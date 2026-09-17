import {routeError, type RouteMiddleware} from '$lib/components/ui/routing/index.js';
import type {RouteMeta} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {can} from '$lib/kernel/auth/permissions.js';

export function permissionMetaGuard(meta: RouteMeta): RouteMiddleware {
    return (ctx, next) => {
        if (meta.permission && !can(ctx.app.connectionOrNull, meta.permission)) routeError(403, 'admin.forbidden');
        return next();
    };
}
