import type {OldUiConversationMessage} from '$lib/oldUi/OldUiBridge.svelte.js';
import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {DisabledChatFeature} from '$lib/components/chat/composer/contexts/aspects/GuardAspect.svelte.js';
import {AbstractMode} from '$lib/components/chat/composer/contexts/modes/contracts/AbstractMode.js';
import {RemoteFile} from '$lib/components/chat/composer/utils/RemoteFile.js';

export interface ChatEditModeState {
    messageId: string;
    originalMessage: string;
    originalAttachments: string[];
}

/**
 * Mode for editing an existing user message.
 *
 * Pre-fills the composer with the original message text and its attachments
 * (reconstructed as `RemoteFile` references to already-uploaded files). Locks
 * model, settings, and tools UI to prevent changes that would make the edited
 * message inconsistent with the original. Blocks send until the user actually
 * changes the text or attachments.
 */
export class ChatEditMode extends AbstractMode<OldUiConversationMessage, ChatEditModeState> {
    public canSend(context: ComposerContext, state: ChatEditModeState): boolean {
        return (context.message.trim() !== state.originalMessage.trim())
            || (context.attachments.list.length !== state.originalAttachments.length)
            || (context.attachments.list.some(att => !state.originalAttachments.includes(context.attachments.getAssignedUuid(att) ?? '')));
    }

    public disablesUiFeature(feature: DisabledChatFeature): boolean {
        return ['models', 'settings', 'tools'].includes(feature);
    }

    public enter(context: ComposerContext, data: OldUiConversationMessage): ChatEditModeState {
        if (data.message_role === 'assistant') {
            throw new Error('Editing assistant messages is not supported');
        }

        context.reset();

        const originalAttachments = [] as string[];
        if (Array.isArray(data.content.attachments)) {
            data.content.attachments.forEach(att => {
                if (!att.fileData) {
                    return;
                }

                // Create a file object by the already uploaded url:
                const file = new RemoteFile(att.fileData.url, att.fileData.name, att.fileData.mime);
                context.attachments.add(file);
                context.attachments.assignUuid(file, att.fileData.uuid);
                originalAttachments.push(att.fileData.uuid);
            });
        }

        context.message = data.content.text;
        context.focusInput();

        return {
            messageId: data.message_id,
            originalMessage: data.content.text,
            originalAttachments
        };
    }
}
