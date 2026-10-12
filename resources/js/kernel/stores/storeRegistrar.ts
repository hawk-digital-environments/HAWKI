import type {DataStore} from '$lib/kernel/stores/types.js';

/**
 * Builds the registrar object that plugins use, inside their `stores()`
 * lifecycle hook, to add `DataStore` instances into `StoreExtension`'s shared
 * `stores` map. `StoreExtension` constructs one of these (wrapping its own
 * internal map) and passes it to `PluginBootstrapper.runStores()`, which
 * hands it to every plugin's `stores()` in turn — plugins never see or touch
 * the map directly.
 *
 * @example
 * // Inside a plugin's `stores()` hook:
 * public stores({add}: StoreRegistrar): void {
 *     add(new AiModelStore());
 * }
 */
export function createStoreRegistrar(
    stores: Map<string, DataStore>
) {
    /** Registers a store; throws if its `name` is missing/blank or already taken by another store. */
    function add(store: DataStore) {
        if (typeof store.name !== 'string' || store.name.trim() === '') {
            throw new Error(`Data store does not have a valid 'name' property.`);
        }

        if (stores.has(store.name)) {
            throw new Error(`Data store with name "${store.name}" is already registered.`);
        }

        stores.set(store.name, store);
    }

    return {
        add
    };
}

/** The registrar type handed to `HawkiPlugin.stores(registrar, context)`. */
export type StoreRegistrar = ReturnType<typeof createStoreRegistrar>;
