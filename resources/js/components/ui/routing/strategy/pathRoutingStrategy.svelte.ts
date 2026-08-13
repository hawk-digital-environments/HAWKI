/**
 * Tracks the route in `location.pathname` via the History API — the strategy
 * a real SPA deployment uses. Requires the server (or a rewrite rule) to
 * serve the app shell for every path under `basePath`, since a hard
 * reload/deep link hits the server directly rather than the client router.
 */
import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';

export function createPathRoutingStrategy(): RoutingStrategy {
    let currentPath = $state(loadPath());

    function loadPath() {
        return window.location.pathname;
    }

    return {
        set(path: string) {
            if (window.location.pathname !== path) {
                window.history.pushState({}, '', path);
            }
            currentPath = path;
        },
        get() {
            return currentPath;
        },
        /**
         * Listens for `popstate` (back/forward navigation) to keep
         * `currentPath` in sync with browser-driven changes — `set()` alone
         * only covers navigation initiated through the router.
         *
         * On teardown, pushes back the path that was current when `bind()`
         * was called, unless the browser is currently sitting exactly on
         * `basePath` (the router's own root) — in that case the location is
         * left untouched.
         */
        bind(_: string, basePath: string): () => void {
            const initialPath = loadPath();

            function onPathChange() {
                currentPath = loadPath();
            }

            window.addEventListener('popstate', onPathChange);

            return () => {
                window.removeEventListener('popstate', onPathChange);

                if (window.location.pathname !== basePath) {
                    window.history.pushState({}, '', initialPath);
                }
            };
        }
    };
}
