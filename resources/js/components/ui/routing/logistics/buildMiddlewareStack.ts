/**
 * Materialises {@link RouteMiddleware}s as `universal-router` routes.
 *
 * `universal-router` has no dedicated middleware concept — instead it resolves
 * a matched route *before* descending into its children, and only stops when
 * an action returns something other than `null`/`undefined`. This module
 * exploits that: every middleware becomes an extra parent route with an empty
 * path (so it matches without consuming any part of the URL) whose `action` is
 * the middleware itself, and the guarded route becomes its child.
 *
 * Used by {@link RouteRegistrar} when compiling both single routes
 * (`middlewares` from {@link RouteOptions}) and route groups (`middlewares`
 * from {@link RouteGroupOptions}).
 */
import {type Route} from 'universal-router';
import type {RegisteredRouteGroupOptions, RegisteredRouteOptions, RouteMiddleware} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

/** A group registration reduced to the parts relevant for the middleware stack (its `children` callback has already been compiled by then). */
type GroupMiddlewareStackOptions = Omit<RegisteredRouteGroupOptions, 'children'>;
// TODO(docs): `isLazy` is not a member of `RegisteredRouteOptions` (lazy routes are
// flagged via the `type: 'lazy_route'` marker on the loader instead), so omitting
// it here is a no-op. Leftover from an earlier shape of the options object?
/** A route registration reduced to the parts relevant for the middleware stack (the component is already baked into the inner route's action). */
type RouteMiddlewareStackOptions = Omit<RegisteredRouteOptions, 'component' | 'isLazy'>;

type MiddlewareRoute = Route & { isMiddleware: true };

/**
 * Wraps `children` in a path-less parent route that runs `middleware` first.
 *
 * Because `path: ''` matches anything without consuming URL segments, the
 * child paths stay unchanged. If the middleware returns a `Component` the
 * router stops there and uses it as the resolve result; if it returns
 * `undefined` resolution continues into `children`.
 */
function createMiddlewareRoute(
    middleware: RouteMiddleware,
    children: Route[]
): MiddlewareRoute {
    return {
        path: '',
        action: (context) => middleware(context),
        children,
        isMiddleware: true
    };
}

/**
 * Nests `middlewares` around `innermostRoute`, preserving array order: the
 * first entry becomes the outermost route (and therefore runs first), the last
 * one directly wraps `innermostRoute`. Returns `innermostRoute` untouched when
 * there are no middlewares.
 */
function createNestedMiddlewareRoutes(
    middlewares: RouteMiddleware[],
    innermostRoute: Route
): Route {
    if (middlewares.length === 0) {
        return innermostRoute;
    }

    let currentChildren = [innermostRoute];

    const middlewaresReversed = [...middlewares].reverse();
    const firstMiddleware = middlewaresReversed.shift();

    for (const middleware of middlewaresReversed) {
        currentChildren = [createMiddlewareRoute(middleware, currentChildren)];
    }

    return createMiddlewareRoute(firstMiddleware!, currentChildren);
}

/**
 * Returns `route` wrapped in one nested parent route per configured middleware
 * (outermost = first middleware), or `route` itself if `options.middlewares` is
 * empty/undefined. Throws if `middlewares` is set but not an array — a runtime
 * guard for registrations coming from untyped/JS callers.
 *
 * TODO(docs): {@link RouteMiddleware} may resolve to `null` *or* `undefined`,
 * but `universal-router` treats them differently for an action: `undefined`
 * continues into the wrapped children, while `null` marks the whole subtree as
 * skipped, so the guarded route is never reached and the router falls through
 * to the next sibling (or 404s). Is `null` intended as the "block this branch"
 * signal, and `undefined` as "pass through"?
 */
export function buildMiddlewareStack(
    route: Route,
    options: GroupMiddlewareStackOptions | RouteMiddlewareStackOptions
): Route {
    const middlewares = options.middlewares ?? [];
    if (!Array.isArray(middlewares)) {
        throw new Error('Middlewares must be an array');
    }

    return createNestedMiddlewareRoutes(middlewares, route);
}
