import type {ModelSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelSlice.svelte.js';
import type {CheckpointingInterface} from '$plugins/core/modules/chat/components/composer/contexts/utils/CheckpointingInterface.js';
import {type AiModel, AiModelParameterKeyType} from '$plugins/core/schemas/resources/ai-models.schema';

const defaultParameters: Record<AiModelParameterKeyType, any> = {
    temperature: 0.7,
    top_p: 0.9
};

interface ModelParameterSliceCheckpoint {
    parameters: Record<AiModelParameterKeyType, unknown>;
}

interface ModelParameterSliceCheckpoint {
    parameters: Record<AiModelParameterKeyType, unknown>;
}

/**
 * Composer slice that owns the AI sampling parameters (e.g. `temperature`, `top_p`)
 * sent with the next request.
 *
 * Values flow through three layers of defaults: the global fallbacks declared
 * above (`temperature=0.7`, `top_p=0.9`), then the current model's own
 * `parameters` (`modelDefaults`), then the user's overrides (`list`). `get()`
 * resolves a single parameter through that chain; `isModified` reports whether
 * the user has strayed from the model's defaults, which {@link ModelSlice.set}
 * checks to decide whether to reset parameters on a model switch.
 *
 * Implements {@link CheckpointingInterface} so a mode can snapshot and restore
 * the user's parameter choices.
 */
export class ModelParameterSlice implements CheckpointingInterface<ModelParameterSliceCheckpoint> {
    constructor(
        private model: ModelSlice
    ) {
        this.reset();
    }

    private _list = $state({} as NonNullable<AiModel['parameters']>);

    /** Current parameter values being sent with the next request. */
    public get list(): NonNullable<AiModel['parameters']> {
        return this._list;
    }

    /** Defaults declared by the current model definition (from the server). */
    public modelDefaults = $derived.by(() => this.model.current?.parameters ?? {});

    /** Effective defaults: model-specific values merged over the global fallbacks
     *  (`temperature=0.7`, `top_p=0.9`). Used by `reset()` and `isModified`. */
    public defaults = $derived.by(() => {
        return {
            ...defaultParameters,
            ...this.modelDefaults
        };
    });

    /** `true` when the current values differ from `modelDefaults` in any key or value.
     *  `ModelSlice.set()` checks this before resetting parameters on a model switch. */
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

    public createCheckpoint(): ModelParameterSliceCheckpoint {
        return {
            parameters: {...this._list}
        };
    }

    public restoreCheckpoint(checkpoint: ModelParameterSliceCheckpoint): void {
        this._list = {...checkpoint.parameters};
    }

}
