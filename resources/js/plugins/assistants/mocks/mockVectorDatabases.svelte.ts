/**
 * Mocked vector databases for the assistant builder's knowledge page —
 * demo/dev only, no API behind it.
 *
 * Enabled via the Vite env flag `VITE_MOCK_VECTOR_DATABASES=true` (root
 * `.env`, needs a node-container restart). When the flag is unset Vite
 * substitutes `undefined` for the env access, `mockVectorDatabasesEnabled`
 * folds to `false`, and the consumers' branches are dead-code-eliminated —
 * this module never ships in a production build. Note: a `import.meta.env.DEV`
 * guard must NOT be used for this (it resolves wrongly in this project's
 * production build, see `nodes.ts`).
 *
 * Selection state is module-local on purpose: it survives navigation between
 * the builder's pages without extending the assistant draft/schema or waking
 * the builder's autosave (mock toggles never call `builder.set`), and it is
 * lost on a full page reload — fine for mocks.
 */

/** One fake vector-database row as rendered on the knowledge page. */
export interface MockVectorDatabase {
    id: string;
    name: string;
    documents: number;
}

/** Hardcoded demo entries — names/documents are mock data, not UI strings. */
export const MOCK_VECTOR_DATABASES: MockVectorDatabase[] = [
    { id: '1', name: 'Hochschul-Wissensbasis', documents: 12450 },
    { id: '2', name: 'Fachbereichsbibliothek', documents: 3280 },
];

/** Whether the knowledge page should render the mocked list. */
export const mockVectorDatabasesEnabled =
    import.meta.env.VITE_MOCK_VECTOR_DATABASES === 'true';

const selectedIds = $state<Set<string>>(new Set());

/** @returns whether the mocked database is toggled on (initially all off). */
export function isMockVectorDatabaseSelected(id: string): boolean {
    return selectedIds.has(id);
}

/** Toggle a mocked database on/off. Svelte 5's `$state` proxies `Set`
 *  mutations, so `add`/`delete` are reactive on their own. */
export function toggleMockVectorDatabase(id: string, active: boolean): void {
    if (active) {
        selectedIds.add(id);
    } else {
        selectedIds.delete(id);
    }
}
