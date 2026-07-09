import type {AiModel, AiModelParameterKeyType} from '$lib/schemas/resources/ai-models.schema.js';
import type {ModelAspect} from '$lib/components/chat/composer/contexts/aspects/ModelApsect.svelte.js';
import type {CheckpointingInterface} from '$lib/components/chat/composer/contexts/utils/CheckpointingInterface.js';

const defaultParameters: Record<AiModelParameterKeyType, any> = {
    temperature: 0.7,
    top_p: 0.9
};

interface ModelParameterAspectCheckpoint {
    parameters: Record<AiModelParameterKeyType, unknown>;
}

export class ModelParameterAspect implements CheckpointingInterface<ModelParameterAspectCheckpoint> {
    constructor(
        private model: ModelAspect
    ) {
        this.reset();
    }

    private _list = $state({} as NonNullable<AiModel['parameters']>);

    /** Current parameter values being sent with the next request. */
    public get list(): NonNullable<AiModel['parameters']> {
        return this._list;
    }

    /** Defaults declared by the current model definition (from the server). */
    public modelDefaults = $derived.by(() => this.model.current.parameters ?? {});

    /** Effective defaults: model-specific values merged over the global fallbacks
     *  (`temperature=0.7`, `top_p=0.9`). Used by `reset()` and `isModified`. */
    public defaults = $derived.by(() => {
        return {
            ...defaultParameters,
            ...this.modelDefaults
        };
    });

    /** `true` when the current values differ from `modelDefaults` in any key or value.
     *  `ModelAspect.set()` checks this before resetting parameters on a model switch. */
    public isModified = $derived.by(() => {
        const currentKeys = new Set(Object.keys(this.list));
        const defaultKeys = new Set(Object.keys(this.modelDefaults));
        if (currentKeys.size !== defaultKeys.size || ![...currentKeys].every(key => defaultKeys.has(key))) {
            return true;
        }
        let hasNonDefault = false;
        for (const entry of Object.entries(this.list) as [AiModelParameterKeyType, unknown][]) {
            const [key, value] = entry;
            if (value != this.defaults[key]) {
                hasNonDefault = true;
                break;
            }
        }
        return hasNonDefault;
    });

    /** Returns the current value for a parameter, falling back to model defaults then global defaults. */
    public get(param: 'temperature' | 'top_p'): number;
    public get(param: AiModelParameterKeyType): unknown;
    public get(param: AiModelParameterKeyType): unknown {
        return this._list[param] ?? this.modelDefaults[param] ?? defaultParameters[param] ?? null;
    }

    /** Sets a single parameter value. */
    public set(param: 'temperature' | 'top_p', value: number | null): void;
    public set(param: AiModelParameterKeyType, value: unknown): void;
    public set(param: AiModelParameterKeyType, value: unknown): void {
        this._list = {...this._list, [param]: value};
    }

    /** Returns `true` when every key/value pair in `other` matches the current parameter values.
     *  Useful for checking whether a preset is already active. */
    public intersects(other: Partial<Record<AiModelParameterKeyType, unknown>>): boolean {
        for (const [key, value] of Object.entries(other) as [AiModelParameterKeyType, unknown][]) {
            if (this.get(key) !== value) {
                return false;
            }
        }
        return true;
    }

    /** Resets both sampling parameters to the current model's defaults (or global fallbacks `temp=0.7`, `top_p=0.9`). */
    public reset(): void {
        this._list = this.defaults;
    }

    public createCheckpoint(): ModelParameterAspectCheckpoint {
        return {
            parameters: {...this._list}
        };
    }

    public restoreCheckpoint(checkpoint: ModelParameterAspectCheckpoint): void {
        this._list = {...checkpoint.parameters};
    }

}
