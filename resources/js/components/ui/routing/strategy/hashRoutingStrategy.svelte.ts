import type { RoutingStrategy } from '$lib/components/ui/routing/strategy/types.js';

export function createHashRoutingStrategy(): RoutingStrategy {
    let currentPath = $state(loadPath());

    function loadPath(): string {
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
            function onHashChange(): void {
                currentPath = loadPath();
            }

            window.addEventListener('hashchange', onHashChange);

            return () => {
                window.removeEventListener('hashchange', onHashChange);

                if (window.location.hash !== '') {
                    window.location.hash = '';
                }
                currentPath = '';
            };
        }
    };
}
