import type {
    AiRequestOptions,
    AiStreamPacket,
    AiStreamRequest,
    AiStreamResult
} from '$lib/kernel/ai/types.js';

const packetTypes = new Set(['header', 'message', 'citation', 'status', 'completion', 'error']);

export interface AiApiOptions {
    endpoint?: string;
    fetch?: typeof globalThis.fetch;
    getCsrfToken?: () => string;
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
    private readonly fetcher: typeof globalThis.fetch;
    private readonly getCsrfToken: () => string;

    constructor(options: AiApiOptions = {}) {
        this.endpoint = options.endpoint ?? '/req/streamAI';
        this.fetcher = options.fetch ?? ((input, init) => globalThis.fetch(input, init));
        this.getCsrfToken = options.getCsrfToken ?? (() =>
            globalThis.document?.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
        );
    }

    /**
     * Starts an AI request and yields the newline-delimited packets returned by
     * the endpoint. Pass an AbortSignal to cancel both the request and stream.
     */
    public async *stream(request: AiStreamRequest, options: AiRequestOptions = {}): AsyncGenerator<AiStreamPacket> {
        const response = await this.fetcher(this.endpoint, {
            method: 'POST',
            headers: this.headers(options.headers),
            body: JSON.stringify(this.requestBody(request)),
            signal: options.signal
        });

        if (!response.ok) {
            throw await this.responseError(response);
        }
        if (!response.body) {
            throw new AiApiError('The AI response did not include a stream.', response.status);
        }

        yield* this.readPackets(response.body);
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
        headers.set('X-CSRF-TOKEN', this.getCsrfToken());
        return headers;
    }

    private async responseError(response: Response): Promise<AiApiError> {
        const fallback = `AI request failed with status ${response.status}${response.statusText ? ` ${response.statusText}` : ''}.`;
        let responseText: string;
        let body: unknown;

        try {
            responseText = await response.text();
        } catch {
            return new AiApiError(fallback, response.status);
        }

        try {
            body = responseText ? JSON.parse(responseText) : undefined;
        } catch {
            return new AiApiError(responseText || fallback, response.status, responseText);
        }

        if (!body || typeof body !== 'object') {
            return new AiApiError(fallback, response.status, body);
        }

        const record = body as Record<string, unknown>;
        const validationErrors = record.errors && typeof record.errors === 'object'
            ? Object.values(record.errors as Record<string, unknown>).flat().filter(value => typeof value === 'string')
            : [];
        const message = validationErrors.join(' ')
            || (typeof record.message === 'string' ? record.message : '')
            || (typeof record.error === 'string' ? record.error : '')
            || fallback;

        return new AiApiError(message, response.status, body);
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
