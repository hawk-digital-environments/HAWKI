import { getContext, setContext } from "svelte";
import { useBuilderContext } from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
import type { Assistant } from "$plugins/assistants/types/assistant";

/**
 * The assistant under test. Reads the builder's live `draft` (not `baseline`)
 * so the test chat reflects unsaved edits immediately — the streaming
 * endpoint (`/req/streamAI`) takes the system prompt/model/params/tools
 * straight in the request body and never resolves an assistant server-side
 * (see `StreamController::handleStreamingRequest`, which never touches
 * `slug`), so there's no need to save/release the assistant first.
 */
export type ChatConfigApi = {
    readonly assistant: Assistant;
    readonly hasModel: boolean;
};

const KEY = Symbol("chat-config");

export const provideChatConfig = (): ChatConfigApi => {
    const builder = useBuilderContext();

    const api: ChatConfigApi = {
        get assistant(): Assistant {
            return builder.draft;
        },
        get hasModel(): boolean {
            return builder.draft.model.length > 0;
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
