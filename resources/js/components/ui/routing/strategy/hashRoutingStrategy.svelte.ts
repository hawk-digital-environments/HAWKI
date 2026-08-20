/**
 * Tracks the route in `location.hash` (`/foo` ⇄ `#/foo`). Works without
 * server-side rewrite rules since the hash never reaches the server, at the
 * cost of the `#` in every URL.
 *
 * `currentPath` is a `$state` mirror of `location.hash`, kept in sync through
 * the `hashchange` event — so `strategy.get()` is reactive under Svelte's
 * effect system (the router's resolve `$effect` reads it) and external hash
 * changes (back/forward, user editing the URL bar) trigger a resolve too.
 * Without the mirror, `goTo()` would write the hash but no `$state` would
 * change, so the effect wouldn't re-run and navigation would silently no-op.
 */
import type {RoutingStrategy, SetRouteInStrategyOptions} from '$lib/components/ui/routing/strategy/types.js';

export function createHashRoutingStrategy(): RoutingStrategy {
    let currentPath = $state(loadHash());

    function loadHash() {
        return window.location.hash.slice(1);
    }

    return {
        set(path: string, options?: SetRouteInStrategyOptions): boolean {
            // `currentPath` and `location.hash` never drift — every writer
            // (`set()` below, `hashchange` in `bind()`) moves both — so this
            // also means the history entry is already the one being asked for.
            if (currentPath === path) {
                return false;
            }
            const newHash = '#' + path;
            if (window.location.hash !== newHash) {
                if (options?.replace) {
                    // `location.hash = ...` always pushes a new history
                    // entry — there is no hash-only equivalent of
                    // `history.replaceState`. `location.replace()` with the
                    // *full* URL (hash included) is the one API that changes
                    // just the fragment without adding an entry; since only
                    // the fragment differs from the current URL, the browser
                    // treats this the same as a same-document hash
                    // navigation (still fires `hashchange`, no full reload).
                    window.location.replace(window.location.pathname + window.location.search + newHash);
                } else {
                    window.location.hash = newHash;
                }
            }
            currentPath = path;
            return true;
        },
        get() {
            return currentPath;
        },
        bind(): () => void {
            function onHashChange() {
                currentPath = loadHash();
            }

            window.addEventListener('hashchange', onHashChange);

            return () => {
                window.removeEventListener('hashchange', onHashChange);
                if (window.location.hash !== '') {
                    // The router owns the fragment for its mounted lifetime.
                    // Remove it without adding an empty-fragment entry to the
                    // browser history or navigating the document when the
                    // owning RouterView unmounts.
                    window.history.replaceState(
                        window.history.state,
                        '',
                        window.location.pathname + window.location.search
                    );
                    currentPath = '';
                }
            };
        }
    };
}
