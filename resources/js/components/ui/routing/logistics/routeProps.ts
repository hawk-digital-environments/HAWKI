/**
 * Prop shapes for route-rendered components. `RouterView` passes these
 * directly as props — pages and layouts declare the `TParams`/`TData` they
 * expect and read them off `$props()` like any other component, instead of
 * pulling them from `useRouter()`.
 */
import type {Route} from 'universal-router';
import type {Snippet} from 'svelte';
import type {z} from 'zod';
import type {RouteParamsSchema} from './dataLoader.js';
import type {RouteConfig, RouteConfigParams} from './routeConfig.js';
import type {RouteMeta} from './RouteRegistrar.js';

/**
 * A config contributes the output of its `paramSchema` (or raw `RouteParams`
 * when it declares none); a bare `paramSchema` is unwrapped with `z.output`;
 * anything else is taken as-is.
 */
type InferRouteParams<T> =
    T extends RouteConfig<infer TSchema, any> ? RouteConfigParams<TSchema>
        : T extends RouteParamsSchema ? z.output<T>
            : T;

/**
 * A config contributes what its `loadData` resolves to (or `void` when it
 * declares none), taking precedence over `TData` — a config already carries
 * both halves, so the second type argument is not passed alongside one. A bare
 * `loadData` in `TData` is unwrapped to what it resolves to; matched on "is a
 * function" rather than on `RouteDataLoader`, so a loader that was never
 * annotated still unwraps. Anything else is taken as-is.
 */
type InferRouteData<TConfigOrParams, TData> =
    TConfigOrParams extends RouteConfig<any, infer TConfigData> ? TConfigData
        : TData extends (...args: never[]) => unknown ? Awaited<ReturnType<TData>>
            : TData;

/**
 * Props a route page receives.
 *
 * Normally passed the component's own config — `RouteProps<typeof config>`
 * carries both the params and the loaded data, so there is nothing else to
 * spell out:
 *
 * ```svelte
 * export const config = configurePage({paramSchema: ..., loadData: ...});
 * const {params, data}: RouteProps<typeof config> = $props();
 * ```
 *
 * The two-argument form stays for components the router feeds without them
 * declaring a config of their own — a page whose loader is given at
 * registration, say. It accepts concrete types (`RouteProps<{id: string},
 * {foo: number}>`) or a bare `paramSchema`/`loadData` to unwrap.
 *
 * The `void` defaults mean "this route declares no params / no loader data,
 * don't read this" — the router always passes a real value at runtime (`{}`
 * when there is no loader), so `void` restricts use rather than lying about
 * presence.
 *
 * `TMeta` narrows the {@link meta} prop. It is deliberately a plain type
 * rather than something a config declares, because meta belongs to the
 * *route*, not to the component — the same page can be registered on two
 * routes with different meta, and a layout receives the meta of whichever
 * route is currently open inside it, which its own config could never know.
 * Being third, it is spelled alongside a config as
 * `RouteProps<typeof config, void, ChatMeta>`; `TData` is ignored when a
 * config is given, so the `void` there is filler, not a claim.
 */
export interface RouteProps<TConfigOrParams = void, TData = void, TMeta = RouteMeta> {
    data: InferRouteData<TConfigOrParams, TData>;
    params: InferRouteParams<TConfigOrParams>;
    /**
     * The matched route's `meta` (see {@link RouteOptions.meta}), or `{}` when
     * it declares none. Every node of the render chain receives the *same*
     * object — unlike `data`/`params`, which are each node's own — so a layout
     * reads the meta of the page currently open inside it. Unvalidated: narrow
     * it through `TMeta` above and the router hands it over as declared.
     */
    meta: TMeta;
    route: Route | null;
}

/** {@link RouteProps} plus the wrapped content a layout renders via `{@render children()}`. */
export interface RouteLayoutProps<TConfigOrParams = void, TData = void, TMeta = RouteMeta> extends RouteProps<TConfigOrParams, TData, TMeta> {
    children: Snippet;
}
