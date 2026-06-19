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

export class SendMessageResponse {
    constructor() {
        let resolveBodyPromise: (body: ResponseBody) => void;
        this.body = new Promise((resolve) => {
            resolveBodyPromise = resolve;
        });
        this.resolveBodyPromise = resolveBodyPromise!;
    }

    public readonly body: Promise<ResponseBody>;
    private readonly resolveBodyPromise: (body: ResponseBody) => void;
    private readonly sync = new SyncPipeline<FlowList>();

    private abortController = $state<AbortController | null>(null);
    private _received = $state(false);
    private _aborted = $state(false);
    private _failed = $state(false);
    private chunks = $state([] as ResponseBodyChunk[]);

    public readonly bodyIsStream = $derived.by(() => this.chunks.length > 0);
    public readonly canAbort = $derived.by(() => this.abortController !== null);
    public readonly received = $derived.by(() => this._received);
    public readonly aborted = $derived.by(() => this._aborted);
    public readonly failed = $derived.by(() => this._failed);
    public readonly done = $derived.by(() => this._received || this._aborted || this._failed);

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

    public setAbortController(controller: AbortController): void {
        this.abortController = controller;
    }

    public onBodyChunk(handler: (chunk: unknown) => void): () => void {
        return this.sync.on(BODY_CHUNK_PIPELINE, handler);
    }

    public triggerBodyChunk(chunk: ResponseBodyChunk): void {
        this.chunks.push(chunk);
        this.sync.trigger(BODY_CHUNK_PIPELINE, chunk);
    }

    public onReceived(handler: (body: unknown) => void): () => void {
        return this.sync.on(RECEIVED_PIPELINE, handler);
    }

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

    public onError(handler: (error: string) => void): () => void {
        return this.sync.on(ERROR_PIPELINE, handler);
    }

    public triggerError(error: string): void {
        if (this.done) {
            console.warn('Response is already done. Ignoring triggerError call.');
            return;
        }

        this._failed = true;
        this.resolveBodyPromise(null);
        this.sync.trigger(ERROR_PIPELINE, error);
    }

    public onDone(handler: () => void): () => void {
        const receivedUnsubscribe = this.onReceived(() => handler());
        const errorUnsubscribe = this.onError(() => handler());
        return () => {
            receivedUnsubscribe();
            errorUnsubscribe();
        };
    }
}

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
