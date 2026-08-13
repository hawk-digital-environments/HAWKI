/**
 * Shared plumbing for "a component, or a function that imports one".
 *
 * A Svelte 5 `Component` *is* a function, so a loader cannot be told apart from
 * an already-imported component by looking at it. Everything that accepts both
 * therefore requires loaders to be tagged with {@link LAZY_COMPONENT_MARKER} —
 * either by {@link lazyComponent} or by `RouteRegistrar.lazyRoute()`.
 */
import type {Component} from 'svelte';

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
 * Unwraps a {@link ComponentOrLoader} into the component itself. Eager
 * components are returned as-is (without a microtask detour), loaders are
 * awaited and unwrapped from their `default` export if they have one.
 *
 * @param componentOrLoader the eager component or tagged loader to resolve
 * @param describe what the component is, used verbatim in error messages
 *                 (e.g. `route "/admin"` or `layout of route "/admin"`)
 */
export async function resolveComponent<TComponent extends Component<any>>(
    componentOrLoader: ComponentOrLoader<TComponent>,
    describe: string
): Promise<TComponent> {
    if (!isLazyComponentLoader(componentOrLoader)) {
        return componentOrLoader as TComponent;
    }

    const loaded = await componentOrLoader();
    if (!loaded) {
        throw new Error(`Lazy loader for ${describe} did not return a component.`);
    }
    if ('default' in loaded && typeof loaded.default === 'function') {
        return loaded.default as TComponent;
    }
    if (typeof loaded === 'function') {
        return loaded as TComponent;
    }

    throw new Error(`Lazy loader for ${describe} returned an invalid component.`);
}
