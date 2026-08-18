/**
 * In-memory-only strategy: the current path lives in a `$state` variable and
 * never touches `window.location` or history. Useful for a router that isn't
 * meant to own the browser URL at all — e.g. a router embedded in a modal, a
 * preview, or a test — since navigating it has no side effects outside the
 * component tree and resets to `''` when unbound. This is the default
 * strategy when none is specified (see `createStrategy` in
 * `router.svelte.ts`).
 */
import type {RoutingStrategy, SetRouteInStrategyOptions} from '$lib/components/ui/routing/strategy/types.js';

export function createTransientRoutingStrategy(): RoutingStrategy {
    let currentPath = $state('');

    return {
        set(path: string, _options?: SetRouteInStrategyOptions): boolean {
            // No history for this strategy; `replace` is accepted for interface parity and ignored.
            if (currentPath === path) {
                return false;
            }
            currentPath = path;
            return true;
        },
        get() {
            return currentPath;
        },
        bind(): () => void {
            return () => {
                currentPath = '';
            };
        }
    };
}
