import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';

export interface PathBasedRoutingStrategyOptions {
    basePath?: string;
}

export function createPathRoutingStrategy(options?: PathBasedRoutingStrategyOptions): RoutingStrategy {
    const basePath = options?.basePath ?? '';
    let currentPath = $state(loadPath());

    function loadPath() {
        const currentPath = window.location.pathname;
        if (currentPath.startsWith(basePath)) {
            return currentPath.slice(basePath.length);
        }
        return currentPath;
    }

    function onPathChange() {
        currentPath = loadPath();
    }

    document.addEventListener('popstate', onPathChange);

    return {
        set(path: string) {
            const newPath = basePath + path;
            if (window.location.pathname !== newPath) {
                window.history.pushState({}, '', newPath);
            }
        },
        get() {
            return currentPath;
        },
        clear() {
            if (window.location.pathname !== basePath) {
                window.history.pushState({}, '', basePath);
            }
            document.removeEventListener('popstate', onPathChange);
        }
    };
}
