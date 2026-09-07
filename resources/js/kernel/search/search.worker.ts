/**
 * The search engine in a Web Worker, so that indexing a large static corpus —
 * the chat message index is the motivating case — and matching it per
 * keystroke never blocks typing.
 *
 * It runs the *same* {@link SearchEngine} as the immediate path, so a row
 * ranks identically no matter which side of the boundary matched it. The
 * message shapes below are the whole protocol; `SearchWorkerClient` is the
 * only speaker on the other end.
 *
 * Mutations are acknowledged. The client could infer the worker's state from
 * message order alone, but an explicit `applied` reply is what tells it that a
 * worker is alive and has really caught up, rather than merely posted to.
 */
import {SearchEngine, type SearchDocument, type SearchScores} from './searchEngine.js';

export type SearchWorkerRequest =
    /** Seeds a fresh worker with the whole corpus, discarding whatever it held. */
    | {type: 'reset'; revision: number; documents: SearchDocument[]}
    /** Incremental catch-up for one index revision. */
    | {type: 'apply'; revision: number; upserts: SearchDocument[]; removals: string[]}
    | {type: 'search'; requestId: number; revision: number; query: string};

export type SearchWorkerResponse =
    | {type: 'applied'; revision: number; size: number}
    | {type: 'scores'; requestId: number; revision: number; scores: SearchScores};

/**
 * Applies one request to `engine` and returns what to post back. Kept apart
 * from the worker plumbing below so the protocol can be exercised without a
 * worker at all.
 */
export function handleSearchWorkerRequest(engine: SearchEngine, request: SearchWorkerRequest): SearchWorkerResponse {
    switch (request.type) {
        case 'reset':
            engine.replace(request.documents);
            return {type: 'applied', revision: request.revision, size: engine.size};
        case 'apply':
            for (const document of request.upserts) {
                engine.upsert(document);
            }
            for (const key of request.removals) {
                engine.remove(key);
            }
            return {type: 'applied', revision: request.revision, size: engine.size};
        case 'search':
            // The revision is echoed, not verified: the client posts mutations
            // before the searches that depend on them, and messages are
            // delivered in order, so everything queued ahead of this one is
            // already in the engine.
            return {
                type: 'scores',
                requestId: request.requestId,
                revision: request.revision,
                scores: engine.search(request.query)
            };
    }
}

/** The slice of `DedicatedWorkerGlobalScope` this file uses; the DOM lib does not describe it. */
interface SearchWorkerScope {
    onmessage: ((event: {data: SearchWorkerRequest}) => void) | null;
    postMessage(message: SearchWorkerResponse): void;
    /** Only defined inside a worker — the readiness check below. */
    WorkerGlobalScope?: unknown;
}

const scope = globalThis as unknown as SearchWorkerScope;

// Importing this module outside a worker (tests, tooling) must not install a
// handler, and must not fail either.
if (typeof scope.WorkerGlobalScope !== 'undefined') {
    const engine = new SearchEngine();
    scope.onmessage = event => scope.postMessage(handleSearchWorkerRequest(engine, event.data));
}
