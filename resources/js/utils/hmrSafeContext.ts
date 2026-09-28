import {getContext, hasContext, setContext} from 'svelte';

/**
 * Drop-in replacement for Svelte's `createContext()` whose key survives Vite
 * HMR.
 *
 * `createContext()` binds its `[get, set]` pair to a fresh object key on every
 * module evaluation. During development an edit anywhere in a context module's
 * import graph bubbles up through non-boundary `.ts` files and re-evaluates
 * the module — consumers rendered afterwards then look up a *different* key
 * than the one the still-mounted provider registered, and every `get()` dies
 * with Svelte's `missing_context` error until the page is fully reloaded
 * (seen e.g. as "Error while rendering route component" from `RouterView`).
 *
 * `Symbol.for(key)` resolves to the same symbol on every evaluation, so
 * provider and consumers stay connected across HMR updates. Semantics match
 * `createContext()`: the getter throws when no ancestor has set the context.
 *
 * @param key Globally unique context name, e.g. `'hawki.chat.composer-context'`.
 */
export function createHmrSafeContext<T>(key: string): [() => T, (value: T) => T] {
    const contextKey = Symbol.for(key);
    return [
        () => {
            if (!hasContext(contextKey)) {
                throw new Error(`Context "${key}" was not set in a parent component.`);
            }
            return getContext<T>(contextKey);
        },
        (value: T) => setContext(contextKey, value)
    ];
}
