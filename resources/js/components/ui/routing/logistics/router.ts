/**
 * # Router — Architecture Overview
 *
 * A `Router` is a reactive wrapper around a `universal-router` instance: it
 * resolves the current path into a page component + layout stack, tracks the
 * result as Svelte `$state`, and exposes it to `RouterView.svelte` — the only
 * consumer that renders a `Router`'s state. `RouterView` passes `data`,
 * `params` and `route` down to each page/layout as props; `RouterHandle`,
 * returned by `useRouter()`, is the navigation API components call
 * (`goTo`, `getPath`, `isActive`, ...) and holds no route state of its own.
 *
 * ## Construction
 *
 * {@link createRouter} (config callback) and {@link createRouterFromRegistrar}
 * (pre-built `RouteRegistrar`) both end up calling `registrar.build()` once
 * to get the `Route[]` tree, then wrap it in `UniversalRouter`. `RoutingExtension`
 * uses the latter to create `app.router` from the routes collected across all
 * plugins/modules.
 *
 * ## Path source: routing strategies
 *
 * A `Router` never reads `window.location` directly — it delegates to a
 * pluggable {@link RoutingStrategy} (`'path'` | `'hash'` | `'transient'`, see
 * `strategy/`), so the same resolution/state logic works whether the app
 * owns the browser URL, uses a hash fragment, or runs fully in-memory.
 * `Router.bind()` wires the strategy's changes into `runResolve()`.
 *
 * ## Resolution & state
 *
 * `runResolve()` is the single place that writes router state — `state`,
 * `component`, `layouts`, `meta`, `nodeData`, `error` are all set there,
 * so a page, a 404 and an error page all leave the router in one consistent
 * shape. Concurrent resolutions are guarded by a single `AbortController` (see
 * `RouterState.startRun()`): starting a resolution aborts its predecessor, so a
 * `runResolve()` call that finishes after a newer one has already started finds
 * its own signal aborted and discards itself rather than clobbering the faster,
 * later one.
 *
 * `universal-router`'s own 404 handling is repurposed: its `errorHandler`
 * always throws, wrapping in {@link RouteResolutionError} everything except a
 * `RouteRedirect`/`RouteHttpError` raised by a middleware, which pass through
 * untouched for `runResolve()` to act on. `runResolve()` then distinguishes a
 * 404 (`status === 404`) from a genuine failure to decide between
 * `state: 'notFound'` and `state: 'error'`.
 *
 * ## Layouts
 *
 * Every resolution rebuilds the layout stack from the matched route's parent
 * chain (see `nodes.ts`) plus the router-wide `rootLayout`, which also
 * covers the 404/error states since those have no matched route to inherit a
 * layout from.
 *
 * ## Data caching
 *
 * `nodeTree.ts`'s `runNode()` (called once per render-chain node) first
 * validates the node's params against its `paramSchema` (see
 * `parseNodeParams()`), then consults an LRU `dataCache` (see `dataCache.ts`)
 * keyed by each node's `cacheKey` (see `dataLoader.ts`) — computed from the
 * *parsed* params — before running its `loadData`, so a cache hit skips the
 * loader entirely. `RouterHandle.clearData()` invalidates entries; see its doc
 * comment for how it interacts with a resolution that is still in flight.
 */
import UniversalRouter, {type Route, type RouteError, type RouteParams} from 'universal-router';
import {type RouteComponent, type RouteLayout, type RouteLayoutLoader, type RouteMeta, RouteRegistrar, type RouteRegistrationCallback, type RouteResultBody} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {resolveComponentModule} from '$lib/components/ui/routing/logistics/lazyComponent.js';
import type {RouteContextExtensions} from '$lib/components/ui/routing/extendableTypes.js';
import {createRouteDataCache} from '$lib/components/ui/routing/logistics/dataCache.js';
import type {AnyRouteConfig} from '$lib/components/ui/routing/logistics/routeConfig.js';
import {RouteHttpError, RouteRedirect, RouteResolutionError} from '$lib/components/ui/routing/logistics/signals.js';
import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';
import {createTransientRoutingStrategy} from '$lib/components/ui/routing/strategy/transientRoutingStrategy.svelte.js';
import {createPathRoutingStrategy} from '$lib/components/ui/routing/strategy/pathRoutingStrategy.svelte.js';
import {createHashRoutingStrategy} from '$lib/components/ui/routing/strategy/hashRoutingStrategy.svelte.js';
import {mergePaths, normalizeBasePath, normalizePath} from '$lib/components/ui/routing/logistics/normalizePath.js';
import {isPathActive, type IsPathActiveOptions, isRouteActive} from '$lib/components/ui/routing/logistics/isActive.js';
import generateUrls, {type UrlParams} from 'universal-router/generateUrls';
import {createNodeTree} from '$lib/components/ui/routing/logistics/nodeTree.js';
import {RouterState} from '$lib/components/ui/routing/logistics/RouterState.svelte.js';
import {resolveRoute} from '$lib/components/ui/routing/logistics/routeResolver.js';

export interface IsActiveOptions extends Omit<IsPathActiveOptions, 'rootPath'> {
    /** Params for a named route target — same meaning as in {@link RouterHandle.getPath}. */
    params?: UrlParams;
}

export interface RouterHandle {
    /**
     * The name this router was created under — what `useRouter(name)` resolves
     * against. Fixed for the router's lifetime. Carried on the handle so a
     * component holding one can tell *which* router it got, which is otherwise
     * unanswerable: a handle exposes only behaviour, and two routers' handles
     * are indistinguishable until one is asked about a path.
     */
    readonly name: string;
    /**
     * Resolves a named route (via `universal-router`'s URL generator) or a
     * literal path (passed through, prefixed with the router's `basePath` if
     * it isn't already) into the path string to use as an `href`. A leading
     * `/` on `routeNameOrPath` is what selects the "literal path" branch.
     */
    getPath: (routeNameOrPath: string, params?: UrlParams) => string;
    /** Short alias for {@link getPath}, for terse usage in templates. */
    p: (routeNameOrPath: string, params?: UrlParams) => string;
    /**
     * Navigates to a literal path through the active {@link RoutingStrategy}.
     * `options.replace` is forwarded to the strategy's `set()` — see its doc
     * comment for what "replace" means per strategy.
     */
    goTo: (path: string, options?: { replace?: boolean }) => Promise<void>;
    /** Resolves `routeName`/`params` via {@link getPath} and navigates to the result. */
    goToRoute: (routeName: string, params?: UrlParams, options?: { replace?: boolean }) => Promise<void>;
    /**
     * Whether `path` is one the active {@link RoutingStrategy} would route
     * through itself (see {@link RoutingStrategy.canHandlePath}). Use this to
     * decide whether an anchor click should be intercepted or left to the
     * browser — hash anchors, query-only links and relative URLs return
     * `false` so the browser handles them natively. Falls back to
     * `path.startsWith('/')` when the strategy does not override the hook.
     */
    canHandlePath: (path: string) => boolean;
    /**
     * Whether the given route name or path points at the page currently shown.
     * Compares concrete paths, so two links to the same route with different
     * params stay distinguishable — use {@link isRouteActive} when you want the
     * param-agnostic "is this section open" answer instead.
     */
    isActive: (routeNameOrPath: string, options?: IsActiveOptions) => boolean;
    /**
     * Whether the named route is currently rendered *or* is an ancestor of the
     * route that is — regardless of which params it was resolved with.
     *
     * Only named routes participate; give a route group a `name` to match a
     * whole section with it.
     */
    isRouteActive: (routeName: string) => boolean;
    /**
     * Resolves the current path again from scratch. Backs the retry button of
     * an error page, and is the way to re-run middlewares after something they
     * depend on (a login, a permission change) happened.
     */
    reload: () => Promise<void>;
    /**
     * Invalidates cached loader data. No argument clears everything.
     * Does NOT re-resolve the current route — call {@link reload} for that.
     *
     * Takes a discriminated object rather than an overloaded `string`
     * because a cache key, a key prefix, a path and a route name are
     * different namespaces that all happen to be `string`s — route names in
     * particular are explicitly not validated for uniqueness by
     * `RouteRegistrar`, so a plain string could collide between a path and a
     * route name with no way to tell which one was meant. Naming the
     * namespace up front makes that ambiguity unrepresentable instead of
     * resolving it in some undocumented order and failing silently on a
     * collision.
     *
     * - `{key}` — removes the single entry with that exact key.
     * - `{keyStartsWith}` — removes every entry whose key starts with the
     *   prefix. Pairs with a `cacheKey` built via `ctx.makeKey(prefix)` (see
     *   `dataLoader.ts`), which always puts the prefix first, so this drops
     *   the whole family of keys a resolver derived from one prefix.
     * - `{path}` — removes every entry loaded for that exact normalized path.
     * - `{route, params}` — resolves `route`/`params` through {@link getPath}
     *   and removes entries loaded for the resulting path, same as `{path}`.
     * - `{route}` without `params` — a wildcard over every entry belonging to
     *   that route's node, regardless of which params it was resolved with.
     *   Cannot be implemented by resolving a path (a route with a required
     *   param has no single path to resolve), so it matches on the node's
     *   `routeName` instead — which is only present when the route/group was
     *   registered with a `name`.
     *
     * Returns whether anything was actually removed.
     */
    clearData: (target?:
                    | { key: string }
                    | { keyStartsWith: string }
                    | { path: string }
                    | { route: string; params?: UrlParams }) => boolean;
    /**
     * Dev helper: dumps the router's current state and full route tree to the
     * console (see `debugger.ts`). Imported dynamically so the dump code
     * never ends up in the production bundle just for being referenced here.
     */
    debug: () => Promise<void>;
}

export interface Router {
    readonly state: 'loading' | 'waiting' | 'notFound' | 'error';
    readonly name: string;
    readonly handle: RouterHandle;
    readonly component: RouteComponent | null;
    /**
     * Layouts wrapping whatever is currently rendered, outermost first — the
     * optional `rootLayout` followed by the layouts collected along the matched
     * route's parent chain. Only the root layout survives a 404 or an error.
     */
    readonly layouts: RouteLayout[];
    readonly path: string;
    /** The matched route, or `null` while nothing is rendered (loading, 404, error). Read by `RouterView` and passed to each node as its `route` prop. */
    readonly route: Route<RouteResultBody> | null;
    /** The matched route's params, or `null` under the same conditions as {@link route}. Passed to each node as its `params` prop. */
    readonly params: RouteParams | null;
    /**
     * The matched route's `meta`, or `null` under the same conditions as
     * {@link route}. Passed to *every* node as its `meta` prop — unlike
     * {@link nodeData}/{@link nodeParams} there is no per-node variant, because
     * meta belongs to the route rather than to the components rendering it, so
     * a layout sees the meta of whichever page is open inside it.
     */
    readonly meta: RouteMeta | null;
    /**
     * The failure that put the router into its current `error` state, or `null`
     * when the last resolution did not fail.
     *
     * Also set for `notFound` — a 404 arrives as a `RouteError` with
     * `status: 404` — so check {@link state} to tell the two apart rather than
     * testing this for nullishness.
     */
    readonly error: Error | RouteError | null;
    /**
     * Data returned by every currently rendered node's `loadData`,
     * index-aligned with `[...layouts, component]` — i.e.
     * `nodeData[nodeData.length - 1]` is the page's own data. A node without
     * a loader contributes an empty object. Reset to `[]` on a 404 or an
     * error so a failed resolution can never leave the previous route's data
     * visible behind the error page.
     */
    readonly nodeData: ReadonlyArray<Record<string, unknown>>;
    /**
     * Each rendered node's params, index-aligned with {@link nodeData} and
     * the render chain. A node with a `paramSchema` gets that schema's
     * parsed/coerced output; a node without one gets the matched route's raw
     * params unchanged. Params are therefore genuinely per-node — a layout
     * and its page can see differently-typed params for the same URL, since
     * each parses only what it declares. Reset to `[]` alongside
     * {@link nodeData} on a 404 or an error.
     */
    readonly nodeParams: ReadonlyArray<unknown>;
    bind: () => void;
}

export interface CreateRouterOptions {
    /** URL prefix every route is resolved under, e.g. `/new`. Defaults to none (routes resolve from `/`). */
    basePath?: string;
    /** Which {@link RoutingStrategy} owns "the current path", or a custom implementation. Defaults to `'transient'` (in-memory, no URL sync) — see `strategy/` for the trade-offs of each. */
    strategy?: 'transient' | 'path' | 'hash' | RoutingStrategy;
    /**
     * Layout wrapping *everything* this router renders — including the 404 and
     * error pages, which have no route of their own to inherit a layout from.
     * Prefer {@link lazyRootLayout} so the layout stays out of the initial bundle.
     */
    rootLayout?: RouteLayout;
    /**
     * {@link rootLayout}, imported only once the router resolves for the first
     * time. Declaring both this and `rootLayout` throws when the router is
     * created.
     *
     * @example
     * createRouter('app', routes, {
     *     lazyRootLayout: async () => (await import('./AppLayout.svelte')).default
     * });
     */
    lazyRootLayout?: RouteLayoutLoader;
    /**
     * Params, data loader and cache key for the router-wide `rootLayout`,
     * built with `configureLayout()`. Same precedence rule as
     * {@link import('./RouteRegistrar.js').RouteOptions.config} — it wins over
     * a `config` the `rootLayout` module exports itself.
     */
    rootLayoutConfig?: AnyRouteConfig;
    /**
     * How many `loadData` results to keep. Defaults to 10; `0` disables
     * caching entirely, which is also what a negative value does (see
     * `dataCache.ts`'s `createRouteDataCache`).
     */
    dataCacheSize?: number;
    /**
     * Concrete values for {@link RouteContextExtensions} — the app-level
     * services (`app`, `restApi`, ...) merged into every context this router
     * produces: middlewares, route actions and `loadData` alike. Handed
     * straight to `UniversalRouter`'s own `context` option, which is what makes
     * it reach middlewares rather than loaders only.
     *
     * `RoutingExtension.ready()` fills this in with `{app, restApi:
     * app.restApi}` when it builds `app.router`; a standalone or transient
     * router may omit it if none of its route code needs those services.
     */
    context?: RouteContextExtensions;
}

/**
 * Creates a `Router` from a route-registration callback — convenience
 * wrapper that builds a fresh {@link RouteRegistrar}, runs `config` against
 * it, and hands the result to {@link createRouterFromRegistrar}. Prefer this
 * for a standalone router; use {@link createRouterFromRegistrar} directly
 * when a registrar is already assembled (e.g. from plugin/module `routes()`
 * hooks, as `RoutingExtension` does).
 */
export function createRouter(
    name: string,
    config: RouteRegistrationCallback,
    options?: CreateRouterOptions
): Router {
    const registrar = new RouteRegistrar();
    config(registrar);
    return createRouterFromRegistrar(name, registrar, options);
}

/**
 * Builds a `Router` from an already-populated {@link RouteRegistrar}:
 * compiles it into a `universal-router` instance, sets up the reactive state
 * described in the module overview above, and returns the `Router` (state +
 * `bind()`) alongside its `RouterHandle` (navigation API for components).
 *
 * `name` must be unique among routers mounted at once — it is what
 * `useRouter(name)` looks a router up by. A duplicate is not an error: the
 * nearest `RouterView` wins for its own subtree, so a nested router reusing an
 * outer one's name makes the outer router unreachable from inside it.
 */
export function createRouterFromRegistrar(
    name: string,
    registrar: RouteRegistrar,
    options?: CreateRouterOptions
): Router {
    const basePath = normalizeBasePath(options?.basePath);
    const state = new RouterState(
        name,
        options,
        createStrategy(options?.strategy),
        basePath,
        createNodeTree(
            name,
            resolveComponentModule,
            options
        ),
        new UniversalRouter(registrar.build(), {
            baseUrl: basePath,
            // Merged into every context `universal-router` builds, so a
            // middleware reaches `context.app` exactly like a loader reaches
            // `ctx.app` — without this, a guard would be the one place in the
            // routing system with no way to ask the application anything.
            context: options?.context ?? {},
            // `universal-router` calls this for ANY throw from a route/middleware
            // action, including a `RouteRedirect`/`RouteHttpError` raised via
            // `redirect()`/`routeError()` inside a middleware — wrapping those in
            // `RouteResolutionError` like every other failure would bury the
            // signal where `runResolve()`'s dedicated `catch` branches for them
            // (see below) can never see it. Let them through unwrapped instead;
            // everything else still gets tagged 'notFound' vs 'error' as before.
            // A `loadData` throwing the same signals needs no such passthrough —
            // loaders run outside `universal-router` entirely (in `runResolve()`,
            // after `resolve()` returns), so their throws already reach
            // `runResolve()`'s `catch` directly.
            errorHandler: (error) => {
                if (error instanceof RouteRedirect || error instanceof RouteHttpError) {
                    throw error;
                }
                throw new RouteResolutionError(error, error.status === 404 ? 'notFound' : 'error');
            }
        }),
        createRouteDataCache(options?.dataCacheSize ?? 30)
    );

    const innerUrlGenerator = (() => {
        const generator = generateUrls(state.innerRouter);
        return (routeName: string, params?: UrlParams) => {
            return normalizePath(generator(routeName, params));
        };
    })();

    function hasBasePrefix(path: string): boolean {
        if (!basePath) {
            return true;
        }
        return path === basePath || path.startsWith(basePath + '/');
    }

    function getPath(routeName: string, params?: UrlParams): string {
        // If the routeName is a path (starts with /), return it directly
        if (routeName.startsWith('/')) {
            const normalizedPath = normalizePath(routeName);
            return hasBasePrefix(normalizedPath) ? normalizedPath : mergePaths(basePath, normalizedPath);
        }
        return innerUrlGenerator(routeName, params);
    }

    function isActive(routeNameOrPath: string, options?: IsActiveOptions): boolean {
        return isPathActive(state.currentPath ?? '', getPath(routeNameOrPath, options?.params), {
            startsWith: options?.startsWith,
            rootPath: basePath
        });
    }

    /**
     * Resolves `path` and publishes the outcome — the single place router state
     * is written, so a page, a 404 and an error all leave it consistent.
     *
     * A newer call always wins: it aborts this one's signal, which is re-checked
     * after every `await`, so a slow resolution can never overwrite a faster one
     * that started later.
     *
     * `redirectChain` carries every path visited so far *because* of a
     * `RouteRedirect` on the way to this call — empty for a "real" navigation
     * (from `bind()`'s `$effect`, `goTo()`, or `reload()`), populated only
     * when this call is itself the result of following a redirect. It exists
     * purely for the loop-detection check below; nothing else reads it.
     */
    async function runResolve(path: string, redirectChain: string[] = []): Promise<void> {
        await resolveRoute(
            state,
            path,
            redirectChain,
            () => handle,
            (...args) => getPath(...args)
        );
    }

    /**
     * Uses `resolvePath` rather than `currentPath` so retrying after a failed
     * resolution targets the path that failed, not the last one that worked.
     */
    async function reload(): Promise<void> {
        await runResolve(state.resolvePath ?? normalizePath(state.strategy.get()));
    }

    /**
     * Pre-emptively invalidates an in-flight resolution before `goTo()`'s
     * `strategy.set()` even reaches `bind()`'s `$effect` — closing the window
     * where a stale resolution could still finish and publish after the user
     * has already navigated away.
     *
     * Returns whether there was anything to cancel — see `goTo()` for why that
     * distinction matters.
     */
    function cancelInFlightResolve(): boolean {
        return state.abortCurrentRun();
    }

    async function goTo(path: string, options?: { replace?: boolean }): Promise<void> {
        const cancelledRunInFlight = cancelInFlightResolve();

        // Normalized before the strategy stores it so that its "did this
        // change?" answer below is about the same string `bind()`'s `$effect`
        // compares against `resolvePath` — the effect normalizes what it reads
        // back, so `/foo/` and `/foo` are one path to it but would otherwise
        // be two different history entries to the strategy.
        const target = normalizePath(path);

        // Normally this is the whole navigation: `set()` marks `bind()`'s
        // resolve `$effect` dirty and that effect runs the resolution. But the
        // effect only fires when the strategy's stored path actually changed,
        // so `goTo()` to the path already on screen resolves nothing — which
        // is correct while the router is idle, and a hang if the line above
        // just cancelled a resolution of that same path (the user clicking the
        // link again because the page is taking too long). Only then does this
        // have to resolve the route itself.
        //
        // It cannot double-resolve either way: `resolveRoute()` assigns
        // `resolvePath` synchronously, so an effect that does fire finds its
        // own `newPath === resolvePath` guard already satisfied.
        if (!state.strategy.set(target, options) && cancelledRunInFlight) {
            void runResolve(target);
        }
    }

    async function goToRoute(routeName: string, params?: UrlParams, options?: { replace?: boolean }): Promise<void> {
        const path = getPath(routeName, params);
        await goTo(path, options);
    }

    /**
     * A resolution already in flight is neither aborted nor prevented from
     * re-populating the cache with what it loaded: its result is about to be
     * rendered either way, and a cache that disagrees with what is on screen
     * would be the worse outcome. Callers who need the *screen* refreshed
     * after clearing want `clearData()` followed by `reload()`.
     */
    function clearData(target?:
                           | { key: string }
                           | { keyStartsWith: string }
                           | { path: string }
                           | { route: string; params?: UrlParams }): boolean {
        if (!target) {
            return state.dataCache.clear();
        }
        if ('key' in target) {
            return state.dataCache.removeWhere((entry) => entry.key === target.key);
        }
        if ('keyStartsWith' in target) {
            return state.dataCache.removeWhere((entry) => entry.key.startsWith(target.keyStartsWith));
        }
        // A `route` target without `params` cannot be turned into a path —
        // `getPath()` has nothing to fill a required `:id` with — so it
        // matches on the node's `routeName` instead, wildcarding over every
        // param combination that route was ever resolved with.
        if ('route' in target && target.params === undefined) {
            return state.dataCache.removeWhere((entry) => entry.routeName === target.route);
        }
        const targetPath = 'route' in target
            ? normalizePath(getPath(target.route, target.params))
            : normalizePath(target.path);
        return state.dataCache.removeWhere((entry) => entry.path === targetPath);
    }

    async function debug(): Promise<void> {
        const dbg = await (import('./debugger.js'));
        dbg.dumpRouterToConsole(state);
    }

    const handle: RouterHandle = {
        name,
        reload,
        clearData,
        debug,
        getPath,
        p: getPath,
        goTo,
        goToRoute,
        canHandlePath: (path: string) => state.strategy.canHandlePath?.(path) ?? path.startsWith('/'),
        isActive,
        isRouteActive: (routeName: string) => isRouteActive(state.currentContext, routeName)
    };

    return {
        get name() {
            return name;
        },
        get state() {
            return state.currentState;
        },
        get component() {
            return state.currentComponent;
        },
        get layouts() {
            return state.currentLayouts;
        },
        get handle() {
            return handle;
        },
        get path() {
            return state.currentPath ?? '';
        },
        get route() {
            return state.currentContext?.route ?? null;
        },
        get params() {
            return state.currentContext?.params ?? null;
        },
        get meta() {
            return state.currentMeta;
        },
        get error() {
            return state.currentError;
        },
        get nodeData() {
            return state.currentNodeData;
        },
        get nodeParams() {
            return state.currentNodeParams;
        },
        bind: () => state.bind((path) => void runResolve(path))
    };
}

function createStrategy(strategy: CreateRouterOptions['strategy']): RoutingStrategy {
    if (strategy === 'transient' || !strategy) {
        return createTransientRoutingStrategy();
    } else if (strategy === 'path') {
        return createPathRoutingStrategy();
    } else if (strategy === 'hash') {
        return createHashRoutingStrategy();
    } else {
        return strategy;
    }
}
