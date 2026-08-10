import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiDataStores} from '$lib/kernel/extendableTypes.js';
import {useApp} from '$lib/app/hooks/useApp.svelte.js';

/**
 * Hook that gives components access to a registered data store
 * (`app.stores.get(name)`).
 *
 * Data stores are reactive `.svelte.ts` classes that hold state shared
 * across component boundaries (e.g. AI handles, AI models, theme, keychain —
 * see `resources/js/plugins/core/stores/*.svelte.ts`). Each store registers
 * itself into the `HawkiDataStores` map via TypeScript declaration merging
 * (e.g. `interface HawkiDataStores { 'theme': ThemeStore; }`), which is what
 * lets `useStore('theme')` return a fully-typed `ThemeStore` instead of the
 * generic `DataStore` interface. Stores that implement `loadData(app)` have
 * it invoked automatically once, during the bootstrapper's `main` stage.
 *
 * Throws if no store with the given name is registered — check
 * `app.stores.has(name)` first if the store's presence is not guaranteed
 * (e.g. it comes from an optional plugin).
 *
 * @param name The registered store name (a key of `HawkiDataStores`).
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 *
 *     const themeStore = useStore('theme');
 * </script>
 * ```
 */
export function useStore<N extends keyof HawkiDataStores>(name: N): HawkiDataStores[N];
export function useStore(name: string): DataStore {
    const app = useApp();
    return app.stores.get(name);
}
