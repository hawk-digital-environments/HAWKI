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
