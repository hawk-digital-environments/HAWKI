import type {ToolSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ToolSlice.svelte.js';
import type {AttachmentSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/AttachmentSlice.svelte.js';
import type {ModelSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelSlice.svelte.js';
import type {GuardSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/GuardSlice.svelte.js';
import type {AiToolOrCapabilityWithState} from '$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
import type {AiModelStore} from '$plugins/core/stores/AiModelStore.svelte.js';
import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

/**
 * Describes why a particular model cannot be used given the current chat state.
 */
export interface ModelUsageIssue {
    type: 'no_tool_calling' | 'no_file_upload' | 'no_vision' | 'missing_tools';
    missingTools?: AiToolOrCapabilityWithState[];
}

/**
 * Read-only composer slice that derives whether the currently selected model is
 * compatible with the active tools and attachments.
 *
 * Holds no state of its own — every property is `$derived` from the
 * {@link ModelSlice}, {@link ToolSlice}, {@link AttachmentSlice}, and
 * {@link GuardSlice}. It centralises the "can the user send right now?" check:
 * {@link isValid} reports whether the current model appears in {@link allUsable},
 * and {@link issues} lists the concrete reasons it can't (e.g. the model lacks
 * tool calling while tools are active, or lacks vision input while images are
 * attached). The UI uses these to disable the send button and show actionable
 * guidance. When the active {@link GuardSlice} hides AI UI elements or disables
 * model selection (e.g. inside certain modes), checks are short-circuited and
 * no issues are reported.
 */
export class ModelUsageSlice {
    constructor(
        private modelStore: AiModelStore,
        private model: ModelSlice,
        private tools: ToolSlice,
        private attachments: AttachmentSlice,
        private guard: GuardSlice
    ) {
    }

    /**
     * `true` when the current model appears in `usableModels`.
     * Use this to disable the send button — it turns `false` when the user
     * has added tools or attachments that the selected model doesn't support.
     */
    public isValid = $derived.by(() => {
        return this.allUsable.some(model => model.model_id === this.model.current.model_id);
    });

    /**
     * The specific reasons the current model cannot be used given the active tools and attachments.
     * Empty array means the model is compatible. Show these to help the user understand why
     * the send button is disabled and which model to switch to.
     */
    public issues = $derived.by(() => this.model.current ? this.getModelUsageIssues(this.model.current) : []);

    /**
     * The subset of all models that are compatible with the current `activeTools` and `attachments`.
     * Use this to populate a "suggested models" list when `currentModelCanBeUsed` is `false`.
     */
    public allUsable = $derived.by(() => {
        return this.modelStore.models.filter(model => this.getModelUsageIssues(model).length === 0);
    });

    private getModelUsageIssues(model: AiModel): ModelUsageIssue[] {
        const issues: ModelUsageIssue[] = [];

        if (!this.guard.showsAiUiElements) {
            // If the guard doesn't show any AI UI elements, we don't want to show any issues related to the model choice,
            // since the user didn't choose it and can't change it.
            return issues;
        }

        if (this.guard.disablesFeature('models')) {
            // If the mode disables model selection, we don't want to show any issues related to the model choice,
            // since the user didn't choose it and can't change it.
            return issues;
        }

        if (this.tools.active.length > 0) {
            if (!model.settings?.tool_calling) {
                issues.push({type: 'no_tool_calling'});
            } else {
                const missingTools = this.tools.active
                    .filter(tool => !tool.isAvailableFor(model));
                if (missingTools.length > 0) {
                    issues.push({type: 'missing_tools', missingTools});
                }
            }
        }

        if (this.attachments.list.length > 0 && !model.settings?.file_upload) {
            issues.push({type: 'no_file_upload'});
        }

        if (this.attachments.hasImages && !model.input.includes('image')) {
            issues.push({type: 'no_vision'});
        }

        return issues;
    }
}
