import {type Route, type RouteContext, type RouteParams, type RouteResult as URRouteResult} from 'universal-router';
import {buildMiddlewareStack} from '$lib/components/ui/routing/logistics/buildMiddlewareStack.js';
import {type ComponentLoader, type ComponentOrLoader, isLazyComponentLoader, lazyComponent, resolveComponent} from '$lib/components/ui/routing/logistics/lazyComponent.js';
import type {Component, Snippet} from 'svelte';

/** Signature of a callback that receives a {@link RouteRegistrar} to register routes on — used for plugin/module `routes()` hooks and for `registrar.group()` children. */
export type RouteRegistrationCallback = (registrar: RouteRegistrar) => void;

export interface RouteComponentProps {
    context: RouteContext;
    params: any;
}

/** A Svelte component used as the "page" of a route. */
export type RouteComponent = Component<RouteComponentProps>;
/** Lazily imports a route's page component, e.g. `async () => (await import('./pages/ChatIndex.svelte')).default`. */
export type RouteComponentLoader = ComponentLoader<RouteComponent>;
/**
 * Either an eagerly imported page component or a loader for one.
 *
 * Because a Svelte 5 `Component` *is* a function, a loader cannot be told
 * apart from a component at runtime — hence {@link RouteRegistrar.lazyRoute}
 * tags loaders with the `type: 'lazy_route'` marker, which a
 * {@link RouteRenderer} can check to decide whether it has to `await` the
 * component first.
 */
export type RouteComponentOrLoader = ComponentOrLoader<RouteComponent>;

/**
 * A component that wraps a route's page (and any layout below it) in its
 * `children` snippet — the router's equivalent of a nested layout.
 *
 * Layouts receive no props: everything they need comes from the router context
 * (`useRouter()`, `useRouteMeta()`), which is what lets a layout stay mounted
 * while the page inside it changes.
 */
export type RouteLayout = Component<{ children: Snippet }>;
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
 * Meta belongs to the *route*, not to the layouts around it: it is read once
 * when the route resolves and is then visible to the page and every layout
 * wrapping it, via `useRouteMeta()`. Router-internal settings (such as
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
}
/**
 * Turns a matched route into whatever the app renders. Installed as the
 * `action` of every compiled route, so it receives the `universal-router`
 * context (`pathname`, `baseUrl`, `route`, `next`, ...) alongside the matched
 * URL params.
 *
 * Beware of `universal-router`'s resolve semantics: returning `undefined`
 * makes the router continue with the next matching route, returning `null`
 * additionally skips the rest of the matched route's subtree, and if nothing
 * ever returns a value `resolve()` rejects with a 404 `Route not found` error.
 *
 * The concrete implementation is injected into {@link RoutingExtension}; see
 * `createDefaultRouteRenderer()` in `routeRenderer.js`.
 */
export type RouteRenderer<TResult = any> = (component: RouteComponentOrLoader, context: RouteContext, params: RouteParams) => URRouteResult<TResult>;
/**
 * Guard that runs *before* the route (or route group) it is attached to.
 *
 * Modeled on a classic PHP-style middleware stack: the callable receives the
 * route context and a `next` callback that resumes the guarded route. Only
 * the three return shapes below are meaningful — HAWKI does not expose
 * `universal-router`'s raw `null` vs. `undefined` action distinction to
 * middleware authors; the wrapper in {@link buildMiddlewareStack} normalises
 * them so callers never have to learn that nuance.
 *
 * - **Return a {@link RouteResultBody}** to take over rendering and stop
 *   resolution. The body carries `component`, `context` and `params`, so a
 *   middleware can both replace the page *and* rewrite the params the page
 *   will receive — e.g. inject a derived value, normalise a slug — before the
 *   router picks them up.
 * - **`return await next()`** to pass through to the guarded route. Whatever
 *   the guarded route resolves to is handed back unchanged.
 * - **Return nothing (or throw)** to mark the guarded route as unreachable:
 *   `universal-router` skips the route's subtree, falls through to the next
 *   sibling, and 404s if nothing else matches — the permission-deny signal.
 */
export type RouteMiddleware = (
    context: RouteContext,
    next: () => Promise<RouteResultBody | undefined>
) => Promise<RouteResultBody | undefined>;

/**
 * The concrete payload a resolved route produces — what `universal-router`
 * actions return and what the router renders. Exposed to middlewares via
 * {@link RouteMiddleware} so they can inspect or rewrite it before passing
 * through (`next()`), or construct one to take over rendering.
 */
export interface RouteResultBody {
    component: RouteComponent;
    context: RouteContext;
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
     * Layout wrapping this route's page, rendered *inside* the layouts of the
     * groups the route sits in. Use `lazyComponent()` for a loader.
     */
    layout?: RouteLayoutOrLoader;
    /**
     * Data handed to the page and all its layouts through `useRouteMeta()`.
     * Type it by passing the schema's inferred type as `TMeta`, e.g.
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
     * scroll position or transitions. Use `lazyComponent()` for a loader.
     *
     * Groups carry no `meta`: meta is the matched route's, and a group layout
     * simply sees the meta of whichever route is currently open inside it.
     */
    layout?: RouteLayoutOrLoader;
}

/** A group registration as stored in the registrar: the user-supplied {@link RouteGroupOptions} plus the group's path prefix and its children callback. */
export interface RegisteredRouteGroupOptions extends RouteGroupOptions {
    path: string | undefined;
    children: (registrar: RouteRegistrar) => void;
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
     * The given loader is tagged in place with `type: 'lazy_route'` so the
     * {@link RouteRenderer} can distinguish it from an eager component (both
     * are plain functions at runtime).
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

    private isLazyRouteLoader(obj: any): obj is RouteComponentLoader {
        return isLazyComponentLoader(obj);
    }

    private buildRouteFromOptions(options: RegisteredRouteOptions): Route {
        const renderableRouteResolver = (context: RouteContext, params: RouteParams): RouteResult => {
            if (!this.isLazyRouteLoader(options.component)) {
                return {component: options.component as RouteComponent, context, params};
            }

            return resolveComponent(options.component, `route "${options.path}"`)
                .then((component) => ({component, context, params}));
        };

        // `meta` and `layout` sit on the inner route on purpose: middleware
        // wrappers must stay transparent, and the layout chain is collected by
        // walking up from whichever route actually matched.
        const innerRoute: HawkiRoute = {
            name: options.name,
            path: options.path.replace(/\/$/, ''), // Strip trailing slash for consistency,
            action: (ctx, params) =>
                renderableRouteResolver(ctx, params),
            meta: options.meta,
            layout: options.layout,
            // An *empty* children list is what turns this into a catch-all:
            // `universal-router` derives `end: !route.children` for its path
            // matcher, so having children (even none) switches the route to
            // prefix matching — and with nothing to descend into, its own
            // action answers the whole subtree.
            children: options.catchAll ? [] : undefined
        };

        return buildMiddlewareStack(innerRoute, options);
    }

    /**
     * Builds a parent `Route` for a group: runs the group's children callback
     * against a fresh nested registrar, compiles that registrar into the
     * parent's `children`, and wraps the result in the group's middlewares.
     * The group route has no `action`, so `universal-router` falls through to
     * the children.
     */
    private buildRouteGroupFromOptions(options: RegisteredRouteGroupOptions) {
        const innerRegistrar = new RouteRegistrar();
        options.children(innerRegistrar);
        const innerRoutes = innerRegistrar.build();

        const groupRoute: HawkiRoute = {
            name: options.name,
            path: options.path,
            children: innerRoutes,
            layout: options.layout
        };

        return buildMiddlewareStack(groupRoute, options);
    }
}
