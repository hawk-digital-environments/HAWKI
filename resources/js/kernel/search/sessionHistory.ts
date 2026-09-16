/**
 * The recent-selection history behind a blank query.
 *
 * Only *identifiers and timestamps* are stored, never titles, content or
 * callbacks: the row a recent resolves to is looked up in the live static index
 * at read time, so a renamed conversation shows its new name and a deleted one
 * simply disappears. That also keeps the local storage free of user content.
 *
 * The history is keyed by the host's identity (user plus connection), so
 * switching accounts on the same browser never surfaces the other account's
 * selections. Without an identity nothing is persisted at all.
 */
import type {ClientStorage} from '$lib/kernel/storage/StorageExtension.js';
import {SEARCH_RECENTS_LIMIT} from '$lib/kernel/search/types.js';

/** One remembered selection. `at` only orders the list; it is never displayed. */
export interface RecentSelection {
    readonly entityKey: string;
    /** Epoch milliseconds of the most recent selection of this entity. */
    readonly at: number;
}

const STORAGE_PREFIX = 'hawki.search.recents.';

export class SearchRecentsStore {
    private readonly storage: ClientStorage;
    private readonly now: () => number;
    private identity: string | null = null;
    private selections: RecentSelection[] = [];

    public constructor(storage: ClientStorage, identity: string | null, now: () => number = Date.now) {
        this.storage = storage;
        this.now = now;
        this.useIdentity(identity);
    }

    /** Most recent first, at most {@link SEARCH_RECENTS_LIMIT} entries. */
    public get entries(): readonly RecentSelection[] {
        return this.selections;
    }

    /** Switches to another user/connection and re-reads that identity's history. */
    public useIdentity(identity: string | null): void {
        this.identity = identity;
        this.selections = this.read();
    }

    /** Moves `entityKey` to the front, keeping the list distinct and capped. */
    public record(entityKey: string): void {
        if (typeof entityKey !== 'string' || entityKey === '') {
            return;
        }
        this.selections = [
            {entityKey, at: this.now()},
            ...this.selections.filter(selection => selection.entityKey !== entityKey)
        ].slice(0, SEARCH_RECENTS_LIMIT);
        this.write();
    }

    public clear(): void {
        this.selections = [];
        const key = this.storageKey();
        if (key !== null) {
            this.storage.removeItem(key);
        }
    }

    private storageKey(): string | null {
        return this.identity === null || this.identity === '' ? null : `${STORAGE_PREFIX}${this.identity}`;
    }

    private read(): RecentSelection[] {
        const key = this.storageKey();
        if (key === null) {
            return [];
        }
        const raw = this.storage.getItem(key);
        if (raw === null) {
            return [];
        }

        let parsed: unknown;
        try {
            parsed = JSON.parse(raw);
        } catch {
            // Corrupted or foreign data is worth nothing here; start over rather
            // than making every read defend against it.
            return [];
        }
        if (!Array.isArray(parsed)) {
            return [];
        }

        const seen = new Set<string>();
        const selections: RecentSelection[] = [];
        for (const candidate of parsed) {
            const entityKey = (candidate as RecentSelection | null)?.entityKey;
            const at = (candidate as RecentSelection | null)?.at;
            if (typeof entityKey !== 'string' || entityKey === '' || typeof at !== 'number' || !Number.isFinite(at)) {
                continue;
            }
            if (seen.has(entityKey)) {
                continue;
            }
            seen.add(entityKey);
            selections.push({entityKey, at});
        }

        return selections.sort((a, b) => b.at - a.at).slice(0, SEARCH_RECENTS_LIMIT);
    }

    private write(): void {
        const key = this.storageKey();
        if (key === null) {
            return;
        }
        this.storage.setItem(key, JSON.stringify(this.selections));
    }
}
