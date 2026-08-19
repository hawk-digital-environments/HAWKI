/**
 * Console dump of a router's current state and full compiled route tree.
 * Dev-only tool, reached through `RouterHandle.debug()`, which imports this
 * module dynamically so it never lands in the production bundle.
 */
import type {Route} from 'universal-router';
import type {Path} from 'universal-router/path-to-regexp';
import {mergePaths} from '$lib/components/ui/routing/logistics/normalizePath.js';
import type {HawkiRoute, RouteLayoutOrLoader} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {isLazyComponentLoader} from '$lib/components/ui/routing/logistics/lazyComponent.js';
import type {RouterState} from '$lib/components/ui/routing/logistics/RouterState.svelte.js';

/** Logs `dump`'s state, current path, meta, layout stack, and the full route tree (with middleware/catch-all markers) to the console. */
export function dumpRouterToConsole(state: RouterState) {
    // Empty is the neutral base (see `normalizeBasePath`); shown as '/' for readability.
    const baseUrl = state.innerRouter.baseUrl;

    console.log('Router dump:', state.name);
    console.log('-'.repeat(50));
    console.log('  state:', state.currentState ?? 'NULL');
    console.log('  path:', state.currentPath);
    console.log('  basePath', baseUrl || '/');
    if (state.currentMeta) {
        console.log('  meta:', state.currentMeta ?? 'NONE');
    }
    if (state.currentLayouts.length > 0) {
        console.log('  layouts:', state.currentLayouts.map(describeLayout).join(' > '));
    }
    console.log('  routes:');
    for (const route of state.innerRouter.root.children || []) {
        recursivelyDumpRoute(route, 1, baseUrl);
    }
}

function recursivelyDumpRoute(route: Route, depth: number = 1, path: string = '/') {
    const indent = '  '.repeat(depth);
    const paths = arrayifyPaths(route.path);
    const {meta, layout} = route as HawkiRoute;
    for (const p of paths) {
        const localPath = mergePaths(path, p);
        console.log(`${indent}  Path: ${localPath}`);
        if (route.action) {
            console.log(`${indent}    Action: ✔️`);
        }
        if (route.name) {
            console.log(`${indent}    Name: ${route.name}`);
        }
        if (layout) {
            console.log(`${indent}    Layout: ${describeLayout(layout)}`);
        }
        if (meta && Object.keys(meta).length > 0) {
            console.log(`${indent}    Meta:`, meta);
        }
        // An empty (but present) children list is how a catch-all is encoded.
        if (route.children && route.children.length === 0) {
            console.log(`${indent}    Catch-all: ✔️`);
        } else if (route.children) {
            console.log(`${indent}    Children:`);
            for (const child of route.children) {
                recursivelyDumpRoute(child, depth + 2, localPath);
            }
        }
    }
}

/** A lazy layout has not been imported yet, so only its loader can be named. */
function describeLayout(layout: RouteLayoutOrLoader): string {
    return isLazyComponentLoader(layout)
        ? `${describeComponent(layout)} (lazy)`
        : describeComponent(layout);
}

/** Components are functions, so the best label available is their (possibly minified) function name. */
function describeComponent(component: unknown): string {
    return (component as { name?: string }).name || 'anonymous';
}

function arrayifyPaths(path: Path | Path[] | undefined): string[] {
    if (path === undefined) {
        return [];
    }

    return (Array.isArray(path) ? path : [path])
        .map(path => {
            if (typeof path === 'string') {
                return path;
            }

            if (typeof path === 'object') {
                return 'TOKEN DATA: ' + JSON.stringify(path);
            }

            return '';
        });
}
