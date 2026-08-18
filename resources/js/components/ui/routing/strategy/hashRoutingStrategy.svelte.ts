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
import type {RoutingStrategy} from './types.js';

export function createHashRoutingStrategy(): RoutingStrategy {
    let currentPath = $state(loadHash());

    function loadHash() {
        return window.location.hash.slice(1);
    }

    return {
        set(path: string) {
            const newHash = '#' + path;
            if (window.location.hash !== newHash) {
                window.location.hash = newHash;
            }
            currentPath = path;
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
                    window.location.hash = '';
                }
            };
        }
    };
}
