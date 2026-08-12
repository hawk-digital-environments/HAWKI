import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';

export function createTransientRoutingStrategy(): RoutingStrategy {
    let currentPath = $state('');

    return {
        set(path: string) {
            currentPath = path;
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
