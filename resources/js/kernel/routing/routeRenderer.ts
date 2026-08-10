import type {RouteRenderer} from '$lib/kernel/routing/RouteRegistrar.js';

/**
 * Creates the default {@link RouteRenderer} — the function that every compiled
 * route uses as its `action`, i.e. the single place that decides what a matched
 * route actually produces (mount the page component, hand a descriptor to a
 * root Svelte component, ...).
 *
 * It is created in `resources/js/app.ts` and injected into
 * {@link RoutingExtension}'s constructor, so the routing kernel itself stays
 * agnostic of the rendering strategy and tests can swap in their own renderer.
 *
 * **Current state: placeholder.** The returned renderer only logs its arguments
 * and returns `null` (see the `@todo` below). For `universal-router` a `null`
 * action result means "no match here", so with this stub every
 * `router.resolve()` call ends in the `Route not found` (404) error — routing
 * is wired up but not yet usable.
 *
 * A real implementation has to handle both shapes of `componentOrLoader`: an
 * eagerly imported Svelte component, or a lazy loader tagged with
 * `type: 'lazy_route'` (registered via `RouteRegistrar.lazyRoute()`), which
 * must be awaited before the component is available.
 *
 * TODO(docs): What is the intended return value of the renderer — the resolved
 * `Component` itself, or a descriptor (component + params) that a root
 * component mounts? And is the renderer also responsible for the lazy import,
 * or should the router only return the loader?
 */
export function createDefaultRouteRenderer(): RouteRenderer {
    return (componentOrLoader, context, params) => {
        console.log('Rendering route with component or loader:', componentOrLoader, 'context:', context, 'params:', params);
        return null; // @todo: Implement actual rendering logic here.
    };
}
