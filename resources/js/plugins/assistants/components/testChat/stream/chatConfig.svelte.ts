import { getContext, setContext } from "svelte";
import type { Assistant } from "$plugins/assistants/types/assistant";

/**
 * The assistant under test. Callers hand in an accessor (not a snapshot) so
 * the test chat always reflects the current value — the builder passes its
 * live `draft` so unsaved edits stream immediately, the store's detail page
 * passes its fetched assistant. The streaming endpoint (`/req/streamAI`)
 * takes the system prompt/model/params/tools straight in the request body
 * and never resolves an assistant server-side (see
 * `StreamController::handleStreamingRequest`, which never touches `slug`),
 * so there's no need to save/release the assistant first.
 */
/**
 * What the chat talks to: `test` chats with the assistant under test, `guide`
 * with the builder's setup guide about that assistant.
 */
export type ChatVariant = "test" | "guide";

export type ChatConfigApi = {
    readonly assistant: Assistant;
    readonly hasModel: boolean;
    readonly variant: ChatVariant;
};

const KEY = Symbol("chat-config");

/** A config without context, for chat stores owned outside a `<Chatbox>`. */
export const createChatConfig = (getAssistant: () => Assistant, variant: ChatVariant = "test"): ChatConfigApi => ({
    get assistant(): Assistant {
        return getAssistant();
    },
    get hasModel(): boolean {
        return getAssistant().model.length > 0;
    },
    variant,
});

export const provideChatConfig = (getAssistant: () => Assistant, variant: ChatVariant = "test"): ChatConfigApi => {
    const api = createChatConfig(getAssistant, variant);

    setContext(KEY, api);
    return api;
};

export const useChatConfig = (): ChatConfigApi => {
    const ctx = getContext<ChatConfigApi>(KEY);
    if (!ctx) throw new Error("useChatConfig() must be used within a <Chatbox> provider");
    return ctx;
};
