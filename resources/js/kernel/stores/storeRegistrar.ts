import type {DataStore} from '$lib/kernel/stores/types.js';

export function createStoreRegistrar(
    stores: Map<string, DataStore>
) {
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

export type StoreRegistrar = ReturnType<typeof createStoreRegistrar>;
