/**
 * LRU store for `loadData` results, keyed by the cache key a node's
 * `cacheKey` resolves to (see `dataLoader.ts`). One instance per router,
 * sized by `CreateRouterOptions.dataCacheSize`; `router.svelte.ts`'s
 * `runResolve()` is the only writer/reader.
 */

export interface RouteDataCacheEntry {
    readonly key: string;
    readonly nodeId: string;
    /** The normalized path this entry was loaded for — what `clearData({path})` matches on. */
    readonly path: string;
    /** The owning route/group's name, if it has one — what `clearData({route})` (without `params`) matches on as a param-agnostic wildcard. `undefined` when the node's route was never given a `name`, which makes such an entry unreachable through that wildcard. */
    readonly routeName?: string;
    readonly data: Record<string, unknown>;
}

export interface RouteDataCache {
    get(key: string): RouteDataCacheEntry | undefined;
    set(entry: RouteDataCacheEntry): void;
    /** Removes every entry matching the predicate. Returns true if anything was removed. */
    removeWhere(predicate: (entry: RouteDataCacheEntry) => boolean): boolean;
    clear(): boolean;
}

/**
 * Creates an LRU-evicting {@link RouteDataCache} backed by a plain `Map`.
 *
 * The eviction order rides entirely on `Map`'s insertion-order iteration
 * instead of a parallel doubly-linked list: the least-recently-used entry is
 * always whatever `map.keys().next()` yields, because every touch — a `get`
 * hit or a `set` — deletes the entry and re-inserts it, which moves it to the
 * back. So the front of the map is, by construction, always the oldest
 * *unused* entry, and eviction on overflow is just "delete the first key".
 *
 * `maxSize <= 0` disables caching as an explicit early return (a no-op store)
 * rather than letting it fall out of the eviction loop — the loop's
 * `size >= maxSize` check would technically also converge on "never store
 * anything" for `maxSize === 0`, but that would be an emergent accident, not
 * a documented guarantee, and `maxSize < 0` would not converge on it at all.
 */
export function createRouteDataCache(maxSize: number): RouteDataCache {
    if (maxSize <= 0) {
        return {
            get: () => undefined,
            set: () => void 0,
            removeWhere: () => false,
            clear: () => false
        };
    }

    const store = new Map<string, RouteDataCacheEntry>();

    function get(key: string): RouteDataCacheEntry | undefined {
        const entry = store.get(key);
        if (!entry) {
            return undefined;
        }
        // Touch: move to the back so it reads as most-recently-used. See the
        // insertion-order trick explained in this function's doc comment.
        store.delete(key);
        store.set(key, entry);
        return entry;
    }

    function set(entry: RouteDataCacheEntry): void {
        if (store.has(entry.key)) {
            // Overwriting an existing key still counts as a touch.
            store.delete(entry.key);
        } else if (store.size >= maxSize) {
            const oldestKey = store.keys().next().value;
            if (oldestKey !== undefined) {
                store.delete(oldestKey);
            }
        }
        store.set(entry.key, entry);
    }

    function removeWhere(predicate: (entry: RouteDataCacheEntry) => boolean): boolean {
        let removed = false;
        for (const [key, entry] of store) {
            if (predicate(entry)) {
                store.delete(key);
                removed = true;
            }
        }
        return removed;
    }

    function clear(): boolean {
        if (store.size === 0) {
            return false;
        }
        store.clear();
        return true;
    }

    return {get, set, removeWhere, clear};
}
