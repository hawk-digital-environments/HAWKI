/**
 * # Router — Architecture Overview
 *
 * A `Router` is a reactive wrapper around a `universal-router` instance: it
 * resolves the current path into a page component + layout stack, tracks the
 * result as Svelte `$state`, and exposes both through a `RouterHandle` that
 * components read via `useRouter()`. `RouterView.svelte` is the only
 * consumer that renders a `Router`'s state; this module has no rendering
 * concerns of its own.
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
 * `component`, `componentProps`, `layouts`, `meta`, `error` are all set there,
 * so a page, a 404 and an error page all leave the router in one consistent
 * shape. Concurrent resolutions are guarded with a monotonic `loadId`: a
 * `runResolve()` call that finishes after a newer one has already started is
 * discarded, so a slow navigation can never clobber a faster, later one.
 *
 * `universal-router`'s own 404 handling is repurposed: its `errorHandler`
 * always throws (wrapped in {@link RouteResolutionError}), and `runResolve()`
 * distinguishes a 404 (`status === 404`) from a genuine failure to decide
 * between `state: 'notFound'` and `state: 'error'`.
 *
 * ## Layouts
 *
 * Every resolution rebuilds the layout stack from the matched route's parent
 * chain (see `layouts.ts`) plus the router-wide `rootLayout`, which also
 * covers the 404/error states since those have no matched route to inherit a
 * layout from.
 */
import UniversalRouter, {type Route, type RouteContext, type RouteError, type RouteParams} from 'universal-router';
import {type HawkiRoute, type RouteComponent, type RouteComponentProps, type RouteLayout, type RouteLayoutOrLoader, type RouteMeta, type RouteResultBody, RouteRegistrar, type RouteRegistrationCallback} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {collectRouteLayouts, resolveRouteLayouts} from '$lib/components/ui/routing/logistics/layouts.js';
import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';
import {z} from 'zod';
import {createTransientRoutingStrategy} from '$lib/components/ui/routing/strategy/transientRoutingStrategy.svelte.js';
import {createPathRoutingStrategy} from '$lib/components/ui/routing/strategy/pathRoutingStrategy.svelte.js';
import {createHashRoutingStrategy} from '$lib/components/ui/routing/strategy/hashRoutingStrategy.svelte.js';
import {mergePaths, normalizeBasePath, normalizePath} from '$lib/components/ui/routing/logistics/normalizePath.js';
import {isPathActive, type IsPathActiveOptions, isRouteActive} from '$lib/components/ui/routing/logistics/isActive.js';
import generateUrls, {type UrlParams} from 'universal-router/generateUrls';

export interface IsActiveOptions extends Omit<IsPathActiveOptions, 'rootPath'> {
    /** Params for a named route target — same meaning as in {@link RouterHandle.getPath}. */
    params?: UrlParams;
}

export interface RouterHandle {
    readonly route: Route<RouteResultBody> | null;
    readonly params: RouteParams | null;
    readonly context: RouteContext<RouteResultBody> | null;
    readonly path: string;
    /**
     * Resolves a named route (via `universal-router`'s URL generator) or a
     * literal path (passed through, prefixed with the router's `basePath` if
     * it isn't already) into the path string to use as an `href`. A leading
     * `/` on `routeNameOrPath` is what selects the "literal path" branch.
     */
    getPath: (routeNameOrPath: string, params?: UrlParams) => string;
    /** Short alias for {@link getPath}, for terse usage in templates. */
    p: (routeNameOrPath: string, params?: UrlParams) => string;
    /** Navigates to a literal path through the active {@link RoutingStrategy}. */
    goTo: (path: string) => Promise<void>;
    /** Resolves `routeName`/`params` via {@link getPath} and navigates to the result. */
    goToRoute: (routeName: string, params?: UrlParams) => Promise<void>;
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
     * The matched route's `meta`, or `null` while nothing is rendered (loading,
     * 404, error). Shared by the page and every layout around it — prefer the
     * typed `useRouteMeta()` hook over reading this raw.
     */
    readonly meta: RouteMeta | null;
    /**
     * The failure that put the router into its current `error` state, or `null`
     * when the last resolution did not fail.
     *
     * Also set for `notFound` — a 404 arrives as a `RouteError` with
     * `status: 404` — so check {@link Router.state} to tell the two apart
     * rather than testing this for nullishness.
     */
    readonly error: Error | RouteError | null;
    /**
     * Resolves the current path again from scratch. Backs the retry button of
     * an error page, and is the way to re-run middlewares after something they
     * depend on (a login, a permission change) happened.
     */
    reload: () => Promise<void>;
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
    readonly contextName: string;
    readonly handle: RouterHandle;
    readonly component: RouteComponent | null;
    readonly componentProps: RouteComponentProps | null;
    /**
     * Layouts wrapping whatever is currently rendered, outermost first — the
     * optional `rootLayout` followed by the layouts collected along the matched
     * route's parent chain. Only the root layout survives a 404 or an error.
     */
    readonly layouts: RouteLayout[];
    readonly path: string;
    /** See {@link RouterHandle.error}. */
    readonly error: Error | RouteError | null;
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
     * Use `lazyComponent()` for a loader.
     */
    rootLayout?: RouteLayoutOrLoader;
}

const routeResultSchema = z.object({
    // `z.function()` would hand back a *wrapper* around the component instead of
    // the component itself; the fresh reference on every resolve makes Svelte
    // tear the page down and re-mount it even when only the params changed.
    component: z.custom<RouteComponent>(value => typeof value === 'function'),
    context: z.object({}).loose(),
    params: z.object({}).loose()
});

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
 * `useRouter(name)` and `RouterView`'s context key are looked up by.
 */
export function createRouterFromRegistrar(
    name: string,
    registrar: RouteRegistrar,
    options?: CreateRouterOptions
): Router {
    let currentState: Router['state'] = $state('loading');
    let currentError: Error | RouteError | null = $state.raw(null);
    let resolvePath: string | null = $state(null);
    let currentPath: string | null = $state(null);
    let currentContext: RouteContext<RouteResultBody> | null = $state.raw(null);
    let currentComponent: RouteComponent | null = $state(null);
    let currentComponentProps: RouteComponentProps | null = $state(null);
    let currentLayouts: RouteLayout[] = $state.raw([]);
    let currentMeta: RouteMeta | null = $state.raw(null);
    const strategy = createStrategy(options?.strategy);

    // Loaded once and shared by every resolution — a failing root layout must
    // not take the whole router down, so it degrades to "no root layout".
    const rootLayoutPromise: Promise<RouteLayout[]> = options?.rootLayout
        ? resolveRouteLayouts([options.rootLayout]).catch((error) => {
            console.error('Failed to load the root layout of router:', name, error);
            return [];
        })
        : Promise.resolve([]);

    const basePath = normalizeBasePath(options?.basePath);
    const innerRouter = new UniversalRouter(registrar.build(), {
        baseUrl: basePath,
        errorHandler: (error) => {
            throw new RouteResolutionError(error, error.status === 404 ? 'notFound' : 'error');
        }
    });

    const innerUrlGenerator = (() => {
        const generator = generateUrls(innerRouter);
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
        return isPathActive(currentPath ?? '', getPath(routeNameOrPath, options?.params), {
            startsWith: options?.startsWith,
            rootPath: basePath
        });
    }

    /** Root layout plus the matched route's own layout chain, loaded in parallel. */
    async function buildLayoutStack(route: Route | null | undefined): Promise<RouteLayout[]> {
        const [rootLayouts, routeLayouts] = await Promise.all([
            rootLayoutPromise,
            resolveRouteLayouts(collectRouteLayouts(route))
        ]);
        return [...rootLayouts, ...routeLayouts];
    }

    let loadId: number = 0;

    /**
     * Resolves `path` and publishes the outcome — the single place router state
     * is written, so a page, a 404 and an error all leave it consistent.
     *
     * A newer call always wins: `loadId` is re-checked after every `await`, so a
     * slow resolution can never overwrite a faster one that started later.
     */
    async function runResolve(path: string): Promise<void> {
        const currentLoadId = ++loadId;
        resolvePath = path;
        currentError = null;
        currentState = 'loading';

        try {
            const renderable = await innerRouter.resolve(path);
            if (!renderable) {
                // noinspection ExceptionCaughtLocallyJS
                throw new Error('No renderable returned for path: ' + path);
            }

            if (currentLoadId !== loadId) {
                console.log('Route resolution for path', path, 'was invalidated before completion.');
                return;
            }

            const parsedRenderable = routeResultSchema.safeParse(renderable);
            if (!parsedRenderable.success) {
                // Thrown instead of handled inline so it runs through the same
                // cleanup as every other failure — in particular so it captures
                // `currentError` for the error page.
                // noinspection ExceptionCaughtLocallyJS
                throw new Error(`Invalid route result for path "${path}"`, {cause: parsedRenderable.error});
            }

            const {component, context, params} = parsedRenderable.data as any as RouteResultBody;

            // Loaded before anything is published so a lazy layout
            // keeps the router in `loading` instead of flashing an
            // unwrapped page.
            const layouts = await buildLayoutStack(context.route);
            if (currentLoadId !== loadId) {
                console.log('Route resolution for path', path, 'was invalidated while its layouts were loading.');
                return;
            }

            currentContext = context;
            currentComponent = component;
            currentComponentProps = {
                context,
                params
            };
            currentMeta = (context.route as HawkiRoute)?.meta ?? null;
            currentLayouts = layouts;

            currentState = 'waiting';
        } catch (error) {
            if (loadId !== currentLoadId) {
                console.warn('Route resolution for path', path, 'was invalidated before error handling could complete.');
                return;
            }
            currentMeta = null;
            currentLayouts = await rootLayoutPromise;
            const originalError = error instanceof RouteResolutionError ? error.originalError : error;
            currentError = originalError as Error | RouteError;

            if (error instanceof RouteResolutionError && error.type === 'notFound') {
                // Not an application failure — just a path nothing matched.
                console.warn('No route matched path:', path);
                currentState = 'notFound';
                return;
            }

            console.error('Error resolving route for path:', path, originalError);
            currentState = 'error';
        } finally {
            if (loadId === currentLoadId) {
                currentPath = path;
            }
        }
    }

    /**
     * Uses `resolvePath` rather than `currentPath` so retrying after a failed
     * resolution targets the path that failed, not the last one that worked.
     */
    async function reload(): Promise<void> {
        await runResolve(resolvePath ?? normalizePath(strategy.get()));
    }

    async function goTo(path: string): Promise<void> {
        strategy.set(path);
    }

    async function goToRoute(routeName: string, params?: UrlParams): Promise<void> {
        const path = getPath(routeName, params);
        await goTo(path);
    }

    async function debug(): Promise<void> {
        const dbg = await (import('./debugger.js'));
        dbg.dumpRouterToConsole({
            name,
            state: currentState,
            currentPath,
            meta: currentMeta,
            layouts: currentLayouts,
            innerRouter
        });
    }

    const handle: RouterHandle = {
        get route() {
            return currentContext?.route ?? null;
        },
        get params() {
            return currentContext?.params ?? null;
        },
        get context() {
            return currentContext;
        },
        get path() {
            return currentPath ?? '';
        },
        get meta() {
            return currentMeta;
        },
        get error() {
            return currentError;
        },
        reload,
        debug,
        getPath,
        p: getPath,
        goTo,
        goToRoute,
        canHandlePath: (path: string) => strategy.canHandlePath?.(path) ?? path.startsWith('/'),
        isActive,
        isRouteActive: (routeName: string) => isRouteActive(currentContext, routeName)
    };

    return {
        get name() {
            return name;
        },
        get contextName() {
            return getRouterContextName(name);
        },
        get state() {
            return currentState;
        },
        get component() {
            return currentComponent;
        },
        get componentProps() {
            return currentComponentProps;
        },
        get layouts() {
            return currentLayouts;
        },
        get handle() {
            return handle;
        },
        get path() {
            return currentPath ?? '';
        },
        get error() {
            return currentError;
        },
        bind: () => {
            $effect(() => {
                return strategy.bind?.(name, basePath) ?? (() => void 0);
            });

            $effect(() => {
                const newPath = normalizePath(strategy.get());
                if (newPath === resolvePath) {
                    return;
                }
                void runResolve(newPath);
            });
        }
    };
}

export function getRouterContextName(name?: string): string {
    return `hawki-router-${name ?? 'app'}`;
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

/**
 * Wraps whatever `universal-router`'s `errorHandler` receives, tagging it
 * with `'notFound'` vs `'error'` so `runResolve()`'s `catch` block can pick
 * the right router state without re-inspecting the original error. Never
 * escapes this module — `runResolve()` unwraps `originalError` before
 * publishing it as {@link RouterHandle.error}.
 */
class RouteResolutionError extends Error {
    constructor(
        public readonly originalError: Error | RouteError,
        public readonly type: 'notFound' | 'error'
    ) {
        super(originalError.message);
    }
}
