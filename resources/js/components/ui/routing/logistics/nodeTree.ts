import {collectRouteNodes, type ResolvedRouteNode, resolveNodeParts, resolveRouteNodes, type RouteNode} from './nodes.js';
import type {CreateRouterOptions} from './router.js';
import {type ComponentModuleResolver, resolveLayoutOption} from './lazyComponent.js';
import type {HawkiRoute, RouteComponent, RouteLayout, RouteResultBody} from './RouteRegistrar.js';
import type {Route, RouteParams} from 'universal-router';
import {makeCacheKey, resolveCacheKey, type RouteCacheKeyContext, type RouteDataLoaderContext} from './dataLoader.js';
import {routeError} from './signals.js';
import type {RouteDataCache} from './dataCache.js';

export type RouterNodeTree = ReturnType<typeof createNodeTree>;

/** The render chain, plus the two slices of it callers ask for by name. */
interface RenderChain {
    /** Root layout (if any), this route's own layouts, then the page — the order `RouterView` renders. */
    nodes: ResolvedRouteNode[];
    pageNode: ResolvedRouteNode;
    layoutNodes: ResolvedRouteNode[];
}

/** One render-chain node's resolved data plus its parsed params — what {@link runNode} produces. */
interface RouteNodeResult {
    data: Record<string, unknown>;
    params: unknown;
}

/** What one full pass over the render chain publishes. `data` and `params` are index-aligned with `[...layoutComponents, pageComponent]`. */
export interface RouteNodeTreeResult {
    pageComponent: RouteComponent;
    layoutComponents: RouteLayout[];
    data: Record<string, unknown>[];
    params: unknown[];
}

export function createNodeTree(
    routerName: string,
    resolveComponentModule: ComponentModuleResolver,
    options?: CreateRouterOptions
) {

    // A distinct id namespace (`root#` vs. the `n<counter>#` ids `RouteRegistrar`
    // mints) so two routers that both carry a root layout can never collide,
    // even though neither goes through the module-level node-id counter.
    const rootNode: RouteNode | null = (() => {
        const rootLayout = resolveLayoutOption(options?.rootLayout, options?.lazyRootLayout, `Router "${routerName}"`);
        if (!rootLayout) {
            return null;
        }

        return {
            id: `root#${routerName}:layout`,
            kind: 'layout',
            componentOrLoader: rootLayout,
            routePath: '/',
            configOption: options?.rootLayoutConfig
        };
    })();

    // Loaded once and shared by every resolution — a failing root layout must
    // not take the whole router down, so it degrades to "no root layout".
    // Resolved on its own rather than folded into the `Promise.all` over the
    // matched route's node chain below, precisely so its failure stays
    // isolated and never fails a route resolution that has nothing to do with
    // it.
    //
    // This only degrades a failure to *load the component itself* (e.g. a
    // chunk 404). Once the component has resolved, its `loadData` becomes
    // just another entry in the `Promise.all` `run()` does over the whole
    // render chain below — so a root layout that loaded fine but whose
    // `loadData` rejects IS fatal, same as any other node's loader failing.
    const rootNodePromise: Promise<ResolvedRouteNode[]> = (async () => {
        if (!rootNode) {
            return [];
        }

        try {
            const resolved = await resolveComponentModule(
                rootNode.componentOrLoader,
                `root layout of router "${routerName}"`
            );
            return [{
                node: rootNode,
                component: resolved.component as RouteLayout,
                ...resolveNodeParts(resolved, rootNode)
            }];
        } catch (error) {
            console.error('Failed to load the root layout of router:', routerName, error);
            return [];
        }
    })();


    /**
     * Root layout plus the matched route's own layout chain, resolved in
     * parallel as full {@link ResolvedRouteNode}s — component *and* effective
     * loader — rather than just components, because {@link run} needs every
     * node's loader to run its data-loading pass. The page itself is
     * deliberately not resolved here, even though the chain may end in a page
     * node: {@link buildRenderChain} resolves the page from the route's
     * *result body* instead, which is what lets a middleware's replacement
     * component win over the route's own page. A page node is only ever the
     * *last* entry of `collectRouteNodes()`'s result (see its push-order
     * comment) — when the matched route contributed one, drop it; when
     * resolution was intercepted by a middleware before reaching a real page
     * (its own route object carries no nodes), the chain is layouts only and
     * nothing needs dropping.
     */
    async function buildLayoutNodeStack(route: Route | null | undefined): Promise<ResolvedRouteNode[]> {
        const chain = collectRouteNodes(route);
        const layoutNodes = chain.length > 0 && chain[chain.length - 1].kind === 'page'
            ? chain.slice(0, -1)
            : chain;

        const [rootNodes, routeLayoutNodes] = await Promise.all([
            rootNodePromise,
            resolveRouteNodes(layoutNodes)
        ]);
        return [...rootNodes, ...routeLayoutNodes];
    }

    async function buildRootLayoutComponents(): Promise<RouteLayout[]> {
        return (await rootNodePromise).map((resolved) => resolved.component as RouteLayout);
    }

    async function buildRenderChain(
        path: string,
        {component: componentOrLoader, context}: RouteResultBody,
        signal: AbortSignal
    ): Promise<RenderChain | null> {

        // Loaded before anything is published so a lazy page or layout
        // keeps the router in `loading` instead of flashing an
        // unwrapped page. The page comes from the result body (not the
        // node chain) so that a middleware's replacement component is
        // resolved and rendered, not silently overridden by the matched
        // route's own page node — see `buildLayoutNodeStack()`.
        const [pageModule, layoutNodes] = await Promise.all([
            resolveComponentModule(componentOrLoader, `route "${path}"`),
            buildLayoutNodeStack(context.route)
        ]);
        if (signal.aborted) {
            console.log('Route resolution for path', path, 'was invalidated while its layouts were loading.');
            return null;
        }

        // The registration options always come from the *matched* route's
        // own page node, exactly like `meta` is read from `context.route`
        // regardless of whether a middleware replaced the rendered component.
        const matchedPageNode = (context.route as HawkiRoute | null | undefined)?.nodes?.page;
        const pageNode: ResolvedRouteNode = {
            node: matchedPageNode ?? {id: `page#${path}`, kind: 'page', componentOrLoader, routePath: path},
            component: pageModule.component as RouteComponent,
            ...resolveNodeParts(pageModule, matchedPageNode)
        };

        return {nodes: [...layoutNodes, pageNode], pageNode, layoutNodes};
    }

    /**
     * Resolves the render chain for `path` and runs every node's loader over
     * it. Returns `null` when a newer navigation superseded this one before
     * the result could be published.
     *
     * Every node's loader runs concurrently — there is no data inheritance
     * between nodes (a layout never sees its page's data or vice versa), so
     * nothing has a reason to wait on anything else. A node without a loader,
     * or one whose data comes back from `dataCache`, contributes without ever
     * calling the loader — see {@link runNode}.
     */
    async function run(
        path: string,
        routeResultBody: RouteResultBody,
        loaderContext: RouteDataLoaderContext,
        dataCache: RouteDataCache
    ): Promise<RouteNodeTreeResult | null> {
        const chain = await buildRenderChain(path, routeResultBody, loaderContext.signal);

        if (!chain) {
            return null;
        }

        const results = await Promise.all(
            chain.nodes.map((node) => runNode(node, loaderContext, dataCache))
        );

        return {
            pageComponent: chain.pageNode.component as RouteComponent,
            layoutComponents: chain.layoutNodes.map((node) => node.component as RouteLayout),
            data: results.map((result) => result.data),
            params: results.map((result) => result.params)
        };
    }

    return {
        rootNode: rootNode,
        promise: rootNodePromise,
        buildRootLayoutComponents,
        run
    };
}

/**
 * Runs one render-chain node: validates its params against `paramSchema`
 * (if any), then consults and (conditionally) populates `dataCache`
 * around its `loadData` (if any). The parsed params — unchanged from
 * `loaderContext.params` when the node has no `paramSchema` — replace the raw
 * ones everywhere downstream: the cache key context, the loader context, and
 * the returned result. Validation always runs, even for a node with no
 * `loadData`, so a page that only declares `paramSchema` still gets coerced
 * params in its props.
 *
 * A node without a loader never touches the cache — there is nothing to
 * key a lookup on and nothing to store — so it short-circuits before the
 * cache-key/loader machinery.
 *
 * A cache hit resolves without ever calling `node.loadData`, but this
 * function is still always `async`/awaited through `Promise.all` in
 * {@link createNodeTree}'s `run()` — a hit resolving "instantly" is still a
 * microtask away, so it never blocks the other nodes' (possibly real) loaders
 * from running concurrently.
 *
 * `nodeCtx` is a fresh shallow clone of `loaderContext` per call, each with
 * its own `disableCache()` closure: the opt-out is per-node-per-
 * resolution, so sharing one context (and one flag) across nodes would
 * let one node's `disableCache()` call accidentally suppress storage for
 * every other node resolving concurrently in the same pass.
 */
async function runNode(
    node: ResolvedRouteNode,
    loaderContext: RouteDataLoaderContext,
    dataCache: RouteDataCache
): Promise<RouteNodeResult> {
    const params = parseNodeParams(node, loaderContext.params);

    if (!node.loadData) {
        return {data: {}, params};
    }

    const cacheKeyCtx: RouteCacheKeyContext = {
        router: loaderContext.router,
        route: loaderContext.route,
        params: params as RouteParams,
        path: loaderContext.path,
        context: loaderContext.context,
        // Built from the parsed params (not `loaderContext.params`), consistent
        // with the rest of this context — see `parseNodeParams()` below.
        makeKey: (prefix: string) => makeCacheKey(prefix, params as RouteParams)
    };
    const key = resolveCacheKey(node.node.id, node.cacheKey, cacheKeyCtx);

    if (key !== null) {
        const cached = dataCache.get(key);
        if (cached) {
            return {data: cached.data, params};
        }
    }

    let cacheDisabled = false;
    const nodeCtx: RouteDataLoaderContext = {
        ...loaderContext,
        params: params as RouteParams,
        disableCache: () => {
            cacheDisabled = true;
        }
    };

    const data = await node.loadData(nodeCtx);

    // Store only if: this node caches at all (`key !== null`), the loader
    // didn't call `disableCache()`, and this run still owns the router — a
    // superseded run's data is never rendered, so caching it would seed the
    // cache with a result nobody asked for. Read from `loaderContext` rather
    // than taken as a parameter, so it is the same signal the loader itself
    // was handed as `ctx.signal`.
    //
    // A `clearData()` that lands *while* this loader is in flight is
    // deliberately not guarded against: the result is about to be rendered
    // either way, and suppressing the write would only make the cache
    // disagree with what is on screen.
    if (key !== null && !cacheDisabled && !loaderContext.signal.aborted) {
        dataCache.set({key, nodeId: node.node.id, path: loaderContext.path, routeName: node.node.routeName, data});
    }

    return {data, params};
}

/**
 * Validates/coerces a render-chain node's raw matched params against its
 * declared `paramSchema`, run synchronously (`safeParse`, never
 * `safeParseAsync` — an async-refined schema would fail loudly here instead
 * of silently deadlocking). A node without a `paramSchema` gets `rawParams`
 * back unchanged. On failure, warns once with the node's `routePath` and the
 * zod issues, then fails the resolution with a 404 via `routeError()` —
 * `routeResolver.ts`'s existing `RouteHttpError` handling turns that into
 * `state: 'notFound'`.
 */
function parseNodeParams(node: ResolvedRouteNode, rawParams: unknown): unknown {
    if (!node.paramSchema) {
        return rawParams;
    }
    const result = node.paramSchema.safeParse(rawParams);
    if (!result.success) {
        console.warn(`Route param validation failed for node "${node.node.routePath}":`, result.error.issues);
        routeError(404, `Invalid params for route "${node.node.routePath}"`);
    }
    return result.data;
}
