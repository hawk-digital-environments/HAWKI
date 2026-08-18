import {RouteDataLoaderContext} from './dataLoader.js';
import {z} from 'zod';
import type {RouterState} from './RouterState.svelte.js';
import type {HawkiRoute, RouteComponentOrLoader, RouteResultBody} from './RouteRegistrar.js';
import type {RouterHandle} from './router.js';
import {redirect, routeError, RouteHttpError, RouteRedirect, RouteResolutionError} from './signals.js';
import type {RouteError} from 'universal-router';

// Backstop for a redirect chain that never repeats a path, which
// `redirectChain`'s cycle check cannot catch on its own.
const MAX_REDIRECTS = 10;

const routeResultSchema = z.object({
    // `z.function()` would hand back a *wrapper* around the component instead of
    // the component itself; the fresh reference on every resolve makes Svelte
    // tear the page down and re-mount it even when only the params changed.
    component: z.custom<RouteComponentOrLoader>(value => typeof value === 'function'),
    context: z.object({}).loose(),
    params: z.object({}).loose()
});

export async function resolveRoute(
    state: RouterState,
    path: string,
    redirectChain: string[] = [],
    getHandle: () => RouterHandle,
    getPath: RouterHandle['getPath']
): Promise<void> {
    // Claims the router for this run and supersedes any older one still in
    // flight. Held in a local, not re-read from `state`, so that once a newer
    // run has taken over this one still sees *its own* signal as aborted.
    const signal = state.startRun();

    state.resolvePath = path;
    state.currentError = null;
    state.currentState = 'loading';

    try {
        const routeResult = await state.innerRouter.resolve(path);
        if (!routeResult) {
            // noinspection ExceptionCaughtLocallyJS
            throw new Error('No routeResult returned for path: ' + path);
        }

        if (signal.aborted) {
            console.log('Route resolution for path', path, 'was invalidated before completion.');
            return;
        }

        const routeResultParsed = routeResultSchema.safeParse(routeResult);
        if (!routeResultParsed.success) {
            // Thrown instead of handled inline so it runs through the same
            // cleanup as every other failure — in particular so it captures
            // `currentError` for the error page.
            // noinspection ExceptionCaughtLocallyJS
            throw new Error(`Invalid route result for path "${path}"`, {cause: routeResultParsed.error});
        }

        const routeResultBody = routeResultParsed.data as any as RouteResultBody;
        const {context, params} = routeResultBody;

        // The per-router extensions are spread *first* so the router's own
        // fields below cannot be shadowed by one — `signal` in particular is
        // the single source of truth for cancellation, and `nodeTree` reads it
        // back off this object rather than being handed it separately.
        const loaderContext = {
            ...(state.options?.loaderContext ?? {}),
            router: getHandle(),
            route: context.route,
            params,
            path,
            context,
            signal,
            // Delegate straight to the standalone `signals.ts` functions
            // — the router-awareness a redirect needs (resolving `target`
            // through *this* router's `getPath()`) lives entirely in how
            // `handleRedirect()` below acts on the thrown `RouteRedirect`,
            // not in how it is raised. A loader calling `ctx.redirect(...)`
            // and a middleware calling the imported `redirect(...)` end up
            // throwing the exact same signal.
            redirect: redirect,
            error: routeError
        } as RouteDataLoaderContext;

        // A rejection inside is NOT caught locally: it falls through to the
        // `catch` below and fails the *whole* resolution, exactly like any
        // other resolution failure — including for the root layout's node,
        // whose *component* degrades gracefully but whose `loadData` does not.
        // See `nodeTree.ts`'s `rootNodePromise`.
        const nodeResults = await state.nodeTree.run(
            path,
            routeResultBody,
            loaderContext,
            state.dataCache
        );
        if (nodeResults === null) {
            console.log('Route resolution for path', path, 'was invalidated while its data was loading.');
            return;
        }

        state.currentContext = context;
        state.currentComponent = nodeResults.pageComponent;
        state.currentMeta = (context.route as HawkiRoute)?.meta ?? null;
        state.currentLayouts = nodeResults.layoutComponents;
        state.currentNodeData = nodeResults.data;
        state.currentNodeParams = nodeResults.params;
        state.currentState = 'waiting';
    } catch (error) {
        await handleError(state, path, error, redirectChain, signal, getHandle, getPath);
    } finally {
        state.finishRun(signal);
        // Also skipped when this run followed a redirect: the recursive
        // `resolveRoute()` in `handleError()` started a run of its own, which
        // aborted this signal and published the *target* path itself.
        if (!signal.aborted) {
            state.currentPath = path;
        }
    }
}

async function handleError(
    state: RouterState,
    path: string,
    error: unknown,
    redirectChain: string[] = [],
    signal: AbortSignal,
    getHandle: () => RouterHandle,
    getPath: RouterHandle['getPath']
) {
    // A superseded run must not publish an error page any more than it may
    // publish a route — and this is also where the `AbortError` its own
    // cancelled `restApi` calls reject with gets swallowed.
    if (signal.aborted) {
        console.log('Route resolution for path', path, 'was invalidated before its error could be handled.');
        return;
    }

    if (error instanceof RouteRedirect) {
        await handleRedirect(state, path, error, redirectChain, getHandle, getPath);
        return;
    }

    if (error instanceof RouteHttpError) {
        await resetToRootOnly(state);
        state.currentError = error;
        // `404` reuses the router's existing `notFound` state (same
        // page a genuinely unmatched path gets); any other status is
        // an application-level failure, same bucket as a crashed
        // resolution.
        state.currentState = error.status === 404 ? 'notFound' : 'error';
        return;
    }

    await resetToRootOnly(state);
    const originalError = error instanceof RouteResolutionError ? error.originalError : error;
    state.currentError = originalError as Error | RouteError;

    if (error instanceof RouteResolutionError && error.type === 'notFound') {
        // Not an application failure — just a path nothing matched.
        console.warn('No route matched path:', path);
        state.currentState = 'notFound';
        return;
    }

    console.error('Error resolving route for path:', path, originalError);
    state.currentState = 'error';
}

/**
 * Follows a {@link RouteRedirect} by re-entering {@link resolveRoute} for its
 * target, carrying `path` forward in `redirectChain` so a redirect that comes
 * back around can be recognised.
 */
async function handleRedirect(
    state: RouterState,
    path: string,
    error: RouteRedirect,
    redirectChain: string[],
    getHandle: () => RouterHandle,
    getPath: RouterHandle['getPath']
): Promise<void> {
    // `target` is only resolvable here, not where `redirect()` was called: a
    // middleware calling the standalone `redirect()` has no router instance,
    // so `error.target` stayed a raw name/path — see `signals.ts`. This
    // router's own `getPath()` is what turns it into a concrete path.
    const target = getPath(error.target, error.params);

    if (redirectChain.length >= MAX_REDIRECTS || redirectChain.includes(target)) {
        await resetToRootOnly(state);
        state.currentError = new Error(
            `Redirect loop detected: ${[...redirectChain, target].join(' -> ')}`
        );
        console.error(state.currentError.message);
        state.currentState = 'error';
        return;
    }

    // `strategy.set()` marks the resolve `$effect` in `bind()` dirty, which
    // would otherwise schedule a duplicate resolution of `target`. Calling
    // `resolveRoute()` immediately after — nothing awaited in between — sets
    // `resolvePath = target` before that effect runs, so its own `newPath ===
    // resolvePath` guard finds itself already satisfied.
    state.strategy.set(target, {replace: error.replace});
    await resolveRoute(state, target, [...redirectChain, path], getHandle, getPath);
}

/**
 * Shared cleanup for every way a resolution can end up with no matched
 * route — 404, a genuine error, or a redirect that hit the loop guard
 * below: drops `currentMeta`/`currentLayouts` down to "root layout only"
 * (there is no matched route to inherit anything else from) and clears
 * `currentNodeData`, so a failed resolution can never leave the previous
 * route's data or layouts visible behind the 404/error page.
 * `currentNodeData` is always reset to `[]` rather than special-cased to
 * "root-layout-only data" — simpler, and the root layout rarely has a
 * loader of its own. `currentNodeParams` is cleared alongside it for the
 * same reason: a stale per-node params array behind a 404/error page
 * would be the same bug as stale data.
 */
async function resetToRootOnly(state: RouterState): Promise<void> {
    // Cleared too: `Router.route`/`params` are read from it and handed to
    // the root layout as props, which would otherwise still describe the
    // last route that resolved successfully.
    state.currentContext = null;
    state.currentMeta = null;
    state.currentLayouts = await state.nodeTree.buildRootLayoutComponents();
    state.currentNodeData = [];
    state.currentNodeParams = [];
}
