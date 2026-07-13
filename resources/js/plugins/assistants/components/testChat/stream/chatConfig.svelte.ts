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
export type ChatConfigApi = {
    readonly assistant: Assistant;
    readonly hasModel: boolean;
};

const KEY = Symbol("chat-config");

export const provideChatConfig = (getAssistant: () => Assistant): ChatConfigApi => {
    const api: ChatConfigApi = {
        get assistant(): Assistant {
            return getAssistant();
        },
        get hasModel(): boolean {
            return getAssistant().model.length > 0;
        },
    };

    setContext(KEY, api);
    return api;
};

export const useChatConfig = (): ChatConfigApi => {
    const ctx = getContext<ChatConfigApi>(KEY);
    if (!ctx) throw new Error("useChatConfig() must be used within a <Chatbox> provider");
    return ctx;
};
