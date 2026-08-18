/**
 * Control-flow signals a middleware or a `loadData` can raise to redirect the
 * user, or to fail the current resolution with an HTTP-style status. Both are
 * thrown `Error`s, caught by `router.svelte.ts`'s `runResolve()` — see there
 * for how a redirect re-enters resolution and how an HTTP error maps onto
 * `state: 'notFound'`/`'error'`.
 *
 * `import {redirect, routeError} from './signals.js'` is the entry point for
 * a middleware, which has no router instance to call a method on. A loader
 * gets the same behaviour for free via `RouteDataLoaderContext.redirect`/
 * `.error` (see `dataLoader.ts`), which just delegate to these.
 */
import type {UrlParams} from 'universal-router/generateUrls';
import type {RouteError} from 'universal-router';

/** Thrown to redirect the current resolution elsewhere. */
export class RouteRedirect extends Error {
    constructor(
        /**
         * A route name or a literal path, deliberately left unresolved: only
         * `runResolve()` knows the owning router and can turn it into a path
         * via that router's `getPath()`.
         */
        readonly target: string,
        readonly params?: UrlParams,
        /** Replaces the current history entry by default, so a redirect doesn't leave a dead back-button entry. */
        readonly replace: boolean = true
    ) {
        super(`Route redirect to "${target}"`);
    }
}

/**
 * Thrown to fail the current resolution with an HTTP-style status. `404`
 * lands on the router's `notFound` state, anything else on `error` — see
 * `runResolve()`'s handling.
 */
export class RouteHttpError extends Error {
    constructor(
        readonly status: number,
        message?: string
    ) {
        super(message ?? `Route failed with status ${status}`);
    }
}

/**
 * Wraps whatever `universal-router`'s `errorHandler` receives, tagging it
 * with `'notFound'` vs `'error'` so `runResolve()`'s `catch` block can pick
 * the right router state without re-inspecting the original error. Never
 * escapes this module — `runResolve()` unwraps `originalError` before
 * publishing it as {@link Router.error}.
 */
export class RouteResolutionError extends Error {
    constructor(
        public readonly originalError: Error | RouteError,
        public readonly type: 'notFound' | 'error'
    ) {
        super(originalError.message);
    }
}

/** Redirects to `pathOrRoute`, resolved once `runResolve()` catches this. */
export function redirect(pathOrRoute: string, params?: UrlParams): never {
    throw new RouteRedirect(pathOrRoute, params);
}

/** Fails the current resolution with `status`. */
export function routeError(status: number, message?: string): never {
    throw new RouteHttpError(status, message);
}
