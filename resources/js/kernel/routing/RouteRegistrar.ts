import {type Route, type RouteContext, type RouteParams, type RouteResult} from 'universal-router';
import {buildMiddlewareStack} from '$lib/kernel/routing/buildMiddlewareStack.js';
import type {Component} from 'svelte';

/** Signature of a callback that receives a {@link RouteRegistrar} to register routes on — used for plugin/module `routes()` hooks and for `registrar.group()` children. */
export type RouteRegistrationCallback = (registrar: RouteRegistrar) => void | Promise<void>;
/** A Svelte component used as the "page" of a route. */
export type RouteComponent = Component;
/** Lazily imports a route's page component, e.g. `async () => (await import('./pages/ChatIndex.svelte')).default`. */
export type RouteComponentLoader = (() => Promise<RouteComponent>);
/**
 * Either an eagerly imported page component or a loader for one.
 *
 * Because a Svelte 5 `Component` *is* a function, a loader cannot be told
 * apart from a component at runtime — hence {@link RouteRegistrar.lazyRoute}
 * tags loaders with the `type: 'lazy_route'` marker, which a
 * {@link RouteRenderer} can check to decide whether it has to `await` the
 * component first.
 */
export type RouteComponentOrLoader = RouteComponent | (RouteComponentLoader & { type: 'lazy_route' });
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
export type RouteRenderer<TResult = any> = (component: RouteComponentOrLoader, context: RouteContext, params: RouteParams) => RouteResult<TResult>;
/**
 * Guard that runs *before* the route (or route group) it is attached to.
 *
 * Return a `Component` to take over rendering and stop resolution (e.g. a
 * login or error page), or a nullish value to let the router continue into the
 * guarded route. See {@link buildMiddlewareStack} for how middlewares are
 * turned into wrapping routes, including the `null` vs. `undefined` nuance.
 */
export type RouteMiddleware = (context: RouteContext) => Promise<Component | undefined | null>;

/** Optional extras for a single route registration. */
export interface RouteOptions {
    /** Unique route name, forwarded to `universal-router` so the route can be looked up / have its URL generated. Not validated for uniqueness by the registrar. */
    name?: string;
    /** Middlewares wrapping this route; the first entry becomes the outermost guard. */
    middlewares?: RouteMiddleware[];
}

/** A route registration as stored in the registrar: the user-supplied {@link RouteOptions} plus the path and component it was registered with. */
export interface RegisteredRouteOptions extends RouteOptions {
    path: string;
    component: RouteComponentOrLoader;
}

/** Optional extras for a route group registration. */
export interface RouteGroupOptions {
    /** Middlewares wrapping the whole group; they run before any child route of the group. */
    middlewares?: RouteMiddleware[];
}

/** A group registration as stored in the registrar: the user-supplied {@link RouteGroupOptions} plus the group's path prefix and its children callback. */
export interface RegisteredRouteGroupOptions extends RouteGroupOptions {
    path: string | undefined;
    children: (registrar: RouteRegistrar) => void | Promise<void>;
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
    public route(path: string, component: RouteComponent, options?: RouteOptions) {
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
    public lazyRoute(path: string, loader: RouteComponentLoader, options?: RouteOptions) {
        if (this.routes.has(path)) {
            throw new Error(`Route with path "${path}" is already registered.`);
        }
        const lazyLoader = Object.assign(loader, {type: 'lazy_route'} as const);
        this.routes.set(path, {path, component: lazyLoader, ...options});
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
     * TODO(docs): On such a merge only the callbacks are combined — the
     * `options` (i.e. `middlewares`) of the second `group()` call are silently
     * dropped, and the first call's middlewares keep applying to the routes
     * added by the second one. Intentional, or should the middleware lists be
     * merged / an error be thrown?
     *
     * @example
     * registrar.group('/admin', (admin) => {
     *     admin.route('/users', UserListPage);
     * }, {middlewares: [requireAdmin]});
     */
    public group(path: string, callback: RouteRegistrationCallback, options?: RouteGroupOptions) {
        if (this.groups.has(path)) {
            const existingGroup = this.groups.get(path)!;
            existingGroup.children = async (registrar) => {
                await existingGroup.children(registrar);
                await callback(registrar);
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
     * each already wrapped in its middleware stack.
     *
     * Called once per registrar — the root one by `RoutingExtension.init()`,
     * nested ones by {@link buildRouteGroupFromOptions}. The same `renderer` is
     * passed down into every group.
     */
    public async build(renderer: RouteRenderer) {
        const builtRoutes: Route[] = [];
        this.routes.forEach((routeOptions) => {
            builtRoutes.push(this.buildRouteFromOptions(routeOptions, renderer));
        });

        for (const [_, groupOptions] of this.groups.entries()) {
            const builtGroup = await this.buildRouteGroupFromOptions(groupOptions, renderer);
            builtRoutes.push(builtGroup);
        }

        return builtRoutes;
    }

    /** Builds a leaf `Route` whose `action` delegates to the {@link RouteRenderer}, then wraps it in this route's middlewares. */
    private buildRouteFromOptions(options: RegisteredRouteOptions, renderer: RouteRenderer): Route {
        const innerRoute: Route = {
            name: options.name,
            path: options.path,
            action: (ctx, params) => renderer(options.component, ctx, params)
        };

        return buildMiddlewareStack(innerRoute, options);
    }

    /**
     * Builds a parent `Route` for a group: runs the group's children callback
     * against a fresh nested registrar, compiles that registrar into the
     * parent's `children`, and wraps the result in the group's middlewares.
     * The group route has no `action`, so `universal-router` falls through to
     * the children.
     *
     * TODO(docs): `options.children(...)` is invoked without `await`, although
     * {@link RegisteredRouteGroupOptions.children} may return a Promise (the
     * module wrapper in `kernel/modules/moduleRegistrar.ts` creates an `async`
     * callback). Async child registrations would therefore land after
     * `innerRegistrar.build()` has already run and be lost — is the callback
     * meant to be synchronous, or is the missing `await` a bug?
     */
    private async buildRouteGroupFromOptions(options: RegisteredRouteGroupOptions, renderer: RouteRenderer): Promise<Route> {
        const innerRegistrar = new RouteRegistrar();
        options.children(innerRegistrar);

        const innerRoutes = await innerRegistrar.build(renderer);

        const innerRoute: Route = {
            path: options.path,
            children: innerRoutes
        };

        return buildMiddlewareStack(innerRoute, options);
    }
}
