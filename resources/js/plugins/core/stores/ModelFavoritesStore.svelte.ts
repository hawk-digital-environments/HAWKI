import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {DataStore} from '$lib/kernel/stores/types.js';

const STORAGE_KEY = 'hawkiFavoriteModels';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'model-favorites': ModelFavoritesStore;
    }
}

/** Browser-persisted favorite model ids used by the experimental picker. */
export class ModelFavoritesStore implements DataStore {
    public readonly name = 'model-favorites';

    public ids = $state<string[]>([]);
    private app: HawkiApp | null = null;

    public async loadData(app: HawkiApp): Promise<void> {
        this.app = app;
        this.ids = parseIds(app.localStorage.getItem(STORAGE_KEY));
    }

    public has(modelId: string): boolean {
        return this.ids.includes(modelId);
    }

    public toggle(modelId: string): void {
        this.ids = this.has(modelId)
            ? this.ids.filter((id) => id !== modelId)
            : [...this.ids, modelId];
        this.app?.localStorage.setItem(STORAGE_KEY, JSON.stringify(this.ids));
    }
}

function parseIds(raw: string | null): string[] {
    try {
        const parsed: unknown = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed)
            ? parsed.filter((value): value is string => typeof value === 'string')
            : [];
    } catch {
        return [];
    }
}
