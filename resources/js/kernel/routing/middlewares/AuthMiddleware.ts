import {declareEffectfulMiddleware, redirect} from '$lib/components/ui/routing/index.js';
import type {RouteMeta} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {assignAuthPage, currentNext} from '$lib/kernel/auth/navigation.js';

export function authMetaGuards(meta: RouteMeta) {
    const access = meta.access ?? 'crypto-ready';
    if (!['public', 'server-session', 'crypto-ready'].includes(String(access))) {
        throw new Error(`Unknown route access: ${String(access)}`);
    }
    return declareEffectfulMiddleware(async (ctx, next) => {
        if (access === 'public') return next();
        const connection = ctx.app.connectionOrNull;
        const destination = currentNext(ctx.pathname);
        if (!connection?.isAuthenticated) {
            redirect(connection?.type === 'internal_registering_user' ? 'auth.register' : 'auth.login', destination ? {next: destination} : undefined);
        }
        if (access === 'server-session') return next();
        if (connection.keychain_state === 'setup_required') redirect('auth.register', destination ? {next: destination} : undefined);
        if (connection.keychain_state === 'inconsistent') redirect('auth.inconsistent', destination ? {next: destination} : undefined);
        if (!ctx.app.cryptoReady) redirect('auth.handshake', destination ? {next: destination} : undefined);
        return next();
    }, ctx => {
        if (access === 'public') return;
        return ctx.app.events.async.on('connectionChanged', connection => {
            if (ctx.app.logoutState !== 'idle') return;
            if (!connection.isAuthenticated) {
                assignAuthPage(connection.hasUserInfo ? 'register' : 'login');
            }
        });
    });
}

/** Default policy retained for consumers that explicitly import the middleware. */
export const authMiddleware = authMetaGuards({});
