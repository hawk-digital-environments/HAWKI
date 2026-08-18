import type {HawkiApp, HawkiAppExtension} from '$lib/kernel/HawkiApp.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';
import type {RouterHandle} from '$lib/components/ui/routing/logistics/router.svelte.js';
import type {HawkiRoute, RouteMiddleware} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {ApiTransportError} from '$lib/kernel/api/errors.js';

/**
 * Name of the SPA login route the guard redirects to *if* one is registered.
 * As long as no route carries this name, the guard falls back to a hard
 * redirect to the legacy login page (`UriBuilder.loginUri()`). A future SPA
 * login page only has to register itself under this name (with
 * {@link PUBLIC_ROUTE_META_KEY} set) and the guard switches to client-side
 * navigation automatically.
 */
export const LOGIN_ROUTE_NAME = 'login';

/**
 * Route meta flag marking a route as reachable without authentication
 * (`meta: {public: true}` — e.g. a future SPA login or registration page).
 * The guard never redirects away from a public route.
 */
export const PUBLIC_ROUTE_META_KEY = 'public';

/**
 * How often the session state is re-checked against the backend while the tab is visible.
 * Checks are skipped while the tab is hidden; instead, a check runs immediately
 * (if the last one is stale) as soon as the tab becomes visible again.
 */
const CHECK_INTERVAL_MS = 60_000;

/**
 * App extension that keeps unauthenticated visitors out of the SPA and
 * notices when a session dies while the app is open. Two mechanisms:
 *
 * 1. A global route middleware (see {@link RouteRegistrar.addGlobalMiddleware})
 *    that denies every non-public route to guests — it covers the very first
 *    resolution on boot as well as every client-side navigation, *before* the
 *    protected page renders.
 * 2. A background poller that re-fetches the unauthenticated
 *    `connections/hawki` resource (via `app.reloadConnection()`, so
 *    `app.connection` stays fresh for the middleware) and redirects when the
 *    session expired while the user was sitting on a page. Transient network
 *    failures are ignored so a flaky connection never logs anyone out, and
 *    users in the registration flow (`internal_registering_user`) are left alone.
 *
 * Today the redirect is a hard `window.location.replace()` to the legacy
 * login page. Once the login lives inside the SPA, nothing here needs to
 * change: register the route as
 * `registrar.route('/login', LoginPage, {name: LOGIN_ROUTE_NAME, meta: {[PUBLIC_ROUTE_META_KEY]: true}})`
 * and the guard navigates client-side instead. After a successful in-SPA
 * login, call `await app.reloadConnection()` followed by `app.router.reload()`
 * to re-run the middleware with the fresh session state.
 */
export class SessionGuardExtension implements HawkiAppExtension {
    private app: HawkiApp | null = null;
    private lastCheckAt = 0;
    private checkRunning = false;
    private redirecting = false;

    /**
     * Resolves the guarded route first and only then decides: the matched
     * route's meta is what tells us whether the target is public, and it is
     * only known after `next()`. Denying returns nothing, which 404s the
     * protected page while the redirect (hard or client-side) takes over.
     */
    private readonly guardRoute: RouteMiddleware = async (_context, next) => {
        const result = await next();
        if (!result || this.isPublicRoute(result.context.route as HawkiRoute | undefined)) {
            return result;
        }
        if (this.app && this.isLoggedOut(this.app.connection)) {
            this.redirectToLogin();
            return undefined;
        }
        return result;
    };

    public provideProperties(): Record<string, any> {
        return {};
    }

    public ready(app: HawkiApp, bootstrapper: Bootstrapper): void {
        this.app = app;

        // ready() runs after all routes have been collected but before the
        // router is compiled on the `late` stage, so the middleware ends up
        // wrapping every route any plugin/module registered.
        app.routeRegistrar.addGlobalMiddleware(this.guardRoute);

        bootstrapper.onStagePassed('finalization', () => {
            // The bootstrap script also runs on legacy pages (login, register, ...)
            // where the SPA shell never mounts and no route ever resolves — polling
            // there would redirect the login page onto itself in a reload loop.
            if (!app.isMounted) {
                return;
            }

            this.lastCheckAt = Date.now();
            window.setInterval(() => this.check(), CHECK_INTERVAL_MS);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && Date.now() - this.lastCheckAt >= CHECK_INTERVAL_MS) {
                    this.check();
                }
            });
        });
    }

    private isLoggedOut(connection: Connection): boolean {
        return connection.type === 'internal';
    }

    private isPublicRoute(route: HawkiRoute | null | undefined): boolean {
        return route?.meta?.[PUBLIC_ROUTE_META_KEY] === true;
    }

    /** Whether the route currently rendered by the app router is public. */
    private isOnPublicRoute(): boolean {
        return this.app?.router.meta?.[PUBLIC_ROUTE_META_KEY] === true;
    }

    private async check(): Promise<void> {
        if (!this.app || this.redirecting || this.checkRunning || document.hidden) {
            return;
        }

        this.checkRunning = true;
        try {
            const connection = await this.app.reloadConnection();
            this.lastCheckAt = Date.now();
            if (this.isLoggedOut(connection) && !this.isOnPublicRoute()) {
                this.redirectToLogin();
            }
        } catch (e) {
            if (e instanceof ApiTransportError && (e.status === 401 || e.status === 419)) {
                if (!this.isOnPublicRoute()) {
                    this.redirectToLogin();
                }
            }
            // Anything else (network hiccup, server error) is ignored; the next tick retries.
        } finally {
            this.checkRunning = false;
        }
    }

    /**
     * Sends the user to the login page: client-side when a route named
     * {@link LOGIN_ROUTE_NAME} exists in the SPA, otherwise a hard redirect to
     * the legacy login page. Only the hard redirect latches `redirecting` —
     * an in-SPA login page must leave the guard alive for after the login.
     */
    private redirectToLogin(): void {
        if (!this.app || this.redirecting) {
            return;
        }

        const router = this.app.router;
        if (this.hasSpaLoginRoute(router)) {
            if (!router.isRouteActive(LOGIN_ROUTE_NAME)) {
                void router.goToRoute(LOGIN_ROUTE_NAME);
            }
            return;
        }

        this.redirecting = true;
        window.location.replace(this.app.uriBuilder.loginUri());
    }

    private hasSpaLoginRoute(router: RouterHandle): boolean {
        try {
            // The URL generator throws for unknown route names — the only way
            // to probe for a named route without walking the route tree.
            router.getPath(LOGIN_ROUTE_NAME);
            return true;
        } catch {
            return false;
        }
    }
}
