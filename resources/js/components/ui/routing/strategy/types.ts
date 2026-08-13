/**
 * Where a `Router` (see `logistics/router.svelte.ts`) reads and writes "the
 * current path". Swapping the strategy is what turns the same router into a
 * path-based, hash-based, or in-memory-only SPA — see `pathRoutingStrategy`,
 * `hashRoutingStrategy`, and `transientRoutingStrategy` for the concrete
 * trade-offs of each.
 */
export interface RoutingStrategy {
    /** Publishes `path` as the current location (e.g. `history.pushState`). */
    set(path: string): void;

    /** The current path, read fresh — not necessarily the last value passed to {@link set}. */
    get(): string;

    /**
     * Wires the strategy up to whatever external source of truth it tracks
     * (e.g. `popstate`). Called once by `Router.bind()` inside an `$effect`,
     * so returning a teardown function is required for cleanup on unmount or
     * router re-bind. Strategies with nothing to listen to may omit this.
     */
    bind?(name: string, basePath: string): () => void;
}
