import {streamText, type ModelMessage} from "ai";
import {createOpenAI} from "@ai-sdk/openai";
import {getContext, setContext} from "svelte";
import type {ChatConfigApi} from "./chatConfig.svelte.js";
import type {ChatMessage} from "../types";

export type ChatStatus = "idle" | "streaming" | "error";

export type ChatStoreApi = {
    readonly messages: ChatMessage[];
    readonly status: ChatStatus;
    readonly error: string | null;
    clear: () => void;
    send: (text: string) => Promise<void>;
};

const KEY = Symbol("chat-store");

const toModelMessages = (messages: ChatMessage[]): ModelMessage[] => {
    const out: ModelMessage[] = [];
    for (const m of messages) {
        const text = m.parts
            .filter((p): p is Extract<(typeof m.parts)[number], {type: "text"}> => p.type === "text")
            .map((p) => p.text)
            .join("");
        if (!text) continue;
        if (m.role === "user") {
            out.push({role: "user", content: text});
        } else {
            out.push({role: "assistant", content: text});
        }
    }
    return out;
};

export const provideChatStore = (config: ChatConfigApi): ChatStoreApi => {
    let messages = $state<ChatMessage[]>([]);
    let status = $state<ChatStatus>("idle");
    let error = $state<string | null>(null);

    const clear = (): void => {
        messages = [];
        error = null;
        status = "idle";
    };

    const send = async (text: string): Promise<void> => {
        const content = text.trim();
        if (!content || status === "streaming") return;

        const cfg = config.value;
        if (!config.hasHandle) return;

        messages.push({
            id: crypto.randomUUID(),
            role: "user",
            parts: [{type: "text", text: content}],
        });

        messages.push({
            id: crypto.randomUUID(),
            role: "assistant",
            parts: [],
            streaming: true,
        });
        const idx = messages.length - 1;

        const history = toModelMessages(messages.slice(0, -1));

        status = "streaming";
        error = null;

        const handle = cfg.assistantHandle;
        const openai = createOpenAI({
            baseURL: cfg.apiUrl,
            apiKey: cfg.token,
            fetch: async (input, init) => {
                if (init?.body) {
                    try {
                        const body = JSON.parse(init.body as string) as Record<string, unknown>;
                        body["assistant_handle"] = handle;
                        init = {...init, body: JSON.stringify(body)};
                    } catch {
                        /* */
                    }
                }
                return globalThis.fetch(input, init);
            },
        });
        const model = openai.responses(cfg.model);

        try {
            const result = streamText({model, messages: history});

            for await (const part of result.fullStream) {
                const msg = messages[idx];
                switch (part.type) {
                    case "text-delta": {
                        const last = msg.parts[msg.parts.length - 1];
                        if (last && last.type === "text") {
                            last.text += part.text;
                        } else {
                            msg.parts.push({type: "text", text: part.text});
                        }
                        break;
                    }
                    case "reasoning-delta": {
                        const last = msg.parts[msg.parts.length - 1];
                        if (last && last.type === "reasoning") {
                            last.text += part.text;
                        } else {
                            msg.parts.push({type: "reasoning", text: part.text});
                        }
                        break;
                    }
                    case "tool-call": {
                        msg.parts.push({
                            type: "tool-call",
                            toolCallId: part.toolCallId,
                            toolName: part.toolName,
                            input: part.input,
                        });
                        break;
                    }
                    case "tool-result": {
                        msg.parts.push({
                            type: "tool-result",
                            toolCallId: part.toolCallId,
                            toolName: part.toolName,
                            output: part.output,
                        });
                        break;
                    }
                    case "error": {
                        status = "error";
                        error = part.error instanceof Error ? part.error.message : String(part.error);
                        break;
                    }
                }
            }

            messages[idx].streaming = false;
            if (status !== "error") status = "idle";
        } catch (err) {
            messages[idx].streaming = false;
            status = "error";
            error = err instanceof Error ? err.message : String(err);
        }
    };

    const api: ChatStoreApi = {
        get messages(): ChatMessage[] {
            return messages;
        },
        get status(): ChatStatus {
            return status;
        },
        get error(): string | null {
            return error;
        },
        clear,
        send,
    };

    setContext(KEY, api);
    return api;
};

export const useChatStore = (): ChatStoreApi => {
    const ctx = getContext<ChatStoreApi>(KEY);
    if (!ctx) throw new Error("useChatStore() must be used within a <Chatbox> provider");
    return ctx;
};
