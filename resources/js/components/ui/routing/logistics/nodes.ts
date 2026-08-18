/**
 * Turns a matched route into the ordered chain of renderable "nodes" that
 * wrap it — every layout above it, outermost first, followed by its own page.
 *
 * `universal-router` links every matched route to its `parent` while matching,
 * and hands the route objects back untouched — so the nodes stamped onto the
 * route and onto the groups above it are all reachable from the single
 * matched route, without the router having to track them during resolution.
 */
import type {Route} from 'universal-router';
import {type ComponentOrLoader, resolveComponentModule, type ResolvedComponentModule} from './lazyComponent.js';
import type {Component} from 'svelte';
import type {HawkiRoute} from './RouteRegistrar.js';
import type {RouteCacheKey, RouteDataLoader, RouteParamsSchema} from './dataLoader.js';
import {type AnyRouteConfig} from './routeConfig.js';

export type RouteNodeKind = 'layout' | 'page';

/**
 * A single renderable unit of a route's chain — either a layout or a page.
 * Stamped once at build time by {@link RouteRegistrar} and from then on
 * treated as immutable data; `router.ts` folds its `id` into the
 * default `loadData` cache key (see `defaultCacheKey()` in `dataLoader.ts`).
 */
export interface RouteNode {
    /** Stable, unique id assigned once at build time. Part of the default `loadData` cache key — see `defaultCacheKey()` in `dataLoader.ts`. */
    readonly id: string;
    readonly kind: RouteNodeKind;
    readonly componentOrLoader: ComponentOrLoader<any>;
    /**
     * Name of the owning route/group, if it has one. A plain string, never a
     * back-reference to the route object — `debugger.ts` dumps this
     * structure, and a back-reference would make the graph cyclic.
     */
    readonly routeName?: string;
    /** Path of the owning route/group, for debugging and error messages. */
    readonly routePath: string;
    /**
     * Config given at registration, which takes precedence over any `config`
     * the component's module exports — see {@link resolveNodeConfig}. Comes
     * from `RouteOptions.config`/`layoutConfig`,
     * `RouteGroupOptions.layoutConfig` or `CreateRouterOptions.rootLayoutConfig`
     * depending on which kind of node this is.
     */
    readonly configOption?: AnyRouteConfig;
}

/**
 * Collects the node chain along the matched route's `parent` chain, ordered
 * outermost layout first, page last — i.e. ready to be nested into each other
 * from left to right.
 *
 * Routes without a stamped node (including the path-less middleware wrappers)
 * drop out silently, so the result contains only real nodes.
 */
export function collectRouteNodes(route: Route | null | undefined): RouteNode[] {
    const nodes: RouteNode[] = [];

    let current: HawkiRoute | null | undefined = route;
    while (current) {
        // `page` is pushed before `layout` at each level because a route's
        // own layout wraps its own page — after the `reverse()` below that
        // puts the layout right before the page it wraps, not after it.
        if (current.nodes?.page) {
            nodes.push(current.nodes.page);
        }
        if (current.nodes?.layout) {
            nodes.push(current.nodes.layout);
        }
        current = current.parent;
    }

    // Collected leaf-first while walking up, but rendered outermost-first.
    return nodes.reverse();
}

/**
 * A {@link RouteNode} with its component resolved, plus the parts of the
 * config that is actually in effect for it. See {@link resolveNodeConfig} for
 * how that config is decided.
 */
export interface ResolvedRouteNode {
    readonly node: RouteNode;
    readonly component: Component<any>;
    readonly loadData?: RouteDataLoader;
    /** `undefined` means the default key — see `defaultCacheKey()` in `dataLoader.ts`. */
    readonly cacheKey?: RouteCacheKey;
    /** `undefined` means no validation — the node receives its params unchanged. */
    readonly paramSchema?: RouteParamsSchema;
}

/**
 * Decides which config is in effect for a node, between its two possible
 * sources: the component module's own `config` export and the config passed at
 * registration.
 *
 * **The registration config wins, as a whole.** One component can be
 * registered on several routes, and only the registration site knows that
 * *this* route wants a different dataset — if the module export won, that
 * would be impossible to express. In dev, declaring both logs a warning, since
 * the module's config is then dead code and the collision is otherwise
 * invisible: adding a `config` to the component file would silently do
 * nothing.
 *
 * Whole config, not member by member: `loadData` and `cacheKey` are typed
 * against their *own* config's `paramSchema`. Merging a registration
 * `loadData` onto a module's `paramSchema` would hand that loader params it
 * was never typed for — its `ctx.params` would claim to be raw `RouteParams`
 * while the router actually passes schema-parsed output. Repeating the schema
 * in the overriding config is the price of that guarantee.
 */
export function resolveNodeConfig(
    module: Pick<ResolvedComponentModule<Component<any>>, 'config'>,
    node: Pick<RouteNode, 'kind' | 'routePath' | 'configOption'> | undefined
): AnyRouteConfig | undefined {
    // `MODE`, not `DEV`: in this project's production build Vite resolves
    // `import.meta.env.DEV` to `true` and `PROD` to `false`, so a `DEV` guard
    // would ship enabled. `MODE` is substituted correctly, so this whole
    // block is dropped from the production bundle.
    if (import.meta.env.MODE !== 'production' && node?.configOption && module.config) {
        console.warn(
            `The ${node.kind} of route "${node.routePath}" declares a config both as a registration option `
            + `and as a module export. The registration option wins — the module's config is never used.`
        );
    }
    return node?.configOption ?? module.config;
}

/**
 * Splits the config in effect for a node into the three members the router
 * runs, so callers do not each have to reach through a possibly-absent config.
 */
export function resolveNodeParts(
    module: Pick<ResolvedComponentModule<Component<any>>, 'config'>,
    node: Pick<RouteNode, 'kind' | 'routePath' | 'configOption'> | undefined
): Pick<ResolvedRouteNode, 'loadData' | 'cacheKey' | 'paramSchema'> {
    const config = resolveNodeConfig(module, node);
    return {
        loadData: config?.loadData as RouteDataLoader | undefined,
        cacheKey: config?.cacheKey as RouteCacheKey | undefined,
        paramSchema: config?.paramSchema as RouteParamsSchema | undefined
    };
}

/**
 * Resolves every node's component (and effective `loadData`) in parallel,
 * preserving component references for unchanged nodes —
 * {@link resolveComponentModule}'s own cache (keyed on loader identity) is
 * what makes a layout shared by two routes come back as the same component
 * reference across navigations.
 */
export function resolveRouteNodes(nodes: RouteNode[]): Promise<ResolvedRouteNode[]> {
    return Promise.all(
        nodes.map(async (node): Promise<ResolvedRouteNode> => {
            const resolved = await resolveComponentModule(node.componentOrLoader, `${node.kind} of route "${node.routePath}"`);
            return {
                node,
                component: resolved.component,
                ...resolveNodeParts(resolved, node)
            };
        })
    );
}
