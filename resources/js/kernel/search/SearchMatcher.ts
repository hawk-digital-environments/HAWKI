import {SearchEngine, type SearchDocument, type SearchScores} from './searchEngine.js';
import type {SearchWorkerRequest, SearchWorkerResponse} from './search.worker.js';
import searchWorkerUrl from './search.worker.ts?worker&url';
import {createCrossOriginWorker} from '$lib/utils/crossOriginWorker.js';

/**
 * Asynchronous front for `SearchEngine`: `replace` hands the documents over,
 * `search` resolves with the scores for a query. Runs the engine in
 * `search.worker.ts` where Web Workers exist, otherwise — or if the worker
 * fails to start — inline on the main thread (yielding to the event loop
 * first so typing still paints); callers see the same promise either way.
 *
 * Answers arrive in request order; a caller that only wants the latest
 * result should compare against its own newest query (see `SearchDialog`).
 * `dispose` terminates the worker and rejects nothing — pending searches
 * simply never resolve.
 */
export class SearchMatcher {
    private worker: Worker | null;
    private inline: SearchEngine | null;
    private nextRequestId = 0;
    private pending = new Map<number, {query: string; resolve: (scores: SearchScores) => void}>();
    /** Last documents handed over, so an inline fallback can pick up where the worker left. */
    private documents: SearchDocument[] = [];

    public constructor() {
        this.worker = createWorker();
        this.inline = this.worker ? null : new SearchEngine();
        this.worker?.addEventListener('message', (event: MessageEvent<SearchWorkerResponse>) => {
            const request = this.pending.get(event.data.requestId);
            this.pending.delete(event.data.requestId);
            request?.resolve(event.data.scores);
        });
        // A worker that cannot load (e.g. blocked script) reports here; from
        // then on the engine runs inline and open requests are answered by it.
        this.worker?.addEventListener('error', event => {
            console.warn('Search: falling back to inline matching, the worker failed.', event);
            this.fallBackToInline();
        });
    }

    /** Replaces the indexed rows. */
    public replace(documents: SearchDocument[]): void {
        this.documents = documents;
        if (this.worker) {
            this.post({type: 'replace', documents});
        } else {
            this.inline?.replace(documents);
        }
    }

    /** Scores for `query`; empty for a blank query. */
    public search(query: string): Promise<SearchScores> {
        if (query.trim() === '') return Promise.resolve([]);
        if (this.worker) {
            const requestId = ++this.nextRequestId;
            return new Promise(resolve => {
                this.pending.set(requestId, {query, resolve});
                this.post({type: 'search', requestId, query});
            });
        }
        return new Promise(resolve => setTimeout(() => resolve(this.inline?.search(query) ?? []), 0));
    }

    public dispose(): void {
        this.worker?.terminate();
        this.worker = null;
        this.pending.clear();
    }

    private post(message: SearchWorkerRequest): void {
        this.worker?.postMessage(message);
    }

    private fallBackToInline(): void {
        this.worker?.terminate();
        this.worker = null;
        this.inline = new SearchEngine();
        this.inline.replace(this.documents);
        const open = [...this.pending.values()];
        this.pending.clear();
        for (const request of open) request.resolve(this.inline.search(request.query));
    }
}

function createWorker(): Worker | null {
    if (typeof Worker === 'undefined') return null;
    try {
        return createCrossOriginWorker(searchWorkerUrl, import.meta.url);
    } catch (error) {
        console.warn('Search: falling back to inline matching, the worker could not start.', error);
        return null;
    }
}
