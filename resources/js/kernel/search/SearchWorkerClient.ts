/**
 * The main thread's end of `search.worker.ts`.
 *
 * One client per app, owned by the search extension and alive for its whole
 * lifetime: the worker holds the corpus, so restarting it per palette would
 * mean re-shipping thousands of documents every time a bar opens. Sessions
 * borrow it through `SearchSessionHost.queryWorker`.
 *
 * Three rules shape the design:
 *
 * - **The corpus is pushed, queries are pulled.** The client is the index's
 *   delta sink; mutations travel as soon as they happen, so a keystroke only
 *   ever pays for the query itself. Deltas are posted before the searches that
 *   depend on them and messages keep their order, so a reply always describes
 *   the corpus as of the revision its request was posted at.
 * - **A promise always settles.** Cancellation, a restart, a dead worker and a
 *   missed deadline all settle whatever is outstanding; nothing is left for a
 *   session to wait on forever.
 * - **No main-thread fallback.** Matching a large corpus inline is exactly the
 *   jank the worker exists to avoid, so a failure is reported as a *retryable*
 *   error (the host offers "try again") and the immediate and dynamic groups
 *   carry the palette meanwhile.
 */
import type {SearchScores} from './searchEngine.js';
import type {SearchWorkerRequest, SearchWorkerResponse} from './search.worker.js';
import type {IndexDelta, SharedSearchIndex} from './sharedIndex.js';
import {createCrossOriginWorker} from '$lib/utils/crossOriginWorker.js';
import searchWorkerUrl from './search.worker.ts?worker&url';

/** A worker answer: the scores plus the index revision they describe. */
export interface SearchWorkerResult {
    revision: number;
    scores: SearchScores;
}

/** A worker request that outlives this settles as a retryable failure. */
export const SEARCH_WORKER_DEADLINE_MS = 5000;

/**
 * Why the worker path is unavailable. `retryable` decides whether the host
 * offers a retry affordance: a browser without workers never will succeed, a
 * crashed or slow worker might.
 */
export class SearchWorkerError extends Error {
    public readonly retryable: boolean;

    public constructor(message: string, retryable: boolean) {
        super(message);
        this.name = 'SearchWorkerError';
        this.retryable = retryable;
    }
}

export interface SearchWorkerClientOptions {
    /** Overridden in tests; production builds the Vite module worker. */
    createWorker?: () => Worker;
    deadlineMs?: number;
}

interface PendingSearch {
    /** The index revision the request was posted at — what the caller gets back. */
    readonly revision: number;
    resolve(result: SearchWorkerResult): void;
    reject(error: Error): void;
    /** Clears the deadline timer and the abort listener. */
    dispose(): void;
}

export class SearchWorkerClient {
    private readonly index: SharedSearchIndex;
    private readonly createWorker: () => Worker;
    private readonly deadlineMs: number;
    private readonly sink: (delta: IndexDelta) => void;
    private readonly unsubscribe: () => void;
    private readonly pending = new Map<number, PendingSearch>();
    /** Posted but not yet acknowledged delta revisions, oldest first. */
    private outstanding: number[] = [];
    private worker: Worker | null = null;
    private detach: (() => void) | null = null;
    private nextRequestId = 0;
    private latestRevision: number;
    private seeding = false;
    private failure: SearchWorkerError | null = null;
    private disposed = false;

    public constructor(index: SharedSearchIndex, options: SearchWorkerClientOptions = {}) {
        this.index = index;
        this.createWorker = options.createWorker ?? createDefaultWorker;
        this.deadlineMs = options.deadlineMs ?? SEARCH_WORKER_DEADLINE_MS;
        this.latestRevision = index.revision;
        this.sink = delta => this.send(delta);
        // Revisions that carry no worker delta still matter: they tell the
        // client that an unrelated (immediate) change happened, so a reply
        // computed now is current for *this* revision too.
        this.unsubscribe = index.subscribe(() => {
            this.latestRevision = this.index.revision;
        });
        index.setDeltaSink(this.sink);
    }

    /** The last error, while one stands. Cleared by {@link retry}. */
    public get error(): SearchWorkerError | null {
        return this.failure;
    }

    /**
     * The highest index revision the worker's corpus is known to cover — the
     * acknowledgements made visible. `0` while no worker runs.
     */
    public get syncedRevision(): number {
        if (this.worker === null) {
            return 0;
        }
        // Revisions between two deltas changed nothing the worker holds, so an
        // acknowledged delta covers everything up to the next posted one.
        return this.outstanding.length === 0 ? this.latestRevision : this.outstanding[0] - 1;
    }

    /**
     * Scores for `query` over the worker corpus, with the revision they
     * describe. Rejects with a {@link SearchWorkerError} when the worker
     * cannot answer, and with an `AbortError` when `signal` fires; a reply
     * that arrives after either is dropped.
     */
    public search(query: string, signal: AbortSignal): Promise<SearchWorkerResult> {
        if (this.disposed) {
            return Promise.reject(new SearchWorkerError('The search worker client is disposed.', false));
        }
        if (signal.aborted) {
            return Promise.reject(abortError());
        }
        // Nothing to ask about: no round-trip, and above all no worker started
        // for an app whose providers all match immediately.
        if (query.trim() === '' || !this.index.hasWorkerDocuments) {
            return Promise.resolve({revision: this.latestRevision, scores: []});
        }
        if (this.failure) {
            return Promise.reject(this.failure);
        }
        if (!this.start()) {
            return Promise.reject(this.failure ?? new SearchWorkerError('The search worker is unavailable.', true));
        }

        const requestId = ++this.nextRequestId;
        const revision = this.latestRevision;
        return new Promise<SearchWorkerResult>((resolve, reject) => {
            const onAbort = () => this.settle(requestId, entry => entry.reject(abortError()));
            const timer = setTimeout(
                () => this.fail(new SearchWorkerError('The search worker did not answer in time.', true)),
                this.deadlineMs
            );
            signal.addEventListener('abort', onAbort, {once: true});

            this.pending.set(requestId, {
                revision,
                resolve,
                reject,
                dispose: () => {
                    clearTimeout(timer);
                    signal.removeEventListener('abort', onAbort);
                }
            });
            this.post({type: 'search', requestId, revision, query});
        });
    }

    /**
     * Starts over after a failure: a fresh worker, re-seeded with the whole
     * corpus. Sessions re-query on their own once they see the error clear.
     */
    public retry(): void {
        if (this.disposed) {
            return;
        }
        this.stop(new SearchWorkerError('The search worker was restarted.', true));
        this.failure = null;
        this.start();
    }

    /** Releases the worker and the index subscription. Pending searches reject. */
    public dispose(): void {
        if (this.disposed) {
            return;
        }
        this.disposed = true;
        this.unsubscribe();
        this.index.setDeltaSink(null);
        this.stop(new SearchWorkerError('The search worker client is disposed.', false));
    }

    /** Whether a worker is running afterwards. */
    private start(): boolean {
        if (this.worker !== null) {
            return true;
        }
        if (this.failure !== null) {
            return false;
        }

        let worker: Worker;
        try {
            worker = this.createWorker();
        } catch (error) {
            this.fail(error instanceof SearchWorkerError
                ? error
                : new SearchWorkerError(`The search worker could not start: ${describe(error)}`, true));
            return false;
        }

        const onMessage = (event: MessageEvent<SearchWorkerResponse>) => this.receive(event.data);
        const onError = () => this.fail(new SearchWorkerError('The search worker failed.', true));
        worker.addEventListener('message', onMessage);
        worker.addEventListener('error', onError);
        worker.addEventListener('messageerror', onError);
        this.detach = () => {
            worker.removeEventListener('message', onMessage);
            worker.removeEventListener('error', onError);
            worker.removeEventListener('messageerror', onError);
        };
        this.worker = worker;
        this.outstanding = [];

        // Re-setting the sink replays the current corpus synchronously, which
        // is exactly the seed a fresh worker needs.
        this.seeding = true;
        this.index.setDeltaSink(this.sink);
        this.seeding = false;
        return this.worker !== null;
    }

    private send(delta: IndexDelta): void {
        this.latestRevision = delta.revision;
        if (this.worker === null) {
            // Nothing to catch up: the next worker is seeded from scratch.
            return;
        }
        this.outstanding.push(delta.revision);
        this.post(this.seeding
            ? {type: 'reset', revision: delta.revision, documents: [...delta.upserts]}
            : {type: 'apply', revision: delta.revision, upserts: [...delta.upserts], removals: [...delta.removals]});
    }

    private receive(response: SearchWorkerResponse): void {
        if (response.type === 'applied') {
            while (this.outstanding.length > 0 && this.outstanding[0] <= response.revision) {
                this.outstanding.shift();
            }
            return;
        }
        // An unknown request id is a reply to a cancelled search; dropping it
        // is the cancellation.
        this.settle(response.requestId, entry => {
            if (response.revision !== entry.revision) {
                entry.reject(new SearchWorkerError('The search worker returned an outdated revision.', true));
                return;
            }
            entry.resolve({revision: response.revision, scores: response.scores});
        });
    }

    private settle(requestId: number, action: (entry: PendingSearch) => void): void {
        const entry = this.pending.get(requestId);
        if (!entry) {
            return;
        }
        this.pending.delete(requestId);
        entry.dispose();
        action(entry);
    }

    private fail(error: SearchWorkerError): void {
        this.stop(error);
        this.failure = error;
    }

    /** Tears the worker down and settles everything it still owed. */
    private stop(reason: SearchWorkerError): void {
        this.detach?.();
        this.detach = null;
        this.worker?.terminate();
        this.worker = null;
        this.outstanding = [];
        for (const requestId of [...this.pending.keys()]) {
            this.settle(requestId, entry => entry.reject(reason));
        }
    }

    private post(message: SearchWorkerRequest): void {
        try {
            this.worker?.postMessage(message);
        } catch (error) {
            this.fail(new SearchWorkerError(`The search worker could not receive data: ${describe(error)}`, true));
        }
    }
}

function createDefaultWorker(): Worker {
    // Checked once, up front: a browser without workers (or without the Blob
    // URL the dev-server cross-origin start needs) never succeeds, and saying
    // so once beats failing per keystroke.
    if (typeof Worker !== 'function' || typeof Blob !== 'function'
        || typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
        throw new SearchWorkerError('This browser cannot run the search worker.', false);
    }
    return createCrossOriginWorker(searchWorkerUrl, import.meta.url);
}

function abortError(): Error {
    if (typeof DOMException === 'function') {
        return new DOMException('The search was cancelled.', 'AbortError');
    }
    const error = new Error('The search was cancelled.');
    error.name = 'AbortError';
    return error;
}

function describe(error: unknown): string {
    return error instanceof Error ? error.message : String(error);
}
