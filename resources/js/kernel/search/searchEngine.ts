import MiniSearch from 'minisearch';

/**
 * The MiniSearch core behind the search palette, free of Svelte and DOM so
 * it runs unchanged inside `search.worker.ts` and, as a fallback, on the
 * main thread (`SearchMatcher`). It only knows plain documents and scores;
 * resolving groups and mapping scores back to rows happens in
 * `SearchExtension.svelte.ts`.
 */

/** What the engine indexes per palette row: identity plus the matched text. */
export interface SearchDocument {
    id: string;
    title: string;
    keywords: string;
}

/** Row ids with their relevance score; rows without a hit are absent. */
export type SearchScores = Array<[id: string, score: number]>;

export class SearchEngine {
    private index = createIndex();

    /** Replaces the indexed documents. */
    public replace(documents: SearchDocument[]): void {
        this.index = createIndex();
        this.index.addAll(documents);
    }

    /**
     * Scores for `query`: every term must match as a prefix or within a
     * small edit distance; title hits outrank keyword hits.
     */
    public search(query: string): SearchScores {
        if (query.trim() === '') return [];
        return this.index.search(query).map(hit => [hit.id as string, hit.score]);
    }
}

function createIndex(): MiniSearch<SearchDocument> {
    return new MiniSearch<SearchDocument>({
        fields: ['title', 'keywords'],
        searchOptions: {
            prefix: true,
            fuzzy: 0.2,
            combineWith: 'AND',
            boost: {title: 2}
        }
    });
}
