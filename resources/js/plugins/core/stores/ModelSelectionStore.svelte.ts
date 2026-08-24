import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {DataStore} from '$lib/kernel/stores/types.js';

const STORAGE_KEY = 'hawkiSelectedModel';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'model-selection': ModelSelectionStore;
    }
}

/**
 * Browser-persisted id of the last AI model the user selected in the composer.
 * Read by `createComposerContext` so a freshly built composer (new chat,
 * conversation switch, page reload) starts on the user's last choice instead
 * of falling back to the system default model.
 */
export class ModelSelectionStore implements DataStore {
    public readonly name = 'model-selection';

    public modelId = $state<string | null>(null);
    private app: HawkiApp | null = null;

    public async loadData(app: HawkiApp): Promise<void> {
        this.app = app;
        this.modelId = app.localStorage.getItem(STORAGE_KEY) || null;
    }

    public remember(modelId: string): void {
        if (this.modelId === modelId) {
            return;
        }
        this.modelId = modelId;
        this.app?.localStorage.setItem(STORAGE_KEY, modelId);
    }
}
