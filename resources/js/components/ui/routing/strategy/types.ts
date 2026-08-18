export interface SetRouteInStrategyOptions {
    /**
     * Asks for `history.replaceState`-like semantics (no new back-button entry) instead
     * used by a {@link import('../logistics/signals.js').RouteRedirect}
     * so the page that redirected does not stay reachable via back. A
     * strategy with no browser history to speak of (`transientRoutingStrategy`)
     * has nothing to replace and treats this as a no-op; see its own comment.
     */
    replace?: boolean;
}

/**
 * Where a `Router` (see `logistics/router.ts`) reads and writes "the
 * current path". Swapping the strategy is what turns the same router into a
 * path-based, hash-based, or in-memory-only SPA — see `pathRoutingStrategy`,
 * `hashRoutingStrategy`, and `transientRoutingStrategy` for the concrete
 * trade-offs of each.
 */
export interface RoutingStrategy {
    /**
     * Publishes `path` as the current location (e.g. `history.pushState`).
     *
     * Returns whether the path {@link get} reports actually changed. `false`
     * means the strategy already stood on `path`, so no reactive read of
     * `get()` became dirty and the router's resolve `$effect` will not fire —
     * `goTo()` relies on this to tell "the effect is handling it" apart from
     * "nobody will", and resolves the route itself in the latter case.
     */
    set(path: string, options?: SetRouteInStrategyOptions): boolean;

    /** The current path, read fresh — not necessarily the last value passed to {@link set}. */
    get(): string;

    /**
     * Wires the strategy up to whatever external source of truth it tracks
     * (e.g. `popstate`). Called once by `Router.bind()` inside an `$effect`,
     * so returning a teardown function is required for cleanup on unmount or
     * router re-bind. Strategies with nothing to listen to may omit this.
     */
    bind?(name: string, basePath: string): () => void;

    /**
     * Whether the strategy treats `path` as one of its own routes — i.e. a
     * click on an anchor with this `href` should be intercepted and routed
     * through {@link set} rather than left to the browser.
     *
     * This is the single place that distinguishes "a route" from a "local but
     * non-routable href" (hash anchors like `#top`, query-only links like
     * `?q=1`, relative URLs like `foo/bar`). The browser handles those
     * natively and correctly; intercepting them would break anchors and
     * mis-route relative links.
     *
     * Optional escape hatch: the default (when omitted) is "any path starting
     * with `/`", which is correct for the three built-in strategies since they
     * all speak the same `/foo` API form regardless of how they store it
     * (`location.pathname`, `location.hash`, in-memory). A strategy that
     * diverges from this convention — e.g. a future hash strategy that accepts
     * `#/foo` hrefs directly, or one that uses a prefix to share the hash with
     * other routers — overrides this so neither the router nor `Link` has to
     * learn its syntax.
     */
    canHandlePath?(path: string): boolean;
}
