import {declareEffectfulMiddleware} from '$lib/components/ui/routing/index.js';
import type {Connection, InternalAuthenticatedConnection} from '$lib/app/schemas/resources/connections.schema.js';

// @todo Redirect through the router (`redirect('auth.login')` in the
// middleware, `app.router.goToRoute('auth.login')` from the background
// refresh) once the new frontend has an `auth.login` route. Until then this
// is a hard, router-independent redirect to the site root.
const LOGIN_URL = '/';

/** Hard-redirects to the login page and aborts the current work. Always throws — `window.location.assign` is async, so callers can't rely on the navigation having happened when it returns. */
function redirectToLogin(): never {
    window.location.assign(LOGIN_URL);
    // Navigation is asynchronous; stop the current resolution/refresh from
    // continuing in the meantime.
    throw new Error('Not authenticated, redirecting to login');
}

function isAuthenticated(connection: Connection | null): connection is InternalAuthenticatedConnection {
    return connection?.type === 'internal_authenticated';
}

/**
 * Global auth middleware: every route (see {@link RoutingExtension}) is
 * guarded unless it opts out via `withoutGlobalMiddlewares: ['auth']` (or
 * `true`).
 *
 * The guard body runs on each navigation; the effect subscribes for the
 * lifetime of the rendered route, so a background connection refresh that
 * drops the session (type changed away from `internal_authenticated`, or all
 * retries exhausted) throws the user out of the authenticated page without
 * waiting for the next navigation. Both paths use {@link redirectToLogin}
 * rather than a router `redirect()`, because the background refresh fires
 * outside a resolution and `redirect()` only works mid-resolution.
 */
export const authMiddleware = declareEffectfulMiddleware(
    async (ctx, next) => {
        if (isAuthenticated(ctx.app.connectionOrNull)) {
            return next();
        }

        redirectToLogin();
    },
    (ctx) => {
        const connectionChangedCleanup = ctx.app.events.async.on('connectionChanged', (connection) => {
            if (!isAuthenticated(connection)) {
                redirectToLogin();
            }
        });
        const refreshCleanup = ctx.app.events.async.on('connectionRefreshFailed', () => {
            redirectToLogin();
        });

        return () => {
            connectionChangedCleanup();
            refreshCleanup();
        };
    }
);
