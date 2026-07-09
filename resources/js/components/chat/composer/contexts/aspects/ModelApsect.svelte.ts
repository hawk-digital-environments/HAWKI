import type {AiModelStore} from '$lib/stores/AiModelStore.svelte.js';
import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import type {ModelParameterAspect} from '$lib/components/chat/composer/contexts/aspects/ModelParameterAspect.svelte.js';
import type {CheckpointingInterface} from '$lib/components/chat/composer/contexts/utils/CheckpointingInterface.js';

interface ModelAspectCheckpoint {
    currentModelId: string;
}

export class ModelAspect implements CheckpointingInterface<ModelAspectCheckpoint> {
    constructor(
        private modelStore: AiModelStore,
        private parameterAspectProvider: () => ModelParameterAspect,
        private onUpdateCurrentModel: (model: AiModel) => void
    ) {
        this._current = $state(modelStore.getSystemModelByType('default')!);
    }

    private _current: AiModel;

    /** Shorthand for `model.settings.file_upload`. Use to show/hide the attachment button. */
    public allowsFileUpload = $derived.by(() => this.current?.settings?.file_upload as boolean | undefined ?? false);

    /** Shorthand for `model.settings.tool_calling`. Use to show/hide the tool menu. */
    public allowsToolCalling = $derived.by(() => this.current?.settings?.tool_calling as boolean | undefined ?? false);

    private get parameterContext(): ModelParameterAspect {
        return this.parameterAspectProvider();
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
     *  Used by `ModelUsageAspect` to detect when image attachments would be incompatible. */
    public hasVision = $derived.by(() => {
        if (!this.current) {
            return false;
        }
        return this.current.input.includes('image') && this.current.settings?.file_upload;
    });

    public createCheckpoint(): ModelAspectCheckpoint {
        return {
            currentModelId: this.current.model_id
        };
    }

    public restoreCheckpoint(checkpoint: ModelAspectCheckpoint): void {
        this.set(checkpoint.currentModelId);
    }
}
