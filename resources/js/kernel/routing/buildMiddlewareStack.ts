import {type Route} from 'universal-router';
import type {RegisteredRouteGroupOptions, RegisteredRouteOptions, RouteMiddleware} from '$lib/kernel/routing/RouteRegistrar.js';

type GroupMiddlewareStackOptions = Omit<RegisteredRouteGroupOptions, 'children'>;
type RouteMiddlewareStackOptions = Omit<RegisteredRouteOptions, 'component' | 'isLazy'>;

function createMiddlewareRoute(
    middleware: RouteMiddleware,
    children: Route[]
): Route {
    return {
        path: '',
        action: (context) => middleware(context),
        children
    };
}

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
