import type {OldUiConversationMessage} from '$lib/oldUi/OldUiBridge.svelte.js';
import {type ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {AiModelStore} from '$lib/stores/AiModelStore.svelte.js';
import type {AiToolStore} from '$lib/stores/AiToolStore.svelte.js';
import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import type {DisabledChatFeature} from '$lib/components/chat/composer/contexts/aspects/GuardAspect.svelte.js';
import type {ChatDefaultModeState} from '$lib/components/chat/composer/contexts/modes/ChatDefaultMode.js';
import {AbstractMode} from '$lib/components/chat/composer/contexts/modes/contracts/AbstractMode.js';
import {__} from '$lib/utils/translator.js';

export interface ChatRegenModeState {
    messageId: string;
    originalMessage: string;
}

/**
 * Mode for regenerating an assistant reply.
 *
 * Pre-fills the model and sampling parameters from the original message's
 * metadata so the regenerated reply uses the same configuration. If the
 * original model is no longer available, falls back to the default and shows
 * an info toast. Locks attachments, the message input, and suggestions — only
 * model and parameter selection are exposed. Exits after send.
 */
export class ChatRegenMode extends AbstractMode<OldUiConversationMessage, ChatRegenModeState> {
    constructor(
        private modelStore: AiModelStore,
        private toolStore: AiToolStore,
        private toast: ToastContext
    ) {
        super();
    }

    public allowsNestedModes(): boolean {
        return false;
    }

    public canSend(context: ComposerContext, state: ChatRegenModeState): boolean {
        return true;
    }

    public exitAfterSend(context: ComposerContext, state: ChatDefaultModeState): boolean {
        return true;
    }

    public disablesUiFeature(feature: DisabledChatFeature): boolean {
        return ['attachments', 'input', 'suggestions'].includes(feature);
    }

    public canEnter(context: ComposerContext, data: OldUiConversationMessage): boolean | string {
        if (data.message_role !== 'assistant') {
            return __('chat.composer.regen.onlyAssistantMessages');
        }

        return true;
    }

    public enter(context: ComposerContext, data: OldUiConversationMessage): ChatRegenModeState {
        context.reset();

        if (data.model) {
            let model = this.modelStore.getModelByIdOrFallback(data.model);
            if (model.model_id !== data.model) {
                this.toast.info(__('chat.composer.regen.modelNotAvailable', {model: data.model, fallback: model.label}));
            }

            context.model.set(model);
        }

        if (typeof data.metadata?.params === 'object' && data.metadata?.params) {
            const params = data.metadata.params;
            if (typeof params.temperature === 'number') {
                context.modelParameters.set('temperature', params.temperature);
            }
            if (typeof params.top_p === 'number') {
                context.modelParameters.set('top_p', params.top_p);
            }
        }

        if (Array.isArray(data.metadata?.tools)) {
            data.metadata.tools.forEach((toolId: string) => {
                const tool = this.toolStore.getOneByName(toolId);
                if (!tool) {
                    this.toast.info(__('chat.composer.regen.toolNotAvailable', {tool: toolId}));
                    return;
                }
                if (!this.toolStore.isAvailableForModel(tool!, context.model.current)) {
                    this.toast.info(__('chat.composer.regen.toolNotAvailableForModel', {tool: toolId}));
                    return;
                }
                context.tools.add(tool);
            });
        }

        context.message = 'dummy';
        context.focusInput();

        return {
            messageId: data.message_id,
            originalMessage: data.content.text
        };
    }

    public exit(context: ComposerContext, state: ChatRegenModeState): void {
    }

}
