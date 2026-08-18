/**
 * Public surface of the routing system — import everything except the Svelte
 * components (`RouterView`, `RouteNotFound`, `RouteError`) from here rather
 * than reaching into `logistics/`, `strategy/` or `hooks/`:
 *
 * ```ts
 * import {configurePage, type RouteProps, useRouter} from '$lib/components/ui/routing/index.js';
 * ```
 *
 * What is *not* re-exported is deliberate, not an oversight: the resolver, the
 * node tree, `RouterState`, the middleware compiler, the data cache and the
 * node/component-module plumbing are how the router works, not what it offers.
 * Neither are the helpers the router already applies on a caller's behalf —
 * path normalization, `isPathActive`/`isRouteActive` (reachable as
 * `RouterHandle.isActive`/`isRouteActive`), `makeCacheKey` (as
 * `RouteCacheKeyContext.makeKey`), `getRouterContextName` (as
 * `Router.contextName`, which `RouterView` already publishes) and
 * `lazyComponent` (applied by `lazyRoute()` and by every `lazyLayout` /
 * `lazyRootLayout` option).
 * Reaching past this file is the signal that either the need is real and
 * belongs here, or the code doing the reaching is in the wrong place.
 *
 * Two things intentionally keep their deep paths:
 * - The Svelte components, which are imported as `.svelte` files.
 * - `declare module` augmentation of `RouteDataLoaderContextExtensions` (see
 *   `kernel/routing/RoutingExtension.ts`), which has to name the module that
 *   *declares* the interface — a re-export is not a declaration site.
 */

// =========================================================================
// Router
// =========================================================================
export {
    createRouter,
    createRouterFromRegistrar,
    type CreateRouterOptions,
    type IsActiveOptions,
    type Router,
    type RouterHandle
} from '$lib/components/ui/routing/logistics/router.svelte.js';

export {provideDefaultRouterName, useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';

// =========================================================================
// Route registration
// =========================================================================
export {
    RouteRegistrar,
    type HawkiRoute,
    type RouteComponent,
    type RouteComponentLoader,
    type RouteComponentOrLoader,
    type RouteGroupOptions,
    type RouteLayout,
    type RouteLayoutLoader,
    type RouteLayoutOrLoader,
    type RouteMeta,
    type RouteMiddleware,
    type RouteOptions,
    type RouteRegistrationCallback,
    type RouteResult,
    type RouteResultBody
} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

// =========================================================================
// Component configuration
// =========================================================================
export {
    configureLayout,
    configurePage,
    type AnyRouteConfig,
    type RouteConfig,
    type RouteConfigInput,
    type RouteConfigParams
} from '$lib/components/ui/routing/logistics/routeConfig.js';

export type {RouteLayoutProps, RouteProps} from '$lib/components/ui/routing/logistics/routeProps.js';

// =========================================================================
// Data loading
// =========================================================================
export type {
    RouteCacheKey,
    RouteCacheKeyContext,
    RouteCacheKeyResolver,
    RouteDataLoader,
    RouteDataLoaderContext,
    RouteDataLoaderContextExtensions,
    RouteParamsSchema
} from '$lib/components/ui/routing/logistics/dataLoader.js';

// =========================================================================
// Resolution signals
// =========================================================================
/**
 * `redirect()` and `routeError()` are how a middleware aborts a resolution —
 * a `loadData` reaches the same two through `ctx.redirect`/`ctx.error`, but a
 * middleware only receives `universal-router`'s `RouteContext`, which carries
 * neither. The error classes are exported for a custom `errorComponent` that
 * wants to tell an HTTP-status failure apart from a crash.
 */
export {
    redirect,
    routeError,
    RouteHttpError,
    RouteRedirect,
    RouteResolutionError
} from '$lib/components/ui/routing/logistics/signals.js';

// =========================================================================
// Routing strategies
// =========================================================================
export {createHashRoutingStrategy} from '$lib/components/ui/routing/strategy/hashRoutingStrategy.svelte.js';
export {createPathRoutingStrategy} from '$lib/components/ui/routing/strategy/pathRoutingStrategy.svelte.js';
export {createTransientRoutingStrategy} from '$lib/components/ui/routing/strategy/transientRoutingStrategy.svelte.js';
export type {RoutingStrategy, SetRouteInStrategyOptions} from '$lib/components/ui/routing/strategy/types.js';

// =========================================================================
// `universal-router` types that appear in the signatures above
// =========================================================================
/**
 * Re-exported so a middleware author or a page reading its `route` prop has a
 * single import to make. These are `universal-router`'s own types, unchanged —
 * importing them from there directly stays equally valid.
 */
export type {Route, RouteContext, RouteParams} from 'universal-router';
export type {UrlParams} from 'universal-router/generateUrls';
