import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';

export type ResponseBodyChunk = string;
export type ResponseBody = string | Array<ResponseBodyChunk> | null;

const BODY_CHUNK_PIPELINE = 'body-chunk';
const RECEIVED_PIPELINE = 'complete';
const ERROR_PIPELINE = 'error';
const ABORT_PIPELINE = 'abort';

interface FlowList {
    [BODY_CHUNK_PIPELINE]: ResponseBodyChunk;
    [RECEIVED_PIPELINE]: ResponseBody;
    [ERROR_PIPELINE]: string;
    [ABORT_PIPELINE]: void;
}

/**
 * Write surface for the response of a single send operation.
 *
 * `MessageSender` passes this to the transport so it can push body chunks,
 * signal completion, or signal an error. The UI never receives this object
 * directly — instead it gets a `ResponseReader` (via {@link createResponseReader})
 * which exposes only the subscribe-side API.
 *
 * State machine: starts open → either `triggerReceived()` (success),
 * `triggerError()` (failure), or `abort()` closes it. All three set `done`
 * to `true` and subsequent calls to any trigger method are no-ops.
 */
export class SendMessageResponse {
    constructor() {
        let resolveBodyPromise: (body: ResponseBody) => void;
        this.body = new Promise((resolve) => {
            resolveBodyPromise = resolve;
        });
        this.resolveBodyPromise = resolveBodyPromise!;
    }

    /** Resolves with the full response body once `triggerReceived()` is called
     *  (or `null` on abort/error). For streaming responses this resolves after the last chunk. */
    public readonly body: Promise<ResponseBody>;
    private readonly resolveBodyPromise: (body: ResponseBody) => void;
    private readonly sync = new SyncPipeline<FlowList>();

    private abortController = $state<AbortController | null>(null);
    private _received = $state(false);
    private _aborted = $state(false);
    private _failed = $state(false);
    private chunks = $state([] as ResponseBodyChunk[]);

    /** `true` when the response arrived as a stream of chunks rather than a single body string. */
    public readonly bodyIsStream = $derived.by(() => this.chunks.length > 0);
    /** `true` when an `AbortController` has been registered via `setAbortController()`. */
    public readonly canAbort = $derived.by(() => this.abortController !== null);
    public readonly received = $derived.by(() => this._received);
    public readonly aborted = $derived.by(() => this._aborted);
    public readonly failed = $derived.by(() => this._failed);
    /** `true` when the response has reached any terminal state (received, aborted, or failed). */
    public readonly done = $derived.by(() => this._received || this._aborted || this._failed);

    /** Cancels the in-flight request via the registered `AbortController` and sets `aborted`. No-op if already done. */
    public abort(): void {
        if (this.done) {
            console.warn('Response is already done. Ignoring abort call.');
            return;
        }

        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        this._aborted = true;
        this.triggerReceivedInternal();
    }

    /** Registers an `AbortController` so the UI can expose an abort button. Called by the transport. */
    public setAbortController(controller: AbortController): void {
        this.abortController = controller;
    }

    /** Subscribes to individual streaming chunks. Returns an unsubscribe function. */
    public onBodyChunk(handler: (chunk: unknown) => void): () => void {
        return this.sync.on(BODY_CHUNK_PIPELINE, handler);
    }

    /** Appends a streaming chunk and notifies `onBodyChunk` subscribers. Called by the transport. */
    public triggerBodyChunk(chunk: ResponseBodyChunk): void {
        this.chunks.push(chunk);
        this.sync.trigger(BODY_CHUNK_PIPELINE, chunk);
    }

    /** Subscribes to response completion. The handler receives the full body (or the chunk
     *  array for streams). Returns an unsubscribe function. */
    public onReceived(handler: (body: unknown) => void): () => void {
        return this.sync.on(RECEIVED_PIPELINE, handler);
    }

    /** Signals that the full response has arrived. If `body` is omitted and chunks were
     *  received, the accumulated chunk array is used as the body. No-op if already done. */
    public triggerReceived(body?: ResponseBody): void {
        if (this.done) {
            console.warn('Response is already done. Ignoring triggerReceived call.');
            return;
        }
        this.triggerReceivedInternal(body);
    }

    private triggerReceivedInternal(body?: ResponseBody): void {
        if (!body) {
            if (this.bodyIsStream) {
                body = this.chunks;
            } else {
                body = null;
            }
        }

        this._received = true;
        this.resolveBodyPromise(body);
        this.sync.trigger(RECEIVED_PIPELINE, body);
    }

    /** Subscribes to response errors. The handler receives a human-readable error message.
     *  Returns an unsubscribe function. */
    public onError(handler: (error: string) => void): () => void {
        return this.sync.on(ERROR_PIPELINE, handler);
    }

    /** Signals that the response failed. Resolves `body` to `null` and notifies `onError`
     *  subscribers. No-op if already done. */
    public triggerError(error: string): void {
        if (this.done) {
            console.warn('Response is already done. Ignoring triggerError call.');
            return;
        }

        this._failed = true;
        this.resolveBodyPromise(null);
        this.sync.trigger(ERROR_PIPELINE, error);
    }

    /** Shorthand for subscribing to both `onReceived` and `onError`. Fires once when
     *  the response reaches any terminal state. Returns an unsubscribe function. */
    public onDone(handler: () => void): () => void {
        const receivedUnsubscribe = this.onReceived(() => handler());
        const errorUnsubscribe = this.onError(() => handler());
        return () => {
            receivedUnsubscribe();
            errorUnsubscribe();
        };
    }
}

/**
 * Returns a read-only subscriber view of `response`.
 *
 * The transport writes to the `SendMessageResponse` instance directly;
 * the UI receives this restricted object so it cannot accidentally call
 * trigger methods. `MessageSender` passes this reader through
 * `SendMessageStatus.response` once the transport has resolved.
 */
export function createResponseReader(response: SendMessageResponse) {
    const onError: SendMessageResponse['onError'] = (...args) => response.onError(...args);
    const onBodyChunk: SendMessageResponse['onBodyChunk'] = (...args) => response.onBodyChunk(...args);
    const onReceived: SendMessageResponse['onReceived'] = (...args) => response.onReceived(...args);
    const onDone: SendMessageResponse['onDone'] = (...args) => response.onDone(...args);
    const abort: SendMessageResponse['abort'] = (...args) => response.abort(...args);
    return {
        onError,
        onBodyChunk,
        onReceived,
        onDone,
        abort,
        get received() {
            return response.received;
        },
        get aborted() {
            return response.aborted;
        },
        get done() {
            return response.done;
        },
        get body() {
            return response.body;
        },
        get canAbort() {
            return response.canAbort;
        },
        get bodyIsStream() {
            return response.bodyIsStream;
        }
    };
}

export type ResponseReader = ReturnType<typeof createResponseReader>;
