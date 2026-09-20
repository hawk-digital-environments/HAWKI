import {ApiTransportError} from '$lib/kernel/api/errors.js';
import type {ApiTransport} from '$lib/kernel/api/transport.js';
import type {
    ChatExchangeRequest,
    ChatExchangeResult,
    ChatRequestOptions,
    OpenResponsesCitation,
    OpenResponsesStreamEvent
} from '$lib/kernel/ai/openResponses/types.js';

export interface OpenResponsesApiOptions {
    endpoint?: string;
    transport: ApiTransport;
}

export class OpenResponsesApiError extends Error {
    constructor(
        message: string,
        public readonly status?: number,
        public readonly responseBody?: unknown,
        options?: ErrorOptions
    ) {
        super(message, options);
        this.name = 'OpenResponsesApiError';
    }
}

/**
 * Browser client for the HAWKI chat proxy speaking the Open Responses wire
 * format (`POST /api/hawki/v1/chat`, default format `openResponses`).
 *
 * The proxy is stateless: callers send the full conversation history on every
 * turn and hold all state themselves. HAWKI-specific request metadata (tool
 * transfer strings, attachment UUIDs, model params) rides the top-level
 * `hawki` request object.
 *
 * `stream()` yields every SSE event for callers that render incremental
 * state; `collect()` and `text()` are conveniences for one-shot tasks such as
 * title or prompt generation.
 */
export class OpenResponsesApi {
    private readonly endpoint: string;
    private readonly transport: ApiTransport;

    constructor(options: OpenResponsesApiOptions) {
        this.endpoint = options.endpoint ?? '/api/hawki/v1/chat';
        this.transport = options.transport;
    }

    /**
     * Starts a chat exchange and yields the SSE events returned by the proxy.
     * The generator ends after the terminal `data: [DONE]` frame. Pass an
     * AbortSignal to cancel both the request and the stream.
     */
    public async *stream(
        request: ChatExchangeRequest,
        options: ChatRequestOptions = {}
    ): AsyncGenerator<OpenResponsesStreamEvent> {
        let responseBody: ReadableStream<Uint8Array>;
        try {
            responseBody = await this.transport(this.endpoint, {
                method: 'POST',
                responseType: 'stream',
                headers: this.headers(options.headers),
                body: JSON.stringify(this.requestBody(request, true)),
                signal: options.signal
            });
        } catch (error) {
            throw this.toApiError(error);
        }

        yield* this.readEvents(responseBody);
    }

    /** Collects a streamed exchange into its final text, citations, and status. */
    public async collect(request: ChatExchangeRequest, options: ChatRequestOptions = {}): Promise<ChatExchangeResult> {
        let streamedText = '';
        let completed = false;
        let status: string | null = null;
        const citations: OpenResponsesCitation[] = [];

        for await (const event of this.stream(request, options)) {
            if (event.type === 'error') {
                throw new OpenResponsesApiError(event.error.message || 'The AI request failed.');
            }
            if (event.type === 'response.output_text.delta') {
                streamedText += event.delta;
            } else if (event.type === 'response.output_text.annotation.added') {
                citations.push({
                    url: event.annotation.url ?? null,
                    title: event.annotation.title ?? null,
                    startIndex: event.annotation.start_index ?? null,
                    endIndex: event.annotation.end_index ?? null
                });
            } else if (
                event.type === 'response.completed' ||
                event.type === 'response.incomplete' ||
                event.type === 'response.failed'
            ) {
                status = event.response.status;
                completed = event.type === 'response.completed';
            }
        }

        return { text: streamedText, citations, completed, status };
    }

    /** Returns only the final text from a streamed exchange. */
    public async text(request: ChatExchangeRequest, options: ChatRequestOptions = {}): Promise<string> {
        return (await this.collect(request, options)).text;
    }

    /**
     * Performs a non-streaming exchange and returns the response resource.
     * Prefer {@link collect} unless the full resource is actually needed —
     * streaming keeps the perceived latency low even for one-shot tasks.
     */
    public async send(
        request: ChatExchangeRequest,
        options: ChatRequestOptions = {}
    ): Promise<Record<string, unknown>> {
        let body: unknown;
        try {
            body = await this.transport(this.endpoint, {
                method: 'POST',
                responseType: 'json',
                headers: this.headers(options.headers),
                body: JSON.stringify(this.requestBody(request, false)),
                signal: options.signal
            });
        } catch (error) {
            throw this.toApiError(error);
        }

        return body as Record<string, unknown>;
    }

    private requestBody(request: ChatExchangeRequest, stream: boolean): Record<string, unknown> {
        const input = request.messages.map((message) => ({
            type: 'message',
            role: message.role,
            content: message.text
        }));
        const lastUser = [...request.messages].reverse().find((message) => message.role === 'user');

        return {
            model: request.model,
            instructions: request.instructions ?? '',
            input,
            stream,
            hawki: {
                ...(request.tools?.length ? { tools: request.tools } : {}),
                ...(request.attachments?.length && lastUser ? { attachments: request.attachments } : {}),
                ...(request.params && Object.keys(request.params).length > 0 ? { params: request.params } : {})
            }
        };
    }

    private headers(additionalHeaders?: HeadersInit): Headers {
        const headers = new Headers(additionalHeaders);
        headers.set('Accept', 'text/event-stream, application/json');
        headers.set('Content-Type', 'application/json');
        return headers;
    }

    private toApiError(error: unknown): Error {
        if (error instanceof ApiTransportError) {
            return new OpenResponsesApiError(error.message, error.status, error.body, { cause: error });
        }
        return error instanceof Error ? error : new OpenResponsesApiError(String(error));
    }

    private async *readEvents(stream: ReadableStream<Uint8Array>): AsyncGenerator<OpenResponsesStreamEvent> {
        const reader = stream.getReader();
        const decoder = new TextDecoder('utf-8');
        let buffer = '';
        let dataLines: string[] = [];
        let finished = false;

        try {
            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    buffer += decoder.decode();
                } else {
                    buffer += decoder.decode(value, { stream: true });
                }

                const lines = buffer.split('\n');
                buffer = done ? '' : (lines.pop() ?? '');
                for (const line of lines) {
                    if (line === '') {
                        const event = this.parseEvent(dataLines);
                        dataLines = [];
                        if (event === 'done') {
                            finished = true;
                            return;
                        }
                        if (event !== null) yield event;
                    } else if (line.startsWith('data:')) {
                        dataLines.push(line.slice(5).trimStart());
                    }
                }

                if (done) {
                    const event = this.parseEvent(dataLines);
                    if (event !== null && event !== 'done') yield event;
                    return;
                }
            }
        } finally {
            if (!finished) {
                await reader.cancel().catch(() => undefined);
            }
            reader.releaseLock();
        }
    }

    private parseEvent(dataLines: string[]): OpenResponsesStreamEvent | null | 'done' {
        if (dataLines.length === 0) {
            return null;
        }
        const payload = dataLines.join('\n');

        if (payload === '[DONE]') {
            return 'done';
        }

        let parsed: unknown;
        try {
            parsed = JSON.parse(payload);
        } catch (error) {
            throw new OpenResponsesApiError('The AI stream returned malformed JSON.', undefined, undefined, {
                cause: error
            });
        }

        if (!parsed || typeof parsed !== 'object' || typeof (parsed as Record<string, unknown>).type !== 'string') {
            throw new OpenResponsesApiError('The AI stream returned an invalid event.');
        }

        return parsed as OpenResponsesStreamEvent;
    }
}
