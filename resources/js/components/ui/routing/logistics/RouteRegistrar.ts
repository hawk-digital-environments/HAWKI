import {type Route, type RouteContext, type RouteParams, type RouteResult as URRouteResult} from 'universal-router';
import {buildMiddlewareStack, buildRouteMiddlewareStack} from '$lib/components/ui/routing/logistics/buildMiddlewareStack.js';
import {type ComponentLoader, type ComponentOrLoader, lazyComponent, resolveLayoutOption} from '$lib/components/ui/routing/logistics/lazyComponent.js';
import type {Component} from 'svelte';
import type {RouteNode, RouteNodeKind} from '$lib/components/ui/routing/logistics/nodes.js';
import type {AnyRouteConfig} from '$lib/components/ui/routing/logistics/routeConfig.js';
import type {RouteLayoutProps, RouteProps} from '$lib/components/ui/routing/logistics/routeProps.js';
import type {GlobalMiddlewares, RouteContextExtensions} from '$lib/components/ui/routing/extendableTypes.js';
import type {RouteMiddleware} from '$lib/components/ui/routing/logistics/middlewares.js';
import type {RouterHandle} from '$lib/components/ui/routing/logistics/router.js';

/** Signature of a callback that receives a {@link RouteRegistrar} to register routes on — used for plugin/module `routes()` hooks and for `registrar.group()` children. */
export type RouteRegistrationCallback = (registrar: RouteRegistrar) => void;

/**
 * A Svelte component used as the "page" of a route. Receives {@link RouteProps}
 * — a page declares its own `TParams`/`TData` and destructures what it needs
 * from `$props()`; `RouterView` supplies the rest.
 */
export type RouteComponent = Component<RouteProps<any, any, any>>;
/** Lazily imports a route's page component, e.g. `async () => (await import('./pages/ChatIndex.svelte')).default`. */
export type RouteComponentLoader = ComponentLoader<RouteComponent>;
/**
 * Either an eagerly imported page component or a loader for one.
 *
 * Because a Svelte 5 `Component` *is* a function, a loader cannot be told
 * apart from a component at runtime — hence {@link RouteRegistrar.lazyRoute}
 * tags loaders with the `type: 'lazy_route'` marker, which
 * `lazyComponent.ts`'s `isLazyComponentLoader()` checks to decide whether
 * `resolveComponentModule()` has to `await` the component first.
 */
export type RouteComponentOrLoader = ComponentOrLoader<RouteComponent>;

/**
 * A component that wraps a route's page (and any layout below it) in its
 * `children` snippet — the router's equivalent of a nested layout. Receives
 * {@link RouteLayoutProps}, same as a page plus `children`.
 */
export type RouteLayout = Component<RouteLayoutProps<any, any, any>>;
/** Lazily imports a layout component, e.g. `async () => (await import('./AdminLayout.svelte')).default`. */
export type RouteLayoutLoader = ComponentLoader<RouteLayout>;
/**
 * Either an eagerly imported layout or a loader for one. Loaders must be
 * wrapped in `lazyComponent()` so they can be told apart from a component.
 */
export type RouteLayoutOrLoader = ComponentOrLoader<RouteLayout>;

/**
 * Arbitrary data a route carries for the components that render it — page
 * title, icon, permission hints, whatever the owning plugin needs.
 *
 * Meta belongs to the *route*, not to the layouts around it: the page and
 * every layout wrapping it receive the matched route's meta as their `meta`
 * prop, so a layout reads the meta of whichever page is currently open inside
 * it. Untyped here on purpose — narrow it where it is read, through
 * {@link RouteProps}' third type argument. Router-internal settings (such as
 * {@link RouteOptions.layout}) deliberately live outside of it, so they can
 * never collide with — or leak into — a plugin's own meta.
 */
export type RouteMeta = Record<string, unknown>;

/**
 * The `universal-router` {@link Route} plus the extra fields this router
 * stores on it. `universal-router` passes route objects through untouched, so
 * the matched route (and its whole `parent` chain) still carries them at
 * resolve time.
 */
export interface HawkiRoute<R = any> extends Route<R> {
    meta?: RouteMeta;
    layout?: RouteLayoutOrLoader;
    /**
     * The compiled node chain for this route — `layout` from
     * {@link RouteOptions.layout}/{@link RouteGroupOptions.layout}, `page` from
     * the route's own component. `layout` stays the registration-time input;
     * `nodes` is what {@link buildRouteFromOptions}/{@link buildRouteGroupFromOptions}
     * derive from it and what `nodes.ts` walks at resolve time.
     */
    nodes?: { layout?: RouteNode; page?: RouteNode };
}

/**
 * `universal-router`'s {@link RouteContext} plus whatever the application
 * merged into every context through
 * {@link import('./router.js').CreateRouterOptions.context} — the same
 * `{@link RouteContextExtensions}` a loader sees, because the router hands that
 * object straight to `UniversalRouter`'s own `context` option.
 *
 * Naming the intersection matters: `RouteContext` carries
 * `[propName: string]: any`, so *any* property access on a bare one compiles.
 * Only the intersection turns `context.app` into a typed `HawkiApp` and a
 * misspelling into a compile error — TypeScript prefers a declared property
 * over an index signature, so the extensions win the lookup rather than being
 * widened back to `any` by it.
 */
export type HawkiRouteContext<R = any> = RouteContext<R> & RouteContextExtensions & RouteResolutionContext;

/**
 * The part of a route context that belongs to *one* resolution rather than to
 * the router as a whole. `CreateRouterOptions.context` is evaluated once per
 * router, so these cannot come from there — `routeResolver.ts` passes them
 * alongside `pathname` on each `resolve()` call instead.
 */
export interface RouteResolutionContext {
    /**
     * This router's own {@link RouterHandle} (`goTo`, `getPath`, `isActive`, …).
     *
     * Not to be confused with `context.router`, which `universal-router` sets
     * to its own `UniversalRouter` instance — that name is taken, hence the
     * separate one here.
     */
    ownerRouter: RouterHandle;
    /**
     * Registers a disposer for whatever this resolution set up, run when the
     * route it belongs to leaves the screen — see
     * {@link import('./middlewares.js').MiddlewareEffect} for the exact
     * guarantee and `RouterState.commitCleanups()` for how it is timed.
     */
    onCleanup: (dispose: () => void) => void;
}

/**
 * The concrete payload a resolved route produces — what `universal-router`
 * actions return and what the router renders. Exposed to middlewares via
 * {@link RouteMiddleware} so they can inspect or rewrite it before passing
 * through (`next()`), or construct one to take over rendering.
 *
 * `component` is unresolved on purpose: a route's action no longer awaits it,
 * so a middleware replacing it is free to hand back either an eager component
 * or a `lazyComponent()` loader. The router resolves it once, alongside the
 * rest of the matched route's node chain.
 */
export interface RouteResultBody {
    component: RouteComponentOrLoader;
    context: HawkiRouteContext;
    params: RouteParams;
}

/**
 * HAWKI's fixed {@link RouteResultBody} instantiation of `universal-router`'s
 * `RouteResult<T>` — every compiled route's action resolves to this shape,
 * and `UniversalRouter<RouteResultBody>` is parameterised with it.
 */
export type RouteResult = URRouteResult<RouteResultBody>;

/** Optional extras for a single route registration. */
export interface RouteOptions<TMeta extends RouteMeta = RouteMeta> {
    /** Unique route name, forwarded to `universal-router` so the route can be looked up / have its URL generated. Not validated for uniqueness by the registrar. */
    name?: string;
    /** Middlewares wrapping this route; the first entry becomes the outermost guard. */
    middlewares?: RouteMiddleware[];
    /**
     * Per-route opt-out from the registrar's global middlewares (see
     * {@link RouteRegistrar.addGlobalMiddleware}). `true` skips all of them;
     * a key or array of keys skips only the named ones (e.g.
     * `withoutGlobalMiddlewares: 'auth'` on a public route). Keys are
     * type-checked against the `GlobalMiddlewares` interface augmented by
     * each contributing extension.
     */
    withoutGlobalMiddlewares?: boolean | keyof GlobalMiddlewares | Array<keyof GlobalMiddlewares>;
    /**
     * Layout wrapping this route's page, rendered *inside* the layouts of the
     * groups the route sits in. Prefer {@link lazyLayout} so the layout stays
     * out of the initial bundle.
     */
    layout?: RouteLayout;
    /**
     * {@link layout}, imported only once this route is actually resolved — the
     * layout counterpart of {@link RouteRegistrar.lazyRoute}. Declaring both
     * this and `layout` throws when the registrar builds.
     *
     * @example
     * registrar.lazyRoute('/users', loader, {
     *     lazyLayout: async () => (await import('./AdminLayout.svelte')).default
     * });
     */
    lazyLayout?: RouteLayoutLoader;
    /**
     * Data handed to the page and all its layouts via the `route` prop's
     * `meta` field. Type it by passing the schema's inferred type as `TMeta`, e.g.
     * `registrar.lazyRoute<ChatMeta>('/', loader, {meta: {title: 'Chat'}})`.
     */
    meta?: TMeta;
    /**
     * Makes the route match its path *and everything below it* — a `/files`
     * catch-all also answers `/files/a/b/c`. Matching stays segment-aware, so
     * `/filesX` is still a miss.
     *
     * Catch-alls are always emitted last by {@link build}, no matter where they
     * were registered, because a catch-all shadows every route after it.
     * Register one with path `/` inside a {@link group} to catch that group's
     * subtree only.
     *
     * The matched remainder is *not* exposed as a param — use a wildcard in the
     * path instead if you need it (`'/files/*rest'` yields
     * `params.rest === ['a', 'b', 'c']`), keeping in mind that a wildcard needs
     * at least one segment and therefore does not match the bare `/files`.
     */
    catchAll?: boolean;
    /**
     * Params, data loader and cache key for this route's page, built with
     * `configurePage()` — see `routeConfig.ts`.
     *
     * Takes precedence over a `config` exported from the component's own
     * module, so the same component can be registered on several routes and
     * still be given a different dataset here. It replaces that config
     * wholesale rather than merging into it — see `nodes.ts`'s
     * `resolveNodeConfig()` for why. Declaring both warns in dev.
     *
     * This is also the only way to configure a component that is not
     * resolvable as a module (a loader written as `async () => (await
     * import('./X.svelte')).default` throws the module namespace away before
     * `resolveComponentModule()` ever sees it, so it cannot export one).
     */
    config?: AnyRouteConfig;
    /** Same, for this route's own `layout` — built with `configureLayout()`. Same precedence rule as {@link config}. */
    layoutConfig?: AnyRouteConfig;
}

/** A route registration as stored in the registrar: the user-supplied {@link RouteOptions} plus the path and component it was registered with. */
export interface RegisteredRouteOptions extends RouteOptions {
    path: string;
    component: RouteComponentOrLoader;
}

/** Optional extras for a route group registration. */
export interface RouteGroupOptions {
    /**
     * Name of the group, which lets `RouterHandle.isRouteActive()` light up a
     * whole section while any route inside it is rendered.
     *
     * Not meant for linking: a group has no `action`, so navigating to it
     * directly falls through to its children and 404s unless one of them
     * matches the empty remainder.
     */
    name?: string;
    /** Middlewares wrapping the whole group; they run before any child route of the group. */
    middlewares?: RouteMiddleware[];
    /**
     * Layout wrapping every route inside this group. Stays mounted while
     * navigating between the group's routes, so it can hold sidebar state,
     * scroll position or transitions. Prefer {@link lazyLayout} so the layout
     * stays out of the initial bundle.
     *
     * Groups carry no `meta`: meta is the matched route's, and a group layout
     * simply sees the meta of whichever route is currently open inside it.
     */
    layout?: RouteLayout;
    /**
     * {@link layout}, imported only once a route inside this group is actually
     * resolved. Declaring both this and `layout` throws when the registrar
     * builds.
     *
     * @example
     * registrar.group('/admin', (admin) => { ... }, {
     *     lazyLayout: async () => (await import('./AdminLayout.svelte')).default
     * });
     */
    lazyLayout?: RouteLayoutLoader;
    /**
     * Params, data loader and cache key for this group's `layout`, built with
     * `configureLayout()`. Same precedence rule as {@link RouteOptions.config}.
     */
    layoutConfig?: AnyRouteConfig;
}

/** A group registration as stored in the registrar: the user-supplied {@link RouteGroupOptions} plus the group's path prefix and its children callback. */
export interface RegisteredRouteGroupOptions extends RouteGroupOptions {
    path: string | undefined;
    children: (registrar: RouteRegistrar) => void;
}

/**
 * Mints ids for {@link RouteNode}s. Module-level, not a `RouteRegistrar`
 * instance field: {@link RouteRegistrar.group} builds each nested group with
 * a *fresh* `RouteRegistrar`, so a per-instance counter would hand out
 * colliding ids across groups — only a counter shared by every registrar
 * guarantees uniqueness across the whole compiled tree.
 */
let nodeIdCounter = 0;

/**
 * Stamps a {@link RouteNode}. The counter alone guarantees a unique id;
 * `path` and `kind` are folded in purely so the id stays readable in a debug
 * dump (e.g. `n42#/admin/users:page`).
 */
function buildRouteNode(kind: RouteNodeKind, componentOrLoader: ComponentOrLoader<any>, path: string, name: string | undefined, configOption?: AnyRouteConfig): RouteNode {
    return {
        id: `n${++nodeIdCounter}#${path}:${kind}`,
        kind,
        componentOrLoader,
        routeName: name,
        routePath: path,
        configOption
    };
}

/**
 * Collects route registrations and compiles them into a `universal-router`
 * route tree.
 *
 * This is the registrar half of the extension+registrar pattern: the single
 * instance owned by {@link RoutingExtension} is handed to every plugin's
 * `routes()` hook and every module's `routes()` hook during the extension's
 * `init()`. Nobody registers routes directly on the router — they describe
 * routes here, and {@link build} converts the collected description into the
 * `Route[]` the router is constructed from.
 *
 * Nesting works through {@link group}: a group creates a *fresh, isolated*
 * `RouteRegistrar` for its children, so paths only have to be unique within
 * their own level and the group's path acts as prefix for everything inside.
 * Middlewares (per route or per group) are not stored on the route itself but
 * materialised as wrapping parent routes by {@link buildMiddlewareStack}.
 *
 * Registration order matters: `universal-router` walks the tree in definition
 * order and takes the first match whose action returns a value. {@link build}
 * emits all plain routes first, then all groups.
 *
 * @example
 * // Inside a plugin's or module's `routes()` hook:
 * public routes(registrar: RouteRegistrar) {
 *     registrar.lazyRoute('/', async () => (await import('./pages/ChatIndex.svelte')).default);
 *     registrar.group('/admin', (admin) => {
 *         admin.route('/users', UserListPage, {name: 'admin.users'});
 *     }, {middlewares: [requireAdmin]});
 * }
 */
export class RouteRegistrar {
    private readonly routes = new Map<string, RegisteredRouteOptions>();
    private readonly groups = new Map<string, RegisteredRouteGroupOptions>();
    /**
     * Middlewares attached to every route on this registrar (and on its
     * nested registrars — the array is shared by reference, see
     * {@link createNestedRegistrar}). Unlike per-route `middlewares`, these
     * do not apply to groups: a group has no page of its own, so a global
     * guard would only run once one of its child routes matched anyway.
     */
    private globalMiddlewares: { middleware: RouteMiddleware, key: keyof GlobalMiddlewares }[] = [];

    /**
     * Adds a middleware that runs in front of every route built from this
     * registrar, keyed by a `GlobalMiddlewares` slot so routes can opt out
     * individually through {@link RouteOptions.withoutGlobalMiddlewares}.
     * Order of registration is the order of execution; a later call with an
     * already-used `key` adds a duplicate rather than replacing it.
     */
    public addGlobalMiddleware(key: keyof GlobalMiddlewares, middleware: RouteMiddleware) {
        this.globalMiddlewares.push({key, middleware});
        return this;
    }

    /** Removes the first global middleware registered under `key`. No-op if none matches. */
    public removeGlobalMiddleware(key: keyof GlobalMiddlewares) {
        const index = this.globalMiddlewares.findIndex(mw => mw.key === key);
        if (index !== -1) {
            this.globalMiddlewares.splice(index, 1);
        }
        return this;
    }

    /**
     * Registers a route that renders an already-imported component.
     * Throws if a route with the same `path` exists on this registrar level.
     *
     * Prefer {@link lazyRoute} for page components so they stay out of the
     * initial bundle.
     */
    public route<TMeta extends RouteMeta = RouteMeta>(path: string, component: RouteComponent, options?: RouteOptions<TMeta>) {
        if (this.routes.has(path)) {
            throw new Error(`Route with path "${path}" is already registered.`);
        }
        this.routes.set(path, {path, component, ...options});
        return this;
    }

    /**
     * Registers a route whose component is imported only when the route is
     * actually resolved. Throws if a route with the same `path` exists on this
     * registrar level.
     *
     * The given loader is tagged in place with `type: 'lazy_route'` so
     * `lazyComponent.ts`'s `isLazyComponentLoader()` can distinguish it from
     * an eager component (both are plain functions at runtime).
     *
     * @example
     * registrar.lazyRoute('/', async () => (await import('./pages/ChatIndex.svelte')).default);
     */
    public lazyRoute<TMeta extends RouteMeta = RouteMeta>(path: string, loader: RouteComponentLoader, optionsOrName?: RouteOptions<TMeta> | string) {
        if (this.routes.has(path)) {
            throw new Error(`Route with path "${path}" is already registered.`);
        }
        if (typeof optionsOrName === 'string') {
            optionsOrName = {name: optionsOrName} as RouteOptions<TMeta>;
        }
        this.routes.set(path, {path, component: lazyComponent(loader), ...optionsOrName});
        return this;
    }

    /**
     * Registers a group of routes under a common `path` prefix. The callback
     * receives a nested, empty {@link RouteRegistrar} when the group is built
     * (not immediately), so child paths are relative to the group path.
     *
     * Registering the same group `path` twice does not throw: the callbacks are
     * chained instead, which lets several plugins/modules contribute routes to
     * the same prefix.
     *
     * @example
     * registrar.group('/admin', (admin) => {
     *     admin.route('/users', UserListPage);
     * }, {middlewares: [requireAdmin]});
     */
    public group(path: string, callback: RouteRegistrationCallback, options?: RouteGroupOptions) {
        if (this.groups.has(path)) {
            const existingGroup = this.groups.get(path)!;
            existingGroup.children = (registrar) => {
                existingGroup.children(registrar);
                callback(registrar);
            };
            return this;
        }
        this.groups.set(path, {path, children: callback, ...options});
        return this;
    }

    /**
     * Appends a middleware to an already registered route, so foreign code can
     * guard a route it does not own. Throws if no route with that `path` is
     * registered on this registrar level — meaning the owning plugin/module
     * must have run its `routes()` hook before this call (plugins run before
     * modules, see {@link RoutingExtension.init}).
     */
    public addMiddlewareToRoute(path: string, middleware: RouteMiddleware) {
        const route = this.routes.get(path);
        if (!route) {
            throw new Error(`Route with path "${path}" is not registered.`);
        }
        if (!route.middlewares) {
            route.middlewares = [];
        }
        route.middlewares.push(middleware);
        return this;
    }

    /**
     * Appends a middleware to an already registered route group, guarding every
     * route inside it. Throws if no group with that `path` is registered on
     * this registrar level.
     */
    public addMiddlewareToGroup(path: string, middleware: RouteMiddleware) {
        const group = this.groups.get(path);
        if (!group) {
            throw new Error(`Route group with path "${path}" is not registered.`);
        }
        if (!group.middlewares) {
            group.middlewares = [];
        }
        group.middlewares.push(middleware);
        return this;
    }

    /**
     * Builds the nested registrar a {@link group} uses for its children.
     *
     * Shares the parent's `globalMiddlewares` array *by reference* so a
     * plugin/module that registers a global guard from inside a group still
     * affects the whole router — the alternative (each level getting its own
     * list) would silently scope the guard to that group's subtree.
     */
    public createNestedRegistrar() {
        const nestedRegistrar = new RouteRegistrar();
        nestedRegistrar.globalMiddlewares = this.globalMiddlewares;
        return nestedRegistrar;
    }

    /**
     * Compiles everything registered so far into the `Route[]` that
     * {@link RoutingExtension} feeds to `UniversalRouter`: first all plain
     * routes (in registration order), then all groups (in registration order),
     * and finally all {@link RouteOptions.catchAll} routes — each already
     * wrapped in its middleware stack.
     *
     * Catch-alls are pulled to the back regardless of when they were
     * registered: they match a whole subtree, so anywhere else in the list they
     * would shadow every route behind them — including *all* groups, which
     * already sort after plain routes. Their order relative to each other is
     * preserved.
     *
     * Called once per registrar — the root one by `RoutingExtension.init()`,
     * nested ones by {@link buildRouteGroupFromOptions}. The same `renderer` is
     * passed down into every group.
     */
    public build() {
        const builtRoutes: Route<RouteResultBody>[] = [];
        const builtCatchAllRoutes: Route<RouteResultBody>[] = [];
        this.routes.forEach((routeOptions) => {
            const builtRoute = this.buildRouteFromOptions(routeOptions);
            (routeOptions.catchAll ? builtCatchAllRoutes : builtRoutes).push(builtRoute);
        });

        for (const [_, groupOptions] of this.groups.entries()) {
            const builtGroup = this.buildRouteGroupFromOptions(groupOptions);
            builtRoutes.push(builtGroup);
        }

        return [...builtRoutes, ...builtCatchAllRoutes];
    }

    private buildRouteFromOptions(options: RegisteredRouteOptions): Route {
        const path = options.path.replace(/\/$/, ''); // Strip trailing slash for consistency
        const layout = resolveLayoutOption(options.layout, options.lazyLayout, `Route "${options.path}"`);

        // `meta` and `layout` sit on the inner route on purpose: middleware
        // wrappers must stay transparent, and the layout chain is collected by
        // walking up from whichever route actually matched.
        const innerRoute: HawkiRoute = {
            name: options.name,
            path,
            // The action no longer resolves the component: it hands back the
            // unresolved `ComponentOrLoader` and lets the router resolve the
            // whole node chain (this page plus its layouts) in one place, so
            // it can run every resolution in parallel instead of one route
            // at a time. This also keeps the action synchronous, which
            // `universal-router` is happy with.
            action: (ctx, params) => ({component: options.component, context: ctx, params}),
            meta: options.meta,
            layout,
            nodes: {
                page: buildRouteNode('page', options.component, path, options.name, options.config),
                layout: layout ? buildRouteNode('layout', layout, path, options.name, options.layoutConfig) : undefined
            },
            // An *empty* children list is what turns this into a catch-all:
            // `universal-router` derives `end: !route.children` for its path
            // matcher, so having children (even none) switches the route to
            // prefix matching — and with nothing to descend into, its own
            // action answers the whole subtree.
            children: options.catchAll ? [] : undefined
        };

        return buildRouteMiddlewareStack(innerRoute, this.globalMiddlewares, options);
    }

    /**
     * Builds a parent `Route` for a group: runs the group's children callback
     * against a fresh nested registrar, compiles that registrar into the
     * parent's `children`, and wraps the result in the group's middlewares.
     * The group route has no `action`, so `universal-router` falls through to
     * the children.
     */
    private buildRouteGroupFromOptions(options: RegisteredRouteGroupOptions) {
        const innerRegistrar = this.createNestedRegistrar();
        options.children(innerRegistrar);
        const innerRoutes = innerRegistrar.build();
        const layout = resolveLayoutOption(options.layout, options.lazyLayout, `Group "${options.path}"`);

        const groupRoute: HawkiRoute = {
            name: options.name,
            path: options.path,
            children: innerRoutes,
            layout,
            // A group has no page node — its route has no `action` of its own.
            nodes: layout
                ? {layout: buildRouteNode('layout', layout, options.path ?? '', options.name, options.layoutConfig)}
                : undefined
        };

        return buildMiddlewareStack(groupRoute, options);
    }
}
