import {getContext, hasContext, setContext} from 'svelte';

/**
 * Creates a `[get, set]` Svelte context pair whose key identity is stable
 * across module (re-)evaluations — provider and consumers stay connected no
 * matter how often the defining module runs.
 *
 * A plain module-level `Symbol()` key is minted fresh on every evaluation of
 * its module. That is invisible in production (modules evaluate exactly
 * once), but during development an edit anywhere in a context module's
 * import graph bubbles up through non-boundary `.ts` files and re-evaluates
 * the module — a still-mounted provider has then registered a *different*
 * key than freshly rendered consumers look up, and every `get()` fails until
 * the page is fully reloaded (seen e.g. as "Error while rendering route
 * component" from `RouterView`).
 *
 * `Symbol.for(key)` resolves through the global symbol registry instead, so
 * the key is the same on every evaluation and the pairing survives. The
 * registry key must therefore be globally unique, e.g.
 * `'hawki.chat.composer-context'`.
 *
 * The getter throws an actionable error when no ancestor has set the context
 * (Svelte's `getContext` itself would return `undefined` and crash further
 * downstream).
 */
export function createStableContext<T>(key: string): [() => T, (value: T) => T] {
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
