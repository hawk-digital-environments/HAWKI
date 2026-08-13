/**
 * Tracks the route in `location.hash` (`/foo` ⇄ `#/foo`). Works without
 * server-side rewrite rules since the hash never reaches the server, at the
 * cost of the `#` in every URL. Reads directly from `window.location` rather
 * than caching state, so it needs no `$state` and no `popstate` listener to
 * stay in sync — the `hashchange` event is not wired up here, so external
 * hash changes (e.g. the user editing the URL bar) do not trigger reactivity.
 */
import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';

export function createHashRoutingStrategy(): RoutingStrategy {
    return {
        set(path: string) {
            const newHash = '#' + path;
            if (window.location.hash !== newHash) {
                window.location.hash = newHash;
            }
        },
        get() {
            return window.location.hash.slice(1);
        },
        bind(): () => void {
            return () => {
                if (window.location.hash !== '') {
                    window.location.hash = '';
                }
            };
        }
    };
}
