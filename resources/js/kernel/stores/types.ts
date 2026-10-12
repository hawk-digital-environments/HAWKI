import type {HawkiApp} from '$lib/kernel/HawkiApp.js';

/**
 * Contract a Svelte store class must implement to be registered with
 * `StoreExtension` (via a plugin's `stores()` hook and `StoreRegistrar.add()`).
 *
 * `name` is the registry key other code uses to look the store up — e.g.
 * `app.stores.get('theme')` or `useStore('theme')` — and must be unique
 * across all plugins; augment {@link HawkiDataStores} with it so lookups are
 * typed. See `resources/js/plugins/core/stores/AiModelStore.svelte.ts` for a
 * concrete `DataStore` implementation.
 *
 * `init` is optional: `StoreExtension` calls it synchronously as soon as the
 * app is assembled, *before* any bootstrap stage runs. Use it for cheap,
 * request-free setup that must be available even when boot stalls in an
 * early stage (e.g. the legacy handshake page parks the `migration` stage
 * until a passkey is entered, yet still calls into the keychain store).
 *
 * `loadData` is optional: implement it only if the store has data that needs
 * to be fetched/hydrated once the app has booted. `StoreExtension` calls it
 * automatically for every store that defines it, once the `main` bootstrap
 * stage is reached — stores without it are simply never called.
 */
export interface DataStore {
    readonly name: string;

    ready?(app: HawkiApp): void;

    loadData?(app: HawkiApp): Promise<void>;
}
