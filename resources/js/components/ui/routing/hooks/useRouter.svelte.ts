/**
 * Component-side access to a `RouterHandle` published into Svelte context by
 * `RouterView` (see `router.svelte.ts` for what a `RouterHandle` exposes).
 *
 * Supports multiple routers on the same page (e.g. a nested "app inside an
 * app" scenario): {@link provideDefaultRouterName} lets an ancestor pin which
 * router name `useRouter()` resolves to when no explicit name is given,
 * falling back to `'app'` when nothing pinned it.
 */
import {createContext, getContext} from 'svelte';
import {getRouterContextName, type RouterHandle} from '../logistics/router.svelte.js';

const [getDefaultRouterName, setDefaultRouterName] = createContext<string>();

/**
 * Reads the `RouterHandle` of the router named `name` from context. Falls
 * back to the name pinned by the nearest {@link provideDefaultRouterName}
 * ancestor, or `'app'` if none pinned one. Throws if no `RouterView` for that
 * name is mounted above the caller.
 *
 * Must be called during component initialization (`getContext` requirement),
 * not inside an event handler or `$effect`.
 */
export function useRouter(name?: string): RouterHandle {
    try {
        name = name ?? getDefaultRouterName();
    } catch (error) {
        name = name ?? 'app';
    }
    const routerHandle = getContext<RouterHandle>(getRouterContextName(name));
    if (!routerHandle) {
        throw new Error(`Router context not found for name: ${name ?? 'app'}`);
    }
    return routerHandle;
}

/**
 * Pins the router name that {@link useRouter} resolves to by default for
 * every descendant that doesn't pass an explicit name. Call during
 * component initialization, above the subtree that should inherit it.
 */
export function provideDefaultRouterName(name: string): void {
    setDefaultRouterName(name);
}
