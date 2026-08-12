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
