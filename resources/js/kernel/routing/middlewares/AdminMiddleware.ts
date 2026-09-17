import {routeError, type RouteMiddleware} from '$lib/components/ui/routing/index.js';
import type {RouteMeta} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

export function adminMetaGuard(meta: RouteMeta): RouteMiddleware {
    return (ctx, next) => {
        if (meta.admin && !ctx.app.isAdmin) routeError(403, 'admin.forbidden');
        return next();
    };
}
