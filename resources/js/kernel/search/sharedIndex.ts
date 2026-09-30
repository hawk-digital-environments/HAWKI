/**
 * The shared static index — one per app, not one per SearchBar.
 *
 * Every activated static provider pushes its current entries in here; every
 * session reads candidates out of it. That sharing is the point: opening a
 * second palette must not re-run a loader or rebuild an index, and a rename
 * in the chat store has to reach both bars from a single update.
 *
 * The index holds rows, not a Fuse index. Fuse lives in `search.worker.ts`:
 * the index only decides *what changed* and hands that to the worker as an
 * {@link IndexDelta}, so building and updating the searchable index never
 * costs main-thread time, however large the corpus.
 *
 * Updates are **incremental and field-aware**. `setProviderEntries` diffs the
 * new snapshot against the previous one by entry id and only ships entries
 * whose *searchable or displayed* text actually changed. Action callbacks are
 * refreshed unconditionally (a re-created `onSelect` closure must not cost a
 * reindex, but it must not go stale either).
 *
 * Each change bumps {@link revision}. Sessions use it to re-derive rows, and
 * the worker path uses it to reject replies that were computed against an
 * older corpus. Note that a revision may advance without a delta: a reorder or
 * an icon change alters rows but no indexed text.
 */
import type {SearchEntry, SearchGroupKind} from '$lib/kernel/search/types.js';
import type {SearchDocument} from '$lib/kernel/search/searchEngine.js';
import {scopeMatches, type ResolvedSearchScope} from '$lib/kernel/search/scope.js';

/** Identity and ownership the index needs about a provider to file its entries. */
export interface IndexedProvider {
    readonly id: string;
    readonly groupId: string;
    readonly pluginId: string;
    readonly moduleId: string;
    readonly kind: SearchGroupKind;
    readonly order: number;
}

/** One indexed entry with everything ranking, scoping and dedupe need. */
export interface IndexedDocument {
    /** `${providerId}#${entry.id}` — unique across the whole index. */
    readonly key: string;
    readonly provider: IndexedProvider;
    /** Position inside the provider's own snapshot; the internal-order fallback. */
    readonly entryOrder: number;
    readonly entry: SearchEntry;
}

/** What the worker has to apply to catch up to a revision. */
export interface IndexDelta {
    readonly revision: number;
    readonly upserts: readonly SearchDocument[];
    readonly removals: readonly string[];
}

/** Receives worker-bound deltas. Set by `SearchWorkerClient`; absent in tests that do not need matching. */
export type IndexDeltaSink = (delta: IndexDelta) => void;

export class SharedSearchIndex {
    private readonly documents = new Map<string, IndexedDocument>();
    /** Provider id → its document keys, in the order the provider returned them. */
    private readonly providerKeys = new Map<string, string[]>();
    /** Document key → the text signature it was last indexed with. */
    private readonly signatures = new Map<string, string>();
    private readonly listeners = new Set<() => void>();
    private sink: IndexDeltaSink | null = null;
    private _revision = 0;

    /** Increases on every accepted change. Never decreases. */
    public get revision(): number {
        return this._revision;
    }

    /** How many documents are indexed; a worker is only worth starting when this is not zero. */
    public get size(): number {
        return this.documents.size;
    }

    /** Notified after every change, once per change. */
    public subscribe(listener: () => void): () => void {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }

    /** Routes worker-bound deltas somewhere. Replays the current corpus so a late worker starts in sync. */
    public setDeltaSink(sink: IndexDeltaSink | null): void {
        this.sink = sink;
        if (!sink) {
            return;
        }
        sink({revision: this._revision, upserts: [...this.documents.values()].map(toSearchDocument), removals: []});
    }

    /**
     * Replaces one provider's entries with `entries`.
     *
     * Returns whether anything changed at all — an unchanged snapshot (the
     * common case when an unrelated part of a store updates) neither bumps the
     * revision nor wakes any session.
     */
    public setProviderEntries(provider: IndexedProvider, entries: readonly SearchEntry[]): boolean {
        const previousKeys = this.providerKeys.get(provider.id) ?? [];
        const nextKeys: string[] = [];
        const upserts: SearchDocument[] = [];
        const seen = new Set<string>();
        let changed = false;

        let entryOrder = 0;
        for (const entry of entries) {
            if (!isUsableEntry(provider.id, entry)) {
                continue;
            }
            const key = documentKey(provider.id, entry.id);
            if (seen.has(key)) {
                console.error(`Search entry id "${entry.id}" is not unique within provider "${provider.id}"; the later entry is dropped.`);
                continue;
            }
            seen.add(key);
            nextKeys.push(key);

            const signature = textSignature(entry);
            const previousSignature = this.signatures.get(key);
            const existing = this.documents.get(key);

            // The callbacks and the position always follow the newest snapshot;
            // only a changed signature means the engine has to be touched.
            const document: IndexedDocument = {key, provider, entryOrder, entry: snapshotEntry(entry)};
            this.documents.set(key, document);

            if (previousSignature !== signature) {
                this.signatures.set(key, signature);
                changed = true;
                upserts.push(toSearchDocument(document));
            } else if (!existing || existing.entryOrder !== entryOrder || existing.entry.icon !== entry.icon) {
                changed = true;
            }
            entryOrder++;
        }

        const removals: string[] = [];
        for (const key of previousKeys) {
            if (seen.has(key)) {
                continue;
            }
            this.documents.delete(key);
            this.signatures.delete(key);
            removals.push(key);
            changed = true;
        }

        this.providerKeys.set(provider.id, nextKeys);

        if (!changed) {
            return false;
        }

        this.commit(upserts, removals);
        return true;
    }

    /** Drops everything a provider contributed, e.g. because it was disabled or its module was removed. */
    public removeProvider(providerId: string): boolean {
        const keys = this.providerKeys.get(providerId);
        if (!keys) {
            return false;
        }
        this.providerKeys.delete(providerId);
        if (keys.length === 0) {
            return false;
        }

        for (const key of keys) {
            this.documents.delete(key);
            this.signatures.delete(key);
        }

        this.commit([], keys);
        return true;
    }

    public document(key: string): IndexedDocument | null {
        return this.documents.get(key) ?? null;
    }

    /** Whether a key still exists — the availability check behind frozen placeholder rows. */
    public has(key: string): boolean {
        return this.documents.has(key);
    }

    /**
     * Every in-scope document, in provider declaration order and then in each
     * provider's own order. Their scores come from `SearchWorkerClient`.
     */
    public candidates(scope: ResolvedSearchScope): IndexedDocument[] {
        const result: IndexedDocument[] = [];
        for (const document of this.documents.values()) {
            if (!scopeMatches(scope, document.provider)) {
                continue;
            }
            result.push(document);
        }
        return result.sort(byProviderThenEntry);
    }

    /** Documents whose entity the given key identifies, newest provider first. Used to resolve recents. */
    public documentsForEntity(entityKey: string, scope: ResolvedSearchScope): IndexedDocument[] {
        const result: IndexedDocument[] = [];
        for (const document of this.documents.values()) {
            if (document.entry.entityKey === entityKey && scopeMatches(scope, document.provider)) {
                result.push(document);
            }
        }
        return result.sort(byProviderThenEntry);
    }

    private commit(upserts: readonly SearchDocument[], removals: readonly string[]): void {
        this._revision++;
        if (this.sink && (upserts.length > 0 || removals.length > 0)) {
            this.sink({revision: this._revision, upserts, removals});
        }
        for (const listener of [...this.listeners]) {
            listener();
        }
    }
}

/**
 * `${providerId}#${entryId}` — the document key the engine and the worker
 * speak in.
 *
 * Entry ids are provider-local and may contain anything, `#` included, so the
 * provider id is escaped: everything up to the first unescaped `#` is the
 * provider, the rest is the entry. Without that, provider `a#b`/entry `c` and
 * provider `a`/entry `b#c` would be the same document.
 */
export function documentKey(providerId: string, entryId: string): string {
    return `${providerId.replace(/[%#]/gu, character => (character === '%' ? '%25' : '%23'))}#${entryId}`;
}

export function toSearchDocument(document: IndexedDocument): SearchDocument {
    return {
        id: document.key,
        title: document.entry.title,
        keywords: (document.entry.keywords ?? []).join(' '),
        content: document.entry.content ?? ''
    };
}

/**
 * Copies the fields the index and the rows read, so a provider that mutates
 * the objects it handed over cannot change indexed text — or a rendered row —
 * behind the index's back. Whatever changes has to come through
 * {@link SharedSearchIndex.setProviderEntries} and carry a revision.
 */
function snapshotEntry(entry: SearchEntry): SearchEntry {
    return {...entry, keywords: entry.keywords ? [...entry.keywords] : undefined};
}

/**
 * What a reindex depends on. `onSelect` and `icon` are deliberately absent:
 * a provider that rebuilds its entry objects on every store read would
 * otherwise reindex its whole corpus on every unrelated change.
 */
function textSignature(entry: SearchEntry): string {
    return JSON.stringify([
        entry.entityKey,
        entry.title,
        entry.description ?? '',
        (entry.keywords ?? []).join('\u0000'),
        entry.content ?? ''
    ]);
}

function isUsableEntry(providerId: string, entry: SearchEntry): boolean {
    if (!entry || typeof entry.id !== 'string' || entry.id === '') {
        console.error(`Search provider "${providerId}" returned an entry without an "id"; it is dropped.`);
        return false;
    }
    if (typeof entry.entityKey !== 'string' || entry.entityKey === '') {
        console.error(`Search entry "${entry.id}" of provider "${providerId}" has no "entityKey"; it is dropped.`);
        return false;
    }
    if (typeof entry.onSelect !== 'function') {
        console.error(`Search entry "${entry.id}" of provider "${providerId}" has no "onSelect"; it is dropped.`);
        return false;
    }
    return true;
}

function byProviderThenEntry(a: IndexedDocument, b: IndexedDocument): number {
    return a.provider.order - b.provider.order || a.entryOrder - b.entryOrder;
}
