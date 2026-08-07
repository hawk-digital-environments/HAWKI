import {type Route, type RouteContext, type RouteParams, type RouteResult} from 'universal-router';
import {buildMiddlewareStack} from '$lib/kernel/routing/buildMiddlewareStack.js';
import type {Component} from 'svelte';

export type RouteRegistrationCallback = (registrar: RouteRegistrar) => void | Promise<void>;
export type RouteComponent = Component;
export type RouteComponentLoader = (() => Promise<RouteComponent>);
export type RouteComponentOrLoader = RouteComponent | (RouteComponentLoader & { type: 'lazy_route' });
export type RouteRenderer<TResult = any> = (component: RouteComponentOrLoader, context: RouteContext, params: RouteParams) => RouteResult<TResult>;
export type RouteMiddleware = (context: RouteContext) => Promise<Component | undefined | null>;

export interface RouteOptions {
    name?: string;
    middlewares?: RouteMiddleware[];
}

export interface RegisteredRouteOptions extends RouteOptions {
    path: string;
    component: RouteComponentOrLoader;
}

export interface RouteGroupOptions {
    middlewares?: RouteMiddleware[];
}

export interface RegisteredRouteGroupOptions extends RouteGroupOptions {
    path: string | undefined;
    children: (registrar: RouteRegistrar) => void | Promise<void>;
}

export class RouteRegistrar {
    private readonly routes = new Map<string, RegisteredRouteOptions>();
    private readonly groups = new Map<string, RegisteredRouteGroupOptions>();

    public route(path: string, component: RouteComponent, options?: RouteOptions) {
        if (this.routes.has(path)) {
            throw new Error(`Route with path "${path}" is already registered.`);
        }
        this.routes.set(path, {path, component, ...options});
        return this;
    }

    public lazyRoute(path: string, loader: RouteComponentLoader, options?: RouteOptions) {
        if (this.routes.has(path)) {
            throw new Error(`Route with path "${path}" is already registered.`);
        }
        const lazyLoader = Object.assign(loader, {type: 'lazy_route'} as const);
        this.routes.set(path, {path, component: lazyLoader, ...options});
        return this;
    }

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

    private buildRouteFromOptions(options: RegisteredRouteOptions, renderer: RouteRenderer): Route {
        const innerRoute: Route = {
            name: options.name,
            path: options.path,
            action: (ctx, params) => renderer(options.component, ctx, params)
        };

        return buildMiddlewareStack(innerRoute, options);
    }

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
