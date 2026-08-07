import type {HawkiApp, HawkiAppAspect, UnfinishedHawkiApp, WithoutAppAspectInternals} from '$lib/kernel/HawkiApp.js';
import type {DataStore} from '$lib/kernel/stores/types.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiDataStores} from '$lib/kernel/extendableTypes.js';
import {createStoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppAspects {
        stores: WithoutAppAspectInternals<StoreAspect>;
    }
}

export class StoreAspect implements HawkiAppAspect {
    private readonly stores = new Map<string, DataStore>();

    public get names(): string[] {
        return Array.from(this.stores.keys());
    }

    public get all(): DataStore[] {
        return Array.from(this.stores.values());
    }

    public has(name: string): boolean {
        return this.stores.has(name);
    }

    public get<N extends keyof HawkiDataStores>(name: N): HawkiDataStores[N];
    public get(name: string): DataStore;
    public get(name: string): DataStore {
        const store = this.stores.get(name);
        if (!store) {
            throw new Error(`Data store with name "${name}" is not registered.`);
        }
        return store;
    }

    public async init(app: UnfinishedHawkiApp) {
        const registrar = createStoreRegistrar(this.stores);
        await app.getOrFail('plugins').bootstrapper.runStores(registrar);
    }

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

    public provideProperties(): Record<string, any> {
        const aspect = this;
        return {
            get stores() {
                return aspect;
            }
        };
    }
}
