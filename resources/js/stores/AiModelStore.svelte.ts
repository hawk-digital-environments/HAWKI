import {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import {getResourceCollectionFromApi} from '$lib/data/api/api.js';
import type {WellKnownSystemModelType} from '$lib/schemas/resources/system-models.schema.js';
import {getConnection} from '$lib/data/connection/connection.js';

/**
 * Reactive store for all available AI models and their system-role assignments.
 *
 * Populated by {@link loadAiModels} during bootstrap (authenticated connections only).
 * Access via the `aiModelStore` singleton — no prop-drilling needed.
 *
 * @example
 * // List all models in a picker
 * import {aiModelStore} from '$lib/stores/AiModelStore.svelte.js';
 * const models = $derived(aiModelStore.models);
 *
 * @example
 * // Look up which model owns the "default" system role
 * const model = aiModelStore.getSystemModelByType('default');
 */
export class AiModelStore {
    /** All available AI models, in API order. Use for pickers or capability checks. */
    public models = $state([] as AiModel[]);
    private modelMap = $derived.by(() => {
        const map = new Map<string, AiModel>();
        aiModelStore.models.forEach(model => map.set(model.model_id, model));
        return map;
    });

    /**
     * Map of system role type → resolved AiModel. Falls back to the first available
     * model when the configured model ID no longer exists on the server.
     * Prefer {@link getSystemModelByType} over direct property access.
     */
    public systemModels = $state({} as Record<string, AiModel>);

    public getOneById(modelId: AiModel | string | number): AiModel | null {
        if (!modelId) {
            return null;
        }
        if ((modelId as AiModel).model_id && this.modelMap.has((modelId as AiModel).model_id)) {
            return this.modelMap.get((modelId as AiModel).model_id)!;
        }
        if (typeof modelId === 'number' || !isNaN(Number(modelId))) {
            const numericId = Number(modelId);
            return this.models.find(m => m.id === String(numericId)) ?? null;
        }
        return this.modelMap.get(String(modelId)) ?? null;
    }

    public getModelByIdOrFallback(modelId: AiModel | string | number | null, fallbackType: WellKnownSystemModelType = 'default'): AiModel {
        return this.getOneById(modelId ?? '')
            ?? this.getSystemModelByType(fallbackType)
            ?? this.models[0];
    }

    public getSystemModelByType(type: WellKnownSystemModelType | string): AiModel | null {
        return this.systemModels[type] ?? null;
    }
}

export const aiModelStore = new AiModelStore();

/**
 * Fetches all AI models and system-model assignments from the API and populates
 * {@link aiModelStore}. Called during bootstrap.
 * No-ops for unauthenticated connections.
 */
export async function loadAiModels() {
    if (getConnection().type !== 'internal_authenticated') {
        return;
    }

    const [aiModels, systemModels] = await Promise.all([
        getResourceCollectionFromApi('ai-models', {query: {include: 'provider'}}),
        getResourceCollectionFromApi('system-models')
    ]);

    aiModelStore.models = aiModels;

    // We want to be able to easily access system models by their usage type, so we create a map here.
    aiModelStore.systemModels = systemModels.reduce((map, model) => {
        map[model.model_type] = aiModels.find(m => m.model_id === model.model_id)
            ?? aiModels[0]; // Fallback to the first model if the configured model is not found, to avoid breaking the system.
        return map;
    }, {} as Record<string, AiModel>);
}
