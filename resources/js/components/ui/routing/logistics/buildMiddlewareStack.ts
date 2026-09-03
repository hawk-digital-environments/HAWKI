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
 *
 * A middleware can also `import {redirect} from './signals.js'` and call it
 * to send the user elsewhere entirely, instead of returning a replacement
 * `RouteResultBody`. That distinction matters: swapping the component (the
 * only option before {@link import('./signals.js').RouteRedirect} existed)
 * changes what renders but leaves the URL pointing at a page the user was
 * never actually shown — a bookmark or reload lands back on the guarded
 * route and re-triggers the same swap. `redirect()` closes that gap by
 * updating the URL itself; see `router.ts`'s `runResolve()` for where
 * the signal is caught and turned into a new resolution.
 */
import {type Route} from 'universal-router';
import type {HawkiRouteContext, RegisteredRouteGroupOptions, RegisteredRouteOptions, RouteResultBody} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {isEffectfulMiddleware, type RouteMiddleware} from '$lib/components/ui/routing/logistics/middlewares.js';
import type {GlobalMiddlewares} from '$lib/components/ui/routing/extendableTypes.js';

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
            // Runs before the middleware body so a listener is already live
            // while the guarded route's loaders resolve. Registering it even
            // on a path the middleware is about to deny is deliberate: the
            // resolution then never publishes, and `routeResolver.ts` disposes
            // the whole run's cleanups — cheaper than teaching the effect to
            // re-derive the guard's decision.
            if (isEffectfulMiddleware(middleware)) {
                const dispose = middleware.effect(context as HawkiRouteContext);
                if (dispose) {
                    (context as HawkiRouteContext).onCleanup(dispose);
                }
            }

            const next = () => context.next() as Promise<RouteResultBody | undefined>;
            // `universal-router` types an action's context as its own bare
            // `RouteContext`; the extensions are on it at runtime because
            // `router.ts` passes them as `UniversalRouter`'s `context` option
            // and `resolve()` spreads that into every matched route's context.
            // The cast is the one place that knowledge is asserted — a router
            // created without `options.context` genuinely hands middlewares a
            // context missing them, which is why `RouteContextExtensions`'
            // doc comment pairs the augmentation with supplying the values.
            return (await middleware(context as HawkiRouteContext, next)) ?? null;
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
    assertMiddlewareArray(middlewares);
    return createNestedMiddlewareRoutes(middlewares, route);
}

/**
 * Like {@link buildMiddlewareStack}, but prepends the registrar's global
 * middlewares (filtered through the route's `withoutGlobalMiddlewares` opt-out)
 * in front of the route's own. Globals run outermost — they can deny or
 * rewrite before a route-specific guard ever sees the context — matching the
 * "global policy, local extension" order most middleware stacks expect.
 */
export function buildRouteMiddlewareStack(
    route: Route,
    globalMiddlewares: { middleware: RouteMiddleware, key: keyof GlobalMiddlewares }[],
    options: RouteMiddlewareStackOptions
) {
    const middlewares = options.middlewares ?? [];
    assertMiddlewareArray(middlewares);
    const allMiddlewares = [
        ...filterGlobalMiddlewares(globalMiddlewares, options.withoutGlobalMiddlewares),
        ...middlewares
    ];
    return createNestedMiddlewareRoutes(allMiddlewares, route);
}

/** Runtime guard for registrations coming from untyped/JS callers: throws if `middlewares` is set but not an array. */
function assertMiddlewareArray(middlewares: unknown): asserts middlewares is RouteMiddleware[] {
    if (!Array.isArray(middlewares)) {
        throw new Error('Middlewares must be an array');
    }
}

/**
 * Resolves a route's `withoutGlobalMiddlewares` opt-out into the list of
 * globals that actually apply. `true` drops all of them; a key or array of
 * keys drops only the named ones; anything else keeps the full set. Order of
 * the survivors matches registration order, not the order of the keys.
 */
function filterGlobalMiddlewares(
    globalMiddlewares: { middleware: RouteMiddleware, key: keyof GlobalMiddlewares }[],
    disabledKeys: RouteMiddlewareStackOptions['withoutGlobalMiddlewares']
): RouteMiddleware[] {
    if (disabledKeys === true) {
        return [];
    }

    if (!disabledKeys || (Array.isArray(disabledKeys) && disabledKeys.length === 0)) {
        return globalMiddlewares.map(gm => gm.middleware);
    }

    if (typeof disabledKeys === 'string') {
        disabledKeys = [disabledKeys] as Array<keyof GlobalMiddlewares>;
    }

    if (!Array.isArray(disabledKeys)) {
        throw new Error('withoutGlobalMiddlewares must be a string, an array of keys or true');
    }

    return globalMiddlewares
        .filter(gm => !disabledKeys.includes(gm.key))
        .map(gm => gm.middleware);
}
