/**
 * The lexical engine behind every search path, free of Svelte and of the DOM
 * so the exact same code runs on the main thread and inside
 * `search.worker.ts`.
 *
 * It knows nothing about groups or providers: it maps plain
 * {@link SearchDocument}s to scores, and `SharedSearchIndex` maps those back
 * onto rows.
 *
 * Ranking uses Orama's BM25 score with the field boosts below. Immediate,
 * worker and remote result indexes use the same scoring configuration.
 *
 * The index is **incremental**: `upsert`/`remove` keep an already-built index
 * current as entries are added or renamed, instead of
 * rebuilding thousands of documents per keystroke.
 */
import {count, create, remove, search, upsert, type Orama} from '@orama/orama';

/** What the engine indexes per row: its document key plus the matched text. */
export interface SearchDocument {
    /** The kernel's document key, `${providerId}#${entry.id}`. */
    id: string;
    title: string;
    /** The entry's keywords, space-joined. */
    keywords: string;
    /** The entry's searchable body, if it has one. */
    content: string;
}

/** Document keys with their engine score; rows without a hit are absent. */
export type SearchScores = Array<[key: string, score: number]>;

const searchSchema = {
    title: 'string',
    keywords: 'string',
    content: 'string'
} as const;

/** Favor titles and keywords over content when Orama calculates scores. */
const SEARCH_FIELD_BOOSTS = {title: 2, keywords: 1.5} as const;

/** How far a term may be off and still match: one edit, i.e. a single typo. */
const SEARCH_TOLERANCE = 1;

type SearchOrama = Orama<typeof searchSchema>;

export class SearchEngine {
    private index: SearchOrama = createIndex();
    private readonly keys = new Set<string>();

    /** How many documents are currently indexed. */
    public get size(): number {
        return this.keys.size;
    }

    /** Inserts or replaces one document, keyed by {@link SearchDocument.id}. */
    public upsert(document: SearchDocument): void {
        sync(upsert(this.index, document));
        this.keys.add(document.id);
    }

    /** Drops one document; a no-op when it was never indexed. */
    public remove(key: string): void {
        if (!this.keys.delete(key)) {
            return;
        }
        sync(remove(this.index, key));
    }

    /** Replaces the whole corpus. Cheaper than diffing when a provider re-loads wholesale. */
    public replace(documents: readonly SearchDocument[]): void {
        this.index = createIndex();
        this.keys.clear();
        for (const document of documents) {
            this.upsert(document);
        }
    }

    public clear(): void {
        this.replace([]);
    }

    /**
     * Scores for `query`: every term must match as a prefix or within one
     * edit of an indexed term (`threshold: 0` is Orama's AND), ranked by the
     * boosted BM25 score. A blank query or an empty index yields nothing — a
     * blank query is answered from recents/registration order, not here.
     */
    public search(query: string): SearchScores {
        if (query.trim() === '' || this.keys.size === 0) {
            return [];
        }

        const hits = sync(search(this.index, {
            term: query,
            properties: ['title', 'keywords', 'content'],
            tolerance: SEARCH_TOLERANCE,
            threshold: 0,
            boost: SEARCH_FIELD_BOOSTS,
            limit: count(this.index)
        })).hits;

        return hits.map(hit => [String(hit.id), hit.score]);
    }
}

function createIndex(): SearchOrama {
    return create({schema: searchSchema});
}

/**
 * Orama's mutators and `search` are typed as "value or promise" because they
 * turn asynchronous once async hooks or plugins are registered. This index
 * registers none, so every call resolves synchronously — which the immediate
 * path relies on, since it indexes and queries inside a reactive effect.
 * Fails loudly should that ever change instead of handing a promise to the UI.
 */
function sync<T>(value: T | Promise<T>): T {
    if (value instanceof Promise) {
        throw new Error('The search index must stay synchronous; do not register async Orama hooks or plugins.');
    }
    return value;
}
