import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiDataStores} from '$lib/kernel/extendableTypes.js';
import {useApp} from '$lib/app/hooks/useApp.svelte.js';

export function useStore<N extends keyof HawkiDataStores>(name: N): HawkiDataStores[N];
export function useStore(name: string): DataStore {
    const app = useApp();
    return app.stores.get(name);
}
