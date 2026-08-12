/**
 * Turns a matched route into the stack of layout components that wrap it.
 *
 * `universal-router` links every matched route to its `parent` while matching,
 * and hands the route objects back untouched — so the layouts registered on
 * the route and on the groups above it are all reachable from the single
 * matched route, without the router having to track them during resolution.
 */
import type {Route} from 'universal-router';
import {type HawkiRoute, type RouteLayout, type RouteLayoutOrLoader} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {resolveComponent} from '$lib/components/ui/routing/logistics/lazyComponent.js';

/**
 * Collects the layouts along the matched route's `parent` chain, ordered
 * outermost first — i.e. ready to be nested into each other from left to right.
 *
 * Routes without a layout (including the path-less middleware wrappers) drop
 * out silently, so the result contains only real layouts.
 */
export function collectRouteLayouts(route: Route | null | undefined): RouteLayoutOrLoader[] {
    const layouts: RouteLayoutOrLoader[] = [];

    let current: HawkiRoute | null | undefined = route;
    while (current) {
        if (current.layout) {
            layouts.push(current.layout);
        }
        current = current.parent;
    }

    // Collected leaf-first while walking up, but rendered outermost-first.
    return layouts.reverse();
}

/**
 * Resolves a layout stack into renderable components, loading lazy layouts in
 * parallel. Returns the *same component references* for layouts that did not
 * change between two resolutions, which is what lets Svelte keep a shared
 * layout mounted while only the page below it swaps out.
 */
export function resolveRouteLayouts(layouts: RouteLayoutOrLoader[]): Promise<RouteLayout[]> {
    return Promise.all(
        layouts.map((layout, index) => resolveComponent(layout, `layout #${index} of the current route`))
    );
}
