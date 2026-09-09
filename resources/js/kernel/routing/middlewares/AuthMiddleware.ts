import {type RouteMiddleware, redirect} from '$lib/components/ui/routing/index.js';
import type {RouteMeta} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {currentNext} from '$lib/kernel/auth/navigation.js';

export function authMetaGuards(meta: RouteMeta): RouteMiddleware {
    const access = meta.access ?? 'crypto-ready';
    if (!['public', 'server-session', 'crypto-ready'].includes(String(access))) {
        throw new Error(`Unknown route access: ${String(access)}`);
    }
    return async (ctx, next) => {
        if (access === 'public') return next();
        const connection = ctx.app.connectionOrNull;
        const destination = currentNext(ctx.app.router, ctx.pathname);
        if (!connection?.isAuthenticated) {
            redirect(connection?.type === 'internal_registering_user' ? 'auth.register' : 'auth.login', destination ? {next: destination} : undefined);
        }
        if (access === 'server-session') return next();
        if (connection.keychain_state === 'setup_required') redirect('auth.register', destination ? {next: destination} : undefined);
        if (connection.keychain_state === 'inconsistent') redirect('auth.inconsistent', destination ? {next: destination} : undefined);
        if (!ctx.app.cryptoReady) redirect('auth.handshake', destination ? {next: destination} : undefined);
        return next();
    };
}
