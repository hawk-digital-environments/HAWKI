/**
 * The single `config` export a page or layout component declares in its
 * `<script module>`, replacing the separate `paramSchema`/`loadData`/`cacheKey`
 * exports:
 *
 * ```svelte
 * <script module lang="ts">
 *     export const config = configurePage({
 *         paramSchema: z.object({id: z.coerce.number()}),
 *         loadData: async (ctx) => ({chat: await ctx.restApi.getChat(ctx.params.id)})
 *     });
 * </script>
 * <script lang="ts">
 *     const {params, data}: RouteProps<typeof config> = $props();
 * </script>
 * ```
 *
 * One call instead of three exports buys two things separate exports cannot:
 *
 * - `ctx.params` inside `loadData` and `cacheKey` is typed by the sibling
 *   `paramSchema` (`ctx.params.id` above is `number`, post-coercion). Separate
 *   exports cannot see each other, so they only ever got raw `RouteParams`.
 * - The loader's return type survives. `export const loadData: RouteDataLoader`
 *   used to erase it to the contract's `Record<string, unknown>` — the helper's
 *   parameter position checks the contract *and* infers the narrow type, so
 *   there is nothing left to annotate wrong.
 *
 * `lazyComponent.ts` discovers the `config` export when it resolves a
 * component's module namespace; `nodes.ts` decides it against any config given
 * at registration; `router.svelte.ts` runs the parts.
 */
import type {RouteParams} from 'universal-router';
import type {z} from 'zod';
import type {RouteCacheKey, RouteDataLoader, RouteDataLoaderContext, RouteParamsSchema} from '$lib/components/ui/routing/logistics/dataLoader.js';

/**
 * The params a node's `loadData`/`cacheKey` see: the schema's parsed output
 * when it declares a `paramSchema`, the router's raw params when it does not.
 */
export type RouteConfigParams<TSchema> = TSchema extends RouteParamsSchema ? z.output<TSchema> : RouteParams;

/**
 * What {@link configurePage}/{@link configureLayout} accept. Every member is
 * optional — a config declaring only a `paramSchema` (validate params, load
 * nothing) is as valid as one declaring only a `loadData`.
 */
export interface RouteConfigInput<TSchema extends RouteParamsSchema | undefined, TData extends Record<string, unknown> | void> {
    /**
     * Validates and transforms this node's params before anything else runs.
     * Its output type flows into `loadData` and `cacheKey` below, and into the
     * component's `params` prop.
     */
    paramSchema?: TSchema;
    /**
     * Loads this node's `data` prop. Declared as a plain function rather than
     * as {@link RouteDataLoader} so its return type is inferred rather than
     * erased — the `Record<string, unknown>` contract is enforced by `TData`'s
     * constraint instead.
     */
    loadData?: (ctx: RouteDataLoaderContext<RouteConfigParams<TSchema>>) => Promise<TData>;
    /** This node's cache identity — see `dataLoader.ts`'s {@link RouteCacheKey}. `false` opts out of caching entirely. */
    cacheKey?: RouteCacheKey<RouteConfigParams<TSchema>>;
}

/**
 * Phantom brand, never present at runtime. `RouteConfigInput`'s members are
 * all optional, so without it *any* object type would structurally satisfy
 * `T extends RouteConfig<...>` — and `RouteProps<{id: string}>` (a concrete
 * param type) would be misread as a config. The brand makes the check in
 * `routeProps.ts` mean "was produced by one of the helpers below".
 */
declare const routeConfigBrand: unique symbol;

/** What {@link configurePage}/{@link configureLayout} return: their input, branded and carrying both inferred types. */
export interface RouteConfig<
    TSchema extends RouteParamsSchema | undefined = undefined,
    TData extends Record<string, unknown> | void = void
> extends RouteConfigInput<TSchema, TData> {
    readonly [routeConfigBrand]: true;
}

/**
 * A config with its type parameters erased — what the router stores and reads
 * at runtime, where the concrete schema and data types are neither known nor
 * needed. Only the plumbing should name this; authors get the inferred type
 * from the helpers.
 */
export type AnyRouteConfig = RouteConfig<any, any>;

/**
 * Declares a page component's params, data loader and cache identity in one
 * call — see this module's header for what that buys over separate exports.
 */
export function configurePage<
    TSchema extends RouteParamsSchema | undefined = undefined,
    TData extends Record<string, unknown> | void = void
>(config: RouteConfigInput<TSchema, TData>): RouteConfig<TSchema, TData> {
    return config as RouteConfig<TSchema, TData>;
}

/**
 * {@link configurePage} for a layout component. Identical in shape today, and
 * a separate name for two reasons: `configurePage` reads wrong at the top of
 * `AdminLayout.svelte`, and the two are not interchangeable in practice — a
 * layout's `loadData` runs once and then stays mounted across every navigation
 * *within* it, so its caching and invalidation behave differently from a
 * page's even though the types are the same.
 */
export function configureLayout<
    TSchema extends RouteParamsSchema | undefined = undefined,
    TData extends Record<string, unknown> | void = void
>(config: RouteConfigInput<TSchema, TData>): RouteConfig<TSchema, TData> {
    return config as RouteConfig<TSchema, TData>;
}

/**
 * Runtime guard used by `lazyComponent.ts` to reject a `config` export that
 * did not come from the helpers. Checks the members rather than the brand —
 * the brand is a type-level fiction with no runtime representation.
 */
export function assertIsRouteConfig(value: unknown, describe: string): asserts value is AnyRouteConfig {
    if (typeof value !== 'object' || value === null) {
        throw new Error(`Lazy loader for ${describe} exported "config" that is not an object — use configurePage()/configureLayout().`);
    }
    const {paramSchema, loadData, cacheKey} = value as Record<string, unknown>;
    if (loadData !== undefined && typeof loadData !== 'function') {
        throw new Error(`Lazy loader for ${describe} exported a "config" whose "loadData" is not a function.`);
    }
    if (cacheKey !== undefined && cacheKey !== false && typeof cacheKey !== 'function') {
        throw new Error(`Lazy loader for ${describe} exported a "config" whose "cacheKey" is not a function or false.`);
    }
    if (paramSchema !== undefined && (typeof paramSchema !== 'object' || paramSchema === null || typeof (paramSchema as { safeParse?: unknown }).safeParse !== 'function')) {
        throw new Error(`Lazy loader for ${describe} exported a "config" whose "paramSchema" is not a Zod schema.`);
    }
}
