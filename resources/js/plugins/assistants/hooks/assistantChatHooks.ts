import type {ChatSendContext, ChatSendDescriptor} from '$plugins/core/modules/chat/hooks/chatSendHooks.js';
import type {ChatWelcomeContext, ChatWelcomeSection} from '$plugins/core/modules/chat/hooks/chatWelcomeHooks.js';
import type {ChatConversation} from '$plugins/core/modules/chat/types.js';
import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
import type {Assistant} from '$plugins/assistants/types/assistant';
import {assistantRowAppearance} from '$plugins/assistants/utils/assistantRowAppearance';
import {assistantHandlesStore} from '$plugins/assistants/stores/AssistantHandlesStore.svelte.js';

/**
 * Chat integration of the assistants plugin, registered as handlers for the
 * chat module's `chatSend` and `chatWelcome` hooks (see `assistants.plugin.ts`).
 *
 * When the composer addresses one of the user's assistants via its `@handle`
 * — or the opened conversation is already bound to one
 * (`ai_convs.assistant_handle`) — the send is pinned to that assistant:
 *
 * - the conversation is created with the assistant binding,
 * - model/tools/params of the run (and the persisted message metadata) come
 *   from the assistant (the model only stays user-selected when the
 *   assistant allows it),
 * - the streamed response shows the assistant's name as its author,
 * - the welcome hero shows the assistant's name, greeting and starter
 *   prompts.
 *
 * The backend independently assembles the run from the `@handle` inside the
 * message text (see `AssistantChatAgentFactory`), so the rewritten values
 * are display/persistence metadata plus the payload the server enforces
 * anyway.
 */

/** `Assistant` narrowed to rows that can be addressed (id + handle present). */
type AddressableAssistant = Assistant & { id: string; handle: string };

function addressableAssistants(): Map<string, AddressableAssistant> {
    const rows = new Map<string, AddressableAssistant>();
    for (const assistant of assistantHandlesStore.assistants) {
        if (assistant.id !== null && assistant.handle !== null) {
            rows.set(assistant.handle, assistant as AddressableAssistant);
        }
    }
    return rows;
}

function resolveAddressedAssistant(
    composer: ComposerContext | null,
    conversation: ChatConversation | null
): AddressableAssistant | null {
    const byHandle = addressableAssistants();

    for (const handle of composer?.handlesInMessage ?? []) {
        const assistant = byHandle.get(handle.replace(/^@/, ''));
        if (assistant) {
            return assistant;
        }
    }

    const boundHandle = conversation?.assistant_handle;
    if (boundHandle !== null && boundHandle !== undefined) {
        return byHandle.get(boundHandle) ?? null;
    }

    return null;
}

/** Handler for the `chatSend` hook. */
export function assistantChatSend(send: ChatSendDescriptor, ctx: ChatSendContext): ChatSendDescriptor {
    const assistant = resolveAddressedAssistant(ctx.composer, ctx.conversation);
    if (!assistant) {
        return send;
    }

    const toolNames = (assistant.aiTools ?? []).map((tool) => tool.name);
    // The same glyph and color that identify the assistant in the `@` menu
    // identify it as the author of its answers.
    const appearance = assistantRowAppearance(assistant);

    return {
        assistantHandle: assistant.handle,
        assistant: {
            name: assistant.name,
            icon: appearance.icon,
            tint: appearance.colors.from
        },
        author: {
            username: assistant.handle,
            name: assistant.name,
            avatar_url: ''
        },
        model: assistant.allowModelSelect ? send.model : assistant.model,
        // The assistant defines the toolset of its runs — possibly empty.
        tools: toolNames,
        params: {
            temp: assistant.temp,
            top_p: assistant.topP,
            max_tokens: assistant.maxTokens
        }
    };
}

/** Handler for the `chatWelcome` hook. */
export function assistantChatWelcome(
    _section: ChatWelcomeSection | null,
    ctx: ChatWelcomeContext
): ChatWelcomeSection | null {
    const assistant = resolveAddressedAssistant(ctx.composer, ctx.conversation);
    if (!assistant) {
        return null;
    }

    return {
        id: 'assistants:welcome',
        title: assistant.name,
        description: assistant.greeting || assistant.description,
        starterPrompts: assistant.starterPrompts,
        handle: `@${assistant.handle}`
    };
}
