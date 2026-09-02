import type {ModelParameterSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelParameterSlice.svelte.js';
import type {CheckpointingInterface} from '$plugins/core/modules/chat/components/composer/contexts/utils/CheckpointingInterface.js';
import {AiModelStore} from '$plugins/core/stores/AiModelStore.svelte';
import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

interface ModelSliceCheckpoint {
    currentModelId: string;
}

interface ModelSliceCheckpoint {
    currentModelId: string;
}

/**
 * Composer slice that owns the currently selected AI model.
 *
 * Holds the single source of truth for "which model will receive the next message"
 * (`current`) and exposes derived shorthands for the model's capability flags
 * (`allowsFileUpload`, `allowsToolCalling`, `hasVision`) so UI elements like the
 * attachment button or tool menu can hide themselves without each re-reading the
 * model settings. `set()` is the write surface — it resolves the value through
 * `AiModelStore.getModelByIdOrFallback`, notifies the context via the
 * `onUpdateCurrentModel` callback, and resets sampling parameters on the
 * {@link ModelParameterSlice} when the user hadn't customised them.
 *
 * Implements {@link CheckpointingInterface} so a mode (e.g. edit) can
 * snapshot the selected model and restore it on exit.
 */
export class ModelSlice implements CheckpointingInterface<ModelSliceCheckpoint> {
    constructor(
        private modelStore: AiModelStore,
        private parameterSliceProvider: () => ModelParameterSlice,
        private onUpdateCurrentModel: (model: AiModel) => void,
        initialModel: AiModel | null = null
    ) {
        this._current = $state(initialModel ?? modelStore.getSystemModelByType('default')!);
    }

    private _current: AiModel;

    /** Shorthand for `model.settings.file_upload`. Use to show/hide the attachment button. */
    public allowsFileUpload = $derived.by(() => this.current?.settings?.file_upload as boolean | undefined ?? false);

    /** Shorthand for `model.settings.tool_calling`. Use to show/hide the tool menu. */
    public allowsToolCalling = $derived.by(() => this.current?.settings?.tool_calling as boolean | undefined ?? false);

    private get parameterContext(): ModelParameterSlice {
        return this.parameterSliceProvider();
    }

    /** The currently selected AI model. */
    public get current(): AiModel {
        return this._current;
    }

    /**
     * Selects the active model. Accepts:
     * - An `AiModel` object
     * - A `model_id` string (e.g. `'gpt-4o'`)
     * - A numeric string or number matching the model's integer `id` field
     * - `null` to fall back to the "default" system model
     *
     * When switching models, existing sampling parameters are preserved only if the
     * user had already customised them (`hasNonDefaultParameters`). Otherwise they
     * are reset to the new model's defaults.
     */
    public set(model: AiModel | string | number | null): void {
        const hadNonDefaultParametersBefore = this.parameterContext.isModified;
        this._current = this.modelStore.getModelByIdOrFallback(model);
        this.onUpdateCurrentModel(this.current);
        // To be extra sure I add a tiny delay before resetting the parameters
        // Theoretically it is not needed, but since we are connecting to the legacy ui here,
        // better safe than sorry.
        setTimeout(() => {
            // Reset to the new defaults, if the parameters were not customized before.
            // If the user has customized the parameters, we keep them as they are, even if the new model has different defaults.
            if (!hadNonDefaultParametersBefore) {
                this.parameterContext.reset();
            }
        }, 10);
    }

    /** `true` when the model accepts file uploads AND lists `'image'` as a supported input type.
     *  Used by `ModelUsageSlice` to detect when image attachments would be incompatible. */
    public hasVision = $derived.by(() => {
        if (!this.current) {
            return false;
        }
        return this.current.input.includes('image') && this.current.settings?.file_upload;
    });

    public createCheckpoint(): ModelSliceCheckpoint {
        return {
            currentModelId: this.current.model_id
        };
    }

    public restoreCheckpoint(checkpoint: ModelSliceCheckpoint): void {
        this.set(checkpoint.currentModelId);
    }
}
