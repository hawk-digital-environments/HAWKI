import type {ChatSendContext, ChatSendDescriptor} from '$plugins/core/modules/chat/hooks/chatSendHooks.js';
import type {ChatWelcomeContext, ChatWelcomeSection} from '$plugins/core/modules/chat/hooks/chatWelcomeHooks.js';
import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
import type {Assistant} from '$plugins/assistants/types/assistant';
import {assistantRowAppearance} from '$plugins/assistants/utils/assistantRowAppearance';
import {assistantHandlesStore} from '$plugins/assistants/stores/AssistantHandlesStore.svelte.js';

/**
 * Chat integration of the assistants plugin, registered as handlers for the
 * chat module's `chatSend` and `chatWelcome` hooks (see `assistants.plugin.ts`).
 *
 * When the composer addresses one of the user's assistants via its `@handle`,
 * the send is pinned to that assistant:
 *
 * - the conversation binding (`ai_convs.assistant_handle`) follows the
 *   addressed handle, so switching assistants mid-chat rebinds and removing
 *   the handle turns the conversation into a plain HAWKI chat,
 * - model/tools/params of the run (and the persisted message metadata) come
 *   from the assistant (the model only stays user-selected when the
 *   assistant allows it),
 * - the streamed response shows the assistant's name as its author,
 * - the welcome hero shows the assistant's name, greeting and starter
 *   prompts (there the conversation binding still acts as a display
 *   fallback while the composer is still seeding).
 *
 * A regen runs through the same pinning for the assistant picked in the
 * regenerate menu (preset as the descriptor's `assistantHandle`), so it
 * applies the assistant's logic regardless of the composer's current handle.
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

function addressedByComposer(composer: ComposerContext | null): AddressableAssistant | null {
    const byHandle = addressableAssistants();

    for (const handle of composer?.handlesInMessage ?? []) {
        const assistant = byHandle.get(handle.replace(/^@/, ''));
        if (assistant) {
            return assistant;
        }
    }

    return null;
}

/**
 * The assistant a send is pinned to: the composer's live `@handle` choice,
 * else the assistant a regen explicitly targets (preset on the descriptor),
 * else the original author of the message a regen overwrites, else none.
 * The conversation binding deliberately does not feed back here — the
 * transport rebases it on this result, so consulting it would resurrect a
 * removed assistant.
 */
function resolveSendAssistant(send: ChatSendDescriptor, ctx: ChatSendContext): AddressableAssistant | null {
    const addressed = addressedByComposer(ctx.composer);
    if (addressed) {
        return addressed;
    }

    if (send.assistantHandle !== null) {
        return addressableAssistants().get(send.assistantHandle) ?? null;
    }

    const regenHandle = ctx.regenMessage?.assistant?.handle;
    if (regenHandle !== undefined) {
        return addressableAssistants().get(regenHandle) ?? null;
    }

    return null;
}

/** Handler for the `chatSend` hook. */
export function assistantChatSend(send: ChatSendDescriptor, ctx: ChatSendContext): ChatSendDescriptor {
    const assistant = resolveSendAssistant(send, ctx);
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
            tint: appearance.colors.from,
            handle: assistant.handle
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
    let assistant = addressedByComposer(ctx.composer);
    if (!assistant) {
        // Display-only: until the composer has seeded the bound handle, the
        // conversation binding decides which assistant the hero presents.
        const boundHandle = ctx.conversation?.assistant_handle;
        if (boundHandle !== null && boundHandle !== undefined) {
            assistant = addressableAssistants().get(boundHandle) ?? null;
        }
    }

    if (!assistant) {
        return null;
    }

    // The same glyph and color that identify the assistant in the `@` menu
    // identify it in the welcome hero.
    const appearance = assistantRowAppearance(assistant);

    return {
        id: 'assistants:welcome',
        title: assistant.name,
        description: assistant.greeting || assistant.description,
        starterPrompts: assistant.starterPrompts,
        icon: appearance.icon,
        tint: appearance.colors.from,
        handle: `@${assistant.handle}`
    };
}
