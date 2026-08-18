/**
 * Materialises {@link RouteMiddleware}s as `universal-router` routes.
 *
 * `universal-router` has no dedicated middleware concept — instead it resolves
 * a matched route *before* descending into its children, and only stops when
 * an action returns something other than `null`/`undefined`. This module
 * exploits that: every middleware becomes an extra parent route with an empty
 * path (so it matches without consuming any part of the URL) whose `action`
 * calls the middleware with a wrapped `next`, and the guarded route becomes
 * its child. The wrapper translates HAWKI's PHP-style middleware contract
 * (return {@link RouteResultBody} | `next()` | nothing) into
 * `universal-router`'s `null`/`undefined` action semantics — see
 * {@link createMiddlewareRoute} for the case-by-case mapping.
 *
 * Used by {@link RouteRegistrar} when compiling both single routes
 * (`middlewares` from {@link RouteOptions}) and route groups (`middlewares`
 * from {@link RouteGroupOptions}).
 */
import {type Route} from 'universal-router';
import type {RegisteredRouteGroupOptions, RegisteredRouteOptions, RouteMiddleware, RouteResultBody} from './RouteRegistrar.js';

/** A group registration reduced to the parts relevant for the middleware stack (its `children` callback has already been compiled by then). */
type GroupMiddlewareStackOptions = Omit<RegisteredRouteGroupOptions, 'children'>;
/** A route registration reduced to the parts relevant for the middleware stack (the component is already baked into the inner route's action). */
type RouteMiddlewareStackOptions = Omit<RegisteredRouteOptions, 'component' | 'path'>;

type MiddlewareRoute = Route & { isMiddleware: true };

/**
 * Wraps `children` in a path-less parent route that runs `middleware` first.
 *
 * Because `path: ''` matches anything without consuming URL segments, the
 * child paths stay unchanged. The action translates HAWKI's middleware
 * contract (return {@link RouteResultBody} | `next()` | nothing) into
 * `universal-router`'s action semantics:
 *
 * - `RouteResultBody` → returned as the resolve result; resolution stops.
 * - `undefined` from `next()` → also handed back, but `universal-router`
 *   treats `undefined` as "continue matching children", which for a wrapping
 *   middleware with a single guarded child means the child gets a chance.
 * - Any other nullish return (middleware returned nothing) → normalised to
 *   `null`, which makes `universal-router` skip the subtree entirely. With no
 *   siblings at this level, the guarded route 404s — the permission-deny path.
 *
 * The `next` handed to the middleware is wrapped so the `resume` footgun of
 * `universal-router`'s `context.next(true)` ("iterate all remaining routes",
 * which would escape the middleware chain and start matching siblings) is
 * unreachable from a HAWKI middleware.
 */
function createMiddlewareRoute(
    middleware: RouteMiddleware,
    children: Route[]
): MiddlewareRoute {
    return {
        path: '',
        action: async (context) => {
            const next = () => context.next() as Promise<RouteResultBody | undefined>;
            return (await middleware(context, next)) ?? null;
        },
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
