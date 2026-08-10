import type {HawkiApp, HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {DataStore} from '$lib/kernel/stores/types.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiDataStores} from '$lib/kernel/extendableTypes.js';
import {createStoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        stores: WithoutAppExtensionInternals<StoreExtension>;
    }
}

/**
 * App extension that owns the central registry of `DataStore`s (see
 * `$lib/kernel/stores/types.js`) and drives their data loading.
 *
 * Stores are not auto-discovered here — plugins register them by
 * implementing `HawkiPlugin.stores()` and calling `registrar.add(store)` (see
 * `StoreRegistrar` in `storeRegistrar.js`, and `CorePlugin.stores()` in
 * `resources/js/plugins/core/core.plugin.ts` for a real example). This
 * extension is reachable at runtime as `app.stores`, and its `get()`/`has()`
 * are what the `useStore()` Svelte hook and legacy bridge code use to reach a
 * specific store by name.
 *
 * A store only needs a `loadData(app)` method if it has data that must be
 * fetched/hydrated after boot; stores without one (e.g. purely
 * client-derived state) are registered but never have `loadData` invoked.
 */
export class StoreExtension implements HawkiAppExtension {
    private readonly stores = new Map<string, DataStore>();

    /** Names of all registered data stores. */
    public get names(): string[] {
        return Array.from(this.stores.keys());
    }

    /** All registered `DataStore` instances. */
    public get all(): DataStore[] {
        return Array.from(this.stores.values());
    }

    /** Whether a store with the given name is registered. */
    public has(name: string): boolean {
        return this.stores.has(name);
    }

    /**
     * Looks up a registered store by name, throwing if it isn't registered.
     * Pass a name from {@link HawkiDataStores} (e.g. `'theme'`) to get the
     * concrete, typed store class instead of the generic `DataStore`:
     * @example
     * const theme = app.stores.get('theme'); // typed as ThemeStore
     */
    public get<N extends keyof HawkiDataStores>(name: N): HawkiDataStores[N];
    public get(name: string): DataStore;
    public get(name: string): DataStore {
        const store = this.stores.get(name);
        if (!store) {
            throw new Error(`Data store with name "${name}" is not registered.`);
        }
        return store;
    }

    /** Lets every plugin register its stores (via `plugin.stores()`) into this extension's registry. */
    public async init(app: UnfinishedHawkiApp) {
        const registrar = createStoreRegistrar(this.stores);
        await app.getOrFail('plugins').bootstrapper.runStores(registrar);
    }

    /**
     * As soon as the `main` bootstrap stage is reached, schedules a
     * `store.loadData(app)` call (still within the `main` stage, so it runs
     * concurrently with other `main`-stage work) for every registered store
     * that implements `loadData`. Stores without `loadData` are skipped.
     */
    public ready(app: HawkiApp, bootstrapper: Bootstrapper) {
        bootstrapper.onStageReached('main', async () => {
            for (const store of this.stores.values()) {
                if (typeof store.loadData !== 'function') {
                    continue;
                }
                bootstrapper.onMainStage(() => store.loadData!(app));
            }
        });
    }

    /** Exposes this extension as `app.stores`. */
    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get stores() {
                return extension;
            }
        };
    }
}
