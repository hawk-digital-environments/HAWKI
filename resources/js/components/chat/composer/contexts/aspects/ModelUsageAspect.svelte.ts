import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import type {AiModelStore} from '$lib/stores/AiModelStore.svelte.js';
import type {ToolAspect} from '$lib/components/chat/composer/contexts/aspects/ToolAspect.svelte.js';
import type {AttachmentAspect} from '$lib/components/chat/composer/contexts/aspects/AttachmentAspect.svelte.js';
import type {ModelAspect} from '$lib/components/chat/composer/contexts/aspects/ModelApsect.svelte.js';
import type {GuardAspect} from '$lib/components/chat/composer/contexts/aspects/GuardAspect.svelte.js';
import type {AiToolOrCapabilityWithState} from '$lib/components/chat/composer/contexts/aspects/toolAspectData.js';

/**
 * Describes why a particular model cannot be used given the current chat state.
 */
export interface ModelUsageIssue {
    type: 'no_tool_calling' | 'no_file_upload' | 'no_vision' | 'missing_tools';
    missingTools?: AiToolOrCapabilityWithState[];
}

export class ModelUsageAspect {
    constructor(
        private modelStore: AiModelStore,
        private model: ModelAspect,
        private tools: ToolAspect,
        private attachments: AttachmentAspect,
        private guard: GuardAspect
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
