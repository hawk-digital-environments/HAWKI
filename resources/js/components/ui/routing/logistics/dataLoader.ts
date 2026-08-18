/**
 * The contracts behind a node's `loadData`, `cacheKey` and `paramSchema` — the
 * three things a page or layout declares through `routeConfig.ts`'s
 * `configurePage()`/`configureLayout()`:
 *
 * ```svelte
 * <script module lang="ts">
 *     export const config = configurePage({
 *         loadData: async (ctx) => ({models: await ctx.restApi.getResourceCollection('ai-models')})
 *     });
 * </script>
 * <script lang="ts">
 *     const {data}: RouteProps<typeof config> = $props();
 * </script>
 * ```
 *
 * This module defines the shapes; `routeConfig.ts` is what authors call, and
 * is also where the types below get their params from the sibling
 * `paramSchema` instead of raw `RouteParams`. `lazyComponent.ts` discovers a
 * component's `config` export when it resolves the module namespace;
 * `router.ts` runs each node's loader concurrently across the render
 * chain and publishes the results as `Router.nodeData`, which `RouterView`
 * passes to each node as its `data` prop.
 *
 * `cacheKey` is evaluated *before* the loader runs, to decide whether the
 * loader needs to run at all; the LRU store itself lives in `dataCache.ts`.
 */
import type {Route, RouteContext, RouteParams} from 'universal-router';
import type {RouterHandle} from '$lib/components/ui/routing/logistics/router.js';
import type {RouteDataLoaderContextExtensions} from '$lib/components/ui/routing/extendableTypes.js';
import type {UrlParams} from 'universal-router/generateUrls';
import type {z} from 'zod';

export interface RouteDataLoaderContext<TParams = RouteParams> extends RouteDataLoaderContextExtensions {
    /**
     * The owning router — deliberately NOT `app.router`. A nested or
     * transient router's loaders must resolve navigation against the router
     * that is actually rendering them, not reach past it for the app-level
     * one.
     */
    router: RouterHandle;
    /** The matched route object. */
    route: Route | null;
    params: TParams;
    /** The path being resolved, already normalized. */
    path: string;
    context: RouteContext;
    /**
     * Aborted when this resolution is superseded by a newer navigation. Pass
     * it to `restApi` calls — `FetchOptions extends RequestInit`, so `signal`
     * is honoured end to end.
     */
    signal: AbortSignal;
    /**
     * Discards this loader's result instead of storing it, without affecting
     * the cache lookup that already happened before the loader ran.
     *
     * This is deliberately the *only* cache-related thing a loader can do.
     * `cacheKey` (see below) decides *identity* — whether a result exists to
     * look up at all — and is evaluated before the loader runs, so it is a
     * separate module export, not a method on this context. `disableCache()`
     * decides *storage* of the result the loader just computed, which is why
     * it lives here instead: it is well-defined only because it does not
     * participate in the lookup. They are not two halves of one switch.
     */
    disableCache: () => void;
    /**
     * Redirects. Throws — never returns — so `return ctx.redirect(...)` and a
     * bare `ctx.redirect(...)` both propagate the same way; see `signals.ts`'s
     * {@link import('./signals.js').redirect} for why the signature is
     * `never` rather than a sentinel return. Resolved against the *owning*
     * router's `getPath()` (same instance as {@link router}), not `app.router`
     * — see {@link router}'s own doc comment for why that distinction matters.
     */
    redirect: (pathOrRoute: string, params?: UrlParams) => never;
    /**
     * Fails this resolution with an HTTP-style status. Throws — never
     * returns. `404` lands the router on `state: 'notFound'`, anything else
     * on `state: 'error'` — see `router.ts`'s `runResolve()`.
     */
    error: (status: number, message?: string) => never;
}

/**
 * A route/layout's data loader, in the type-erased form the router stores and
 * runs. Authors do not name this type: `configurePage()`/`configureLayout()`
 * take the loader as a plain function so its return type is *inferred* rather
 * than erased to the `Record<string, unknown>` below, and `RouteProps<typeof
 * config>` reads that inferred type back.
 */
export type RouteDataLoader<TParams = RouteParams> =
    (ctx: RouteDataLoaderContext<TParams>) => Promise<Record<string, unknown>>;

/**
 * Context for computing a node's cache key. Deliberately a *smaller* context
 * than {@link RouteDataLoaderContext}: being a plain interface rather than
 * `RouteDataLoaderContextExtensions`, it structurally cannot carry `restApi`
 * or any other app service. The key is evaluated to decide whether the loader
 * needs to run at all, so it must be computable without fetching anything.
 */
export interface RouteCacheKeyContext<TParams = RouteParams> {
    /** See {@link RouteDataLoaderContext.router}. */
    router: RouterHandle;
    route: Route | null;
    params: TParams;
    /** The path being resolved, already normalized. */
    path: string;
    context: RouteContext;
    /**
     * Builds a cache key from `prefix` plus every matched param, so a
     * hand-written key cannot forget one — the bug this exists to prevent is
     * a `cacheKey` that only encodes part of the params and ends up serving
     * one URL's data on another (e.g. `/chat/a`'s data on `/chat/b`). See
     * {@link makeCacheKey} for the serialisation.
     */
    makeKey: (prefix: string) => string;
}

/**
 * Computes a node's cache key, or returns `false` to skip caching for this
 * particular resolution (e.g. `ctx.params.id === 'new'` for a not-yet-created
 * draft that should never be served from a stale cache).
 */
export type RouteCacheKeyResolver<TParams = RouteParams> =
    (ctx: RouteCacheKeyContext<TParams>) => string | false;

/**
 * A node's declared cache identity, given as a config's `cacheKey`:
 *
 * ```ts
 * export const config = configurePage({
 *     cacheKey: (ctx) => ctx.makeKey('chat'),
 *     // or: cacheKey: false,   // never cache this node
 *     // or: omit it entirely — see `defaultCacheKey` below
 *     loadData: async (ctx) => ({...})
 * });
 * ```
 *
 * Evaluated *before* `loadData` runs, so the router can answer "is this
 * already cached?" without running the loader. A literal `false` opts a node
 * out of caching entirely, independent of any given resolution.
 */
export type RouteCacheKey<TParams = RouteParams> = RouteCacheKeyResolver<TParams> | false;

/**
 * Builds the deterministic key behind {@link RouteCacheKeyContext.makeKey}:
 * `prefix` plus every `params` entry, keys sorted before serialising so the
 * same params in a different object key order always produce the same
 * string. An array-valued param (`RouteParams` allows `string | string[]`)
 * is joined with the ASCII "record separator" control character, and each
 * `key=value` pair (plus the leading `prefix`) with the "unit separator" —
 * neither can occur in a URL path segment, so they cannot collide with a real
 * param value. Deliberately does not fold in the node id: `prefix` is the
 * author's namespace, which is what lets two nodes share one cache entry.
 */
export function makeCacheKey(prefix: string, params: RouteParams): string {
    const PART_SEP = '\u001f';
    const ARRAY_SEP = '\u001e';
    const serializedParams = Object.keys(params)
        .sort()
        .map((key) => {
            const value = params[key];
            return `${key}=${Array.isArray(value) ? value.join(ARRAY_SEP) : value}`;
        })
        .join(PART_SEP);
    return `${prefix}${PART_SEP}${serializedParams}`;
}

/**
 * The cache key used when a node declares no `cacheKey` at all — the node's
 * build-time {@link import('./nodes.js').RouteNode.id} plus the full
 * normalized path. Folding the path in keeps the default safe under params:
 * two different URLs through the same node can never collide, at the cost of
 * never sharing a cache entry between them either. The opposite trade-off from
 * {@link makeCacheKey}, which deliberately leaves the node id out so that two
 * nodes *can* share one entry.
 */
export function defaultCacheKey(nodeId: string, path: string): string {
    return `${nodeId}|${path}`;
}

/**
 * Resolves a node's effective cache key for one resolution: an explicit
 * `false` (declared, or returned by a resolver) means "do not cache" and
 * yields `null`, a resolver's string return is used as-is, and no declaration
 * at all falls back to {@link defaultCacheKey}.
 *
 * Takes the node's id and declaration rather than the `ResolvedRouteNode` they
 * come from, so that this module stays free of a dependency on `nodes.ts` —
 * which imports the cache-key types from here.
 */
export function resolveCacheKey(nodeId: string, declared: RouteCacheKey | undefined, ctx: RouteCacheKeyContext): string | null {
    if (declared === false) {
        return null;
    }
    if (typeof declared === 'function') {
        const result = declared(ctx);
        return result === false ? null : result;
    }
    return defaultCacheKey(nodeId, ctx.path);
}

/**
 * A node's declared param validation, given as a config's `paramSchema`:
 *
 * ```svelte
 * <script module lang="ts">
 *     export const config = configurePage({paramSchema: z.object({id: z.coerce.number()})});
 * </script>
 * <script lang="ts">
 *     const {params}: RouteProps<typeof config> = $props();   // params.id is number
 * </script>
 * ```
 *
 * `router.ts` runs `schema.safeParse(rawParams)` for this node before
 * evaluating its `cacheKey` or running its `loadData` — the parsed (and
 * possibly coerced/transformed) output replaces the raw params for that node
 * everywhere: the cache key context, the loader context, and the component's
 * `params` prop.
 */
export type RouteParamsSchema = z.ZodType;
