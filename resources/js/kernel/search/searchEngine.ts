/**
 * The lexical engine behind every search path, free of Svelte and of the DOM
 * so the exact same code runs on the main thread and inside
 * `search.worker.ts`.
 *
 * It knows nothing about groups or providers: it maps plain
 * {@link SearchDocument}s to scores, and `SharedSearchIndex` maps those back
 * onto rows.
 *
 * Ranking uses Fuse's relevance score with the field weights below. Immediate,
 * worker and remote result indexes use the same scoring configuration.
 *
 * The index is **incremental**: `upsert`/`remove` keep an already-built index
 * current as entries are added or renamed, instead of
 * rebuilding thousands of documents per keystroke.
 */
import Fuse from 'fuse.js';

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

/** Document keys with higher-is-better scores; rows without a hit are absent. */
export type SearchScores = Array<[key: string, score: number]>;

export class SearchEngine {
    private readonly index = new Fuse<SearchDocument>([], {
        keys: [
            {name: 'title', weight: 2},
            {name: 'keywords', weight: 1.5},
            {name: 'content', weight: 1}
        ],
        useTokenSearch: true,
        tokenMatch: 'all',
        threshold: 0.3,
        ignoreLocation: true,
        ignoreDiacritics: true,
        includeScore: true
    });
    private readonly keys = new Set<string>();

    /** How many documents are currently indexed. */
    public get size(): number {
        return this.keys.size;
    }

    /** Inserts or replaces one document, keyed by {@link SearchDocument.id}. */
    public upsert(document: SearchDocument): void {
        this.remove(document.id);
        this.index.add(document);
        this.keys.add(document.id);
    }

    /** Drops one document; a no-op when it was never indexed. */
    public remove(key: string): void {
        if (!this.keys.delete(key)) {
            return;
        }
        this.index.remove(document => document.id === key);
    }

    /** Replaces the whole corpus. Cheaper than diffing when a provider re-loads wholesale. */
    public replace(documents: readonly SearchDocument[]): void {
        const unique = new Map(documents.map(document => [document.id, document]));
        this.index.setCollection([...unique.values()]);
        this.keys.clear();
        for (const key of unique.keys()) {
            this.keys.add(key);
        }
    }

    public clear(): void {
        this.replace([]);
    }

    /**
     * Every query term must fuzzy-match somewhere across the indexed fields.
     * Blank queries use recents/registration order outside this engine.
     */
    public search(query: string): SearchScores {
        if (query.trim() === '' || this.keys.size === 0) {
            return [];
        }

        // Publication sorts descending and reserves zero for server-only hits.
        return this.index.search(query).map(({item, score}) => [
            item.id,
            Math.max(Number.EPSILON, 1 - (score ?? 1))
        ]);
    }
}
