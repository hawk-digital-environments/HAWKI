import type {HawkiApp} from '$lib/kernel/HawkiApp.js';

export interface DataStore {
    readonly name: string;

    loadData?(app: HawkiApp): Promise<void>;
}
