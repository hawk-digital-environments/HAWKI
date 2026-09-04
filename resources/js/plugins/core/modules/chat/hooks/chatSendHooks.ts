import type {AiModelParameters} from '$lib/kernel/ai/types.js';
import type {ChatAssistantIdentity, ChatConversation, ChatMessage} from '$plugins/core/modules/chat/types.js';
import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';

/**
 * The resolved description of one outgoing chat send, threaded through the
 * `chatSend` hook before the transport creates the conversation and streams
 * the response.
 *
 * The chat module fills in the composer's own selection (model, active
 * tools, parameter list); handlers may rewrite the run — e.g. the
 * assistants plugin pins model/tools/params to the addressed assistant's
 * configuration and supplies its display identity — and mark the exchange
 * as bound to an assistant handle. The handle travels as
 * `payload.assistant_handle` (the backend's sole assistant-detection
 * mechanism, mirroring the OpenAI responses endpoint) and is persisted as
 * the conversation binding (`ai-convs.assistant_handle`).
 */
export interface ChatSendDescriptor {
    /**
     * Assistant handle (without the leading `@`) the exchange is bound to,
     * or null for a plain model run. Travels as `payload.assistant_handle`
     * (the backend assembles the assistant from it) and is persisted as the
     * conversation binding.
     */
    assistantHandle: string | null;
    /** Display identity for the AI message's author; null keeps the model-label rendering. */
    assistant: ChatAssistantIdentity | null;
    /** Display author for the streamed assistant message; null keeps the default HAWKI author. */
    author: ChatMessage['author'] | null;
    /** Model id the run is addressed to (also persisted on the AI message). */
    model: string;
    /** Tool-transfer strings for the run (also persisted in the message metadata). */
    tools: string[];
    /** Sampling parameters for the run (also persisted in the message metadata). */
    params: AiModelParameters | null;
}

/** Context for the `chatSend` hook. */
export interface ChatSendContext {
    /** The composer the send originates from. */
    composer: ComposerContext;
    /** The conversation the send goes into; null while the first message creates it. */
    conversation: ChatConversation | null;
}

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiHooks {
        chatSend: { value: ChatSendDescriptor; ctx: ChatSendContext };
    }
}
