import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {RegisteredRouteOptions, RouteMeta, RouteMiddleware, Router} from '$lib/components/ui/routing/index.js';

/** How often the connection is re-validated against the backend while the app is open. */
export const AUTH_CHECK_INTERVAL_MS = 15 * 60 * 1000;

// @todo Redirect through the router (`redirect('auth.login')` in the
// middleware, `app.router.goToRoute('auth.login')` from the background
// refresh) once the new frontend has an `auth.login` route. Until then this
// is a hard, router-independent redirect to the site root.
const LOGIN_URL = '/';

function redirectToLogin(): never {
    window.location.assign(LOGIN_URL);
    // Navigation is asynchronous; stop the current resolution/refresh from
    // continuing in the meantime.
    throw new Error('Not authenticated, redirecting to login');
}

/**
 * Route meta understood by the {@link AuthChecker}. Set `auth: false` on a
 * route to let unauthenticated users see it:
 * ```ts
 * registrar.lazyRoute('/login', loader, {meta: {auth: false}});
 * ```
 * Routes without the key require authentication.
 */
export interface AuthRouteMeta {
    auth?: boolean;
}

function requiresAuth(meta: RouteMeta | null | undefined): boolean {
    return (meta as AuthRouteMeta | null | undefined)?.auth !== false;
}

/**
 * Keeps the session honest on the client side.
 *
 * Two mechanisms, one policy: a user may only be on a route that requires
 * authentication while `app.connection` is an authenticated one; otherwise
 * they are sent to {@link LOGIN_URL}.
 *
 * - {@link forRoute} hands `RouteRegistrar` a middleware for each guarded
 *   route (see `RouteRegistrarOptions.routeMiddlewares`) — a route with
 *   `auth: false` gets none at all — so a navigation onto a guarded route is
 *   checked against the connection the app currently holds. No request is
 *   made here — the connection is refreshed by the second mechanism, not on
 *   every navigation.
 * - {@link install} refreshes the connection every
 *   {@link AUTH_CHECK_INTERVAL_MS} and whenever the tab regains focus (a
 *   background tab's timers are throttled, so refocus is what catches a
 *   session that expired while the user was away). If the refresh fails, or
 *   comes back unauthenticated while a guarded route is shown, the user is
 *   sent to the login target.
 */
export class AuthChecker {
    private app: HawkiApp | null = null;
    private getRouter: () => Router | null = () => null;
    private pendingRefresh: Promise<void> | null = null;

    /**
     * Builds the middleware for one route, or nothing if the route's meta
     * opts out. The decision is made here, at build time, because the
     * middleware itself only sees its wrapper route's context, not the
     * guarded route's `meta`.
     */
    public forRoute(route: RegisteredRouteOptions): RouteMiddleware[] {
        if (!requiresAuth(route.meta)) {
            return [];
        }
        return [
            async (context, next) => {
                if (!this.isAuthenticated(context.app)) {
                    redirectToLogin();
                }
                return next();
            }
        ];
    }

    /**
     * Starts the periodic and refocus refreshes. Returns a disposer.
     *
     * `getRouter` tells the background refresh whether a guarded route is on
     * screen. The kernel also boots on legacy pages (login, home, ...) where
     * no router renders anything — there a failed refresh must not redirect,
     * or the login page would reload itself on every focus change.
     */
    public install(app: HawkiApp, getRouter: () => Router | null): () => void {
        this.app = app;
        this.getRouter = getRouter;

        const onRefocus = () => {
            if (document.visibilityState === 'visible') {
                void this.refresh();
            }
        };
        const interval = window.setInterval(() => void this.refresh(), AUTH_CHECK_INTERVAL_MS);
        window.addEventListener('focus', onRefocus);
        document.addEventListener('visibilitychange', onRefocus);

        return () => {
            window.clearInterval(interval);
            window.removeEventListener('focus', onRefocus);
            document.removeEventListener('visibilitychange', onRefocus);
        };
    }

    private isAuthenticated(app: HawkiApp): boolean {
        return app.connection.type === 'internal_authenticated';
    }

    /** Refreshes the connection; concurrent callers (focus + visibilitychange fire together) share one request. */
    private refresh(): Promise<void> {
        if (!this.pendingRefresh) {
            this.pendingRefresh = this.doRefresh().finally(() => {
                this.pendingRefresh = null;
            });
        }
        return this.pendingRefresh;
    }

    private async doRefresh(): Promise<void> {
        const app = this.app;
        if (!app) {
            return;
        }

        let authenticated: boolean;
        try {
            await app.refreshConnection();
            authenticated = this.isAuthenticated(app);
        } catch (error) {
            console.warn('AuthChecker: refreshing the connection failed', error);
            authenticated = false;
        }

        if (!authenticated && this.isGuardedRouteShown()) {
            redirectToLogin();
        }
    }

    /** A route without meta counts as guarded; no rendered route at all (router absent, loading, 404, error) does not. */
    private isGuardedRouteShown(): boolean {
        const router = this.getRouter();
        return router?.route != null && requiresAuth(router.meta);
    }
}
