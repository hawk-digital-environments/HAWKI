/**
 * Shared plumbing for "a component, or a function that imports one".
 *
 * A Svelte 5 `Component` *is* a function, so a loader cannot be told apart from
 * an already-imported component by looking at it. Everything that accepts both
 * therefore requires loaders to be tagged with {@link LAZY_COMPONENT_MARKER} —
 * either by {@link lazyComponent} or by `RouteRegistrar.lazyRoute()`.
 */
import type {Component} from 'svelte';
import {type AnyRouteConfig, assertIsRouteConfig} from '$lib/components/ui/routing/logistics/routeConfig.js';

/** Value of the `type` marker that flags a function as a loader rather than a component. */
export const LAZY_COMPONENT_MARKER = 'lazy_route';

/** Imports a component on demand — either the component itself or a module namespace with it as `default`. */
export type ComponentLoader<TComponent extends Component<any>> = () => Promise<TComponent | { default?: TComponent }>;
/** A {@link ComponentLoader} carrying the marker that makes it distinguishable from a component. */
export type LazyComponentLoader<TComponent extends Component<any>> = ComponentLoader<TComponent> & { type: typeof LAZY_COMPONENT_MARKER };
/** Either an eagerly imported component or a tagged loader for one. */
export type ComponentOrLoader<TComponent extends Component<any>> = TComponent | LazyComponentLoader<TComponent>;

/**
 * Tags a loader so {@link resolveComponent} knows it has to `await` it.
 *
 * The marker is applied in place, so the very same function reference is
 * returned — layout identity therefore stays stable across resolutions, which
 * is what keeps a shared layout mounted while navigating between its pages.
 *
 * @example
 * registrar.group('/admin', ..., {
 *     layout: lazyComponent(async () => (await import('./AdminLayout.svelte')).default)
 * });
 */
export function lazyComponent<TComponent extends Component<any>>(
    loader: ComponentLoader<TComponent>
): LazyComponentLoader<TComponent> {
    return Object.assign(loader, {type: LAZY_COMPONENT_MARKER} as const);
}

export function isLazyComponentLoader(value: unknown): value is LazyComponentLoader<any> {
    return typeof value === 'function' && (value as any).type === LAZY_COMPONENT_MARKER;
}

/**
 * What a loader resolves to, once unwrapped: the component itself, plus the
 * `config` it exported alongside it, if any.
 */
export interface ResolvedComponentModule<TComponent extends Component<any>> {
    component: TComponent;
    /**
     * The module's `config` export — see `routeConfig.ts`'s `configurePage()`
     * / `configureLayout()`.
     *
     * Only present when the loader returned a module namespace (i.e.
     * `async () => import('./X.svelte')`) and that namespace exported one. A
     * loader written as `async () => (await import('./X.svelte')).default`
     * throws the namespace away before this function ever sees it, so there is
     * nothing to read a `config` off of — `RouteRegistrar.ts`'s `config` /
     * `layoutConfig` options are the only way to give that case a loader.
     */
    config?: AnyRouteConfig;
}

/**
 * Caches the in-flight/resolved promise of every loader that has been run
 * through {@link resolveComponentModule}, keyed on the *loader function
 * reference* itself — never on a path or route id.
 *
 * `lazyComponent()` tags a loader in place and hands back the same reference,
 * so a layout shared by several routes (or groups) resolves through this same
 * entry every time. That is what makes the resolved component reference
 * stable across navigations, which in turn is what lets Svelte keep a shared
 * layout mounted while only the page inside it swaps — a fresh reference on
 * every resolve would tear it down and remount it for no reason.
 * {@link resolveComponent} shares this exact promise too (it is
 * `resolveComponentModule(...).then((m) => m.component)`), so that guarantee
 * holds for every caller, not just callers that ask for the whole module.
 *
 * A `WeakMap` so a discarded router (and the loaders it owned) can still be
 * garbage-collected instead of being pinned here forever.
 */
const resolvedComponentCache = new WeakMap<LazyComponentLoader<any>, Promise<ResolvedComponentModule<any>>>();

/**
 * Unwraps a {@link ComponentOrLoader} into its component *and* the
 * `loadData` its module namespace exported, if any. Eager components are
 * returned as-is (without a microtask detour, and with no `loadData` — an
 * already-imported component has no module namespace attached to it here),
 * loaders are awaited and memoised in {@link resolvedComponentCache} so the
 * loader only ever runs once.
 *
 * @param componentOrLoader the eager component or tagged loader to resolve
 * @param describe what the component is, used verbatim in error messages
 *                 (e.g. `route "/admin"` or `layout of route "/admin"`)
 */
export function resolveComponentModule<TComponent extends Component<any>>(
    componentOrLoader: ComponentOrLoader<TComponent>,
    describe: string
): Promise<ResolvedComponentModule<TComponent>> {
    if (!isLazyComponentLoader(componentOrLoader)) {
        return Promise.resolve({component: componentOrLoader as TComponent});
    }

    const cached = resolvedComponentCache.get(componentOrLoader);
    if (cached) {
        return cached as Promise<ResolvedComponentModule<TComponent>>;
    }

    const promise = loadComponentModule(componentOrLoader, describe);
    // Evict on rejection so a transient failure (e.g. a chunk 404 after a
    // redeploy) can be retried instead of being cached forever. Attaching
    // this `.catch()` directly to `promise` (the same object every caller
    // gets back) marks it as handled without consuming the rejection itself —
    // each `await`/`.catch()` a caller attaches is an independent reaction,
    // so the error still propagates to them normally.
    promise.catch(() => resolvedComponentCache.delete(componentOrLoader));

    resolvedComponentCache.set(componentOrLoader, promise);
    return promise as Promise<ResolvedComponentModule<TComponent>>;
}

export type ComponentModuleResolver = typeof resolveComponentModule;

/**
 * Unwraps a {@link ComponentOrLoader} into the component itself, discarding
 * any `loadData` its module namespace exported — a thin convenience over
 * {@link resolveComponentModule} for the (still common) callers that only
 * care about the component. Shares the same cached promise, so the component
 * reference stability {@link resolvedComponentCache}'s doc comment describes
 * applies here too.
 */
export function resolveComponent<TComponent extends Component<any>>(
    componentOrLoader: ComponentOrLoader<TComponent>,
    describe: string
): Promise<TComponent> {
    return resolveComponentModule(componentOrLoader, describe).then((resolved) => resolved.component);
}

async function loadComponentModule<TComponent extends Component<any>>(
    loader: LazyComponentLoader<TComponent>,
    describe: string
): Promise<ResolvedComponentModule<TComponent>> {
    const loaded = await loader();
    if (!loaded) {
        throw new Error(`Lazy loader for ${describe} did not return a component.`);
    }
    if ('default' in loaded && typeof loaded.default === 'function') {
        // A `default` export means the loader returned a whole module
        // namespace (`async () => import('./X.svelte')`) rather than
        // unwrapping it itself — that namespace is the only place a
        // `config` export could live, so this is the one branch where it
        // is looked for at all.
        const config = (loaded as { config?: unknown }).config;
        if (config !== undefined) {
            assertIsRouteConfig(config, describe);
        }
        return {
            component: loaded.default as TComponent,
            config: config as AnyRouteConfig | undefined
        };
    }
    if (typeof loaded === 'function') {
        return {component: loaded as TComponent};
    }

    throw new Error(`Lazy loader for ${describe} returned an invalid component.`);
}
