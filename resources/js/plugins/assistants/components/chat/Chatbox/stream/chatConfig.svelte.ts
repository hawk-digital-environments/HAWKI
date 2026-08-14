import {getContext, setContext} from "svelte";
import {getOpenAiApiBaseUrl} from "$lib/data/api/client";
import {getAuthToken} from "$lib/data/api/auth";
import {assistantBuilderStore} from "$lib/stores/assistants/AssistantBuilderStore.svelte";

export type ChatConfig = {
    apiUrl: string;
    token: string;
    model: string;
    assistantHandle: string;
};

export type ChatConfigApi = {
    readonly value: ChatConfig;
    readonly hasHandle: boolean;
};

const KEY = Symbol("chat-config");

export const provideChatConfig = (handleOverride?: () => string | undefined): ChatConfigApi => {
    const value = $derived<ChatConfig>({
        apiUrl: getOpenAiApiBaseUrl(),
        token: getAuthToken() ?? "",
        model: assistantBuilderStore.baseline.model ?? "",
        assistantHandle: (
            handleOverride?.()?.trim() ||
            assistantBuilderStore.baseline.handle?.trim() ||
            ""
        ),
    });

    const hasHandle = $derived(value.assistantHandle.length > 0);

    const api: ChatConfigApi = {
        get value(): ChatConfig {
            return value;
        },
        get hasHandle(): boolean {
            return hasHandle;
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
