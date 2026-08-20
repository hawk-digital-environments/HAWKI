import type {
    AiRequestOptions,
    AiStreamPacket,
    AiStreamRequest,
    AiStreamResult
} from '$lib/kernel/ai/types.js';
import {ApiTransportError} from '$lib/kernel/api/errors.js';
import type {ApiTransport} from '$lib/kernel/api/transport.js';

const packetTypes = new Set(['header', 'message', 'citation', 'status', 'completion', 'error']);

export interface AiApiOptions {
    endpoint?: string;
    transport: ApiTransport;
}

export class AiApiError extends Error {
    constructor(
        message: string,
        public readonly status?: number,
        public readonly responseBody?: unknown,
        options?: ErrorOptions
    ) {
        super(message, options);
        this.name = 'AiApiError';
    }
}

/**
 * Browser client for HAWKI's internal `/req/streamAI` endpoint.
 *
 * `stream()` exposes every NDJSON packet for callers that render incremental
 * state. `collect()` and `text()` are conveniences for one-shot internal AI
 * tasks such as title or prompt generation.
 */
export class AiApi {
    private readonly endpoint: string;
    private readonly transport: ApiTransport;

    constructor(options: AiApiOptions) {
        this.endpoint = options.endpoint ?? '/req/streamAI';
        this.transport = options.transport;
    }

    /**
     * Starts an AI request and yields the newline-delimited packets returned by
     * the endpoint. Pass an AbortSignal to cancel both the request and stream.
     */
    public async *stream(request: AiStreamRequest, options: AiRequestOptions = {}): AsyncGenerator<AiStreamPacket> {
        let responseBody: ReadableStream<Uint8Array>;
        try {
            responseBody = await this.transport(this.endpoint, {
                method: 'POST',
                responseType: 'stream',
                headers: this.headers(options.headers),
                body: JSON.stringify(this.requestBody(request)),
                signal: options.signal
            });
        } catch (error) {
            if (error instanceof ApiTransportError) {
                throw new AiApiError(error.message, error.status, error.body, {cause: error});
            }
            throw error;
        }

        yield* this.readPackets(responseBody);
    }

    /** Collects a streamed request into its final text and citations. */
    public async collect(request: AiStreamRequest, options: AiRequestOptions = {}): Promise<AiStreamResult> {
        let streamedText = '';
        let completionText = '';
        let completed = false;
        const citations: unknown[] = [];

        for await (const packet of this.stream(request, options)) {
            if (packet.type === 'error') {
                throw new AiApiError(aiPacketText(packet.content) || 'The AI request failed.');
            }
            if (packet.type === 'message') {
                streamedText += aiPacketText(packet.content);
            } else if (packet.type === 'citation' && packet.content !== undefined) {
                citations.push(packet.content);
            } else if (packet.type === 'completion') {
                completionText = aiPacketText(packet.content);
                completed = Boolean(packet.isDone);
            }
        }

        return {
            text: completionText || streamedText,
            citations,
            completed
        };
    }

    /** Returns only the final text from a streamed request. */
    public async text(request: AiStreamRequest, options: AiRequestOptions = {}): Promise<string> {
        return (await this.collect(request, options)).text;
    }

    private requestBody(request: AiStreamRequest): Record<string, unknown> {
        return {
            broadcast: false,
            threadIndex: request.threadIndex ?? 0,
            slug: request.slug ?? '',
            ...(request.isUpdate === undefined ? {} : {isUpdate: request.isUpdate}),
            ...(request.messageId === undefined ? {} : {messageId: request.messageId}),
            ...(request.key === undefined ? {} : {key: request.key}),
            payload: {
                model: request.model,
                broadcast: false,
                stream: true,
                messages: request.messages,
                ...(request.tools === undefined ? {} : {tools: request.tools}),
                ...(request.params === undefined ? {} : {params: request.params})
            }
        };
    }

    private headers(additionalHeaders?: HeadersInit): Headers {
        const headers = new Headers(additionalHeaders);
        headers.set('Accept', 'application/json');
        headers.set('Content-Type', 'application/json');
        return headers;
    }

    private async *readPackets(stream: ReadableStream<Uint8Array>): AsyncGenerator<AiStreamPacket> {
        const reader = stream.getReader();
        const decoder = new TextDecoder('utf-8');
        let buffer = '';
        let finished = false;

        try {
            while (true) {
                const {done, value} = await reader.read();
                if (done) {
                    buffer += decoder.decode();
                    finished = true;
                } else {
                    buffer += decoder.decode(value, {stream: true});
                }

                const lines = buffer.split('\n');
                buffer = done ? '' : (lines.pop() ?? '');
                for (const line of lines) {
                    if (line.trim()) yield this.parsePacket(line);
                }

                if (done) break;
            }

            if (buffer.trim()) yield this.parsePacket(buffer);
        } finally {
            if (!finished) {
                await reader.cancel().catch(() => undefined);
            }
            reader.releaseLock();
        }
    }

    private parsePacket(line: string): AiStreamPacket {
        let packet: unknown;
        try {
            packet = JSON.parse(line);
        } catch (error) {
            throw new AiApiError('The AI stream returned malformed JSON.', undefined, undefined, {cause: error});
        }

        if (!packet || typeof packet !== 'object' || !packetTypes.has(String((packet as Record<string, unknown>).type))) {
            throw new AiApiError('The AI stream returned an invalid packet.');
        }
        return packet as AiStreamPacket;
    }
}

/** Extracts text from both string packets and `{text: ...}` packet content. */
export function aiPacketText(content: unknown): string {
    if (typeof content === 'string') return content;
    if (content && typeof content === 'object' && 'text' in content) {
        return String((content as {text: unknown}).text ?? '');
    }
    return '';
}
