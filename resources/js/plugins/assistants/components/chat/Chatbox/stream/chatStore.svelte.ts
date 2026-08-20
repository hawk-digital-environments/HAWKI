import { getContext, setContext } from "svelte";
import type { ChatConfigApi } from "./chatConfig.svelte.js";
import type { Assistant } from "$plugins/assistants/types/assistant";
import type { ChatMessage, MessagePart } from "../types";

export type ChatStatus = "idle" | "streaming" | "error";

export type ChatStoreApi = {
    readonly messages: ChatMessage[];
    readonly status: ChatStatus;
    readonly error: string | null;
    clear: () => void;
    send: (text: string) => Promise<void>;
};

const KEY = Symbol("chat-store");

/**
 * One line of the newline-delimited JSON stream `/req/streamAI` returns —
 * see `StreamController::handleStreamingRequest()`'s `$formatData`/
 * `$formatStatus` closures for the authoritative shape. `isDone` can be set
 * on any `type`, not just `'completion'`/`'error'` (a mid-stream provider
 * error is delivered as `{type: 'message', isDone: true}` with the error text
 * as `content`), so callers must check it independently of `type`.
 */
type StreamLine = {
    type: "header" | "status" | "message" | "citation" | "completion" | "error";
    content: unknown;
    isDone: boolean;
    status?: { key: string; value?: unknown } | null;
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
}

/** Splits the raw byte stream on newlines and JSON-parses each line, mirroring `processStream()` in `public/js/stream_functions.js`. */
async function* readStreamLines(body: ReadableStream<Uint8Array>): AsyncGenerator<StreamLine> {
    const reader = body.getReader();
    const decoder = new TextDecoder("utf-8");
    let buffer = "";
    while (true) {
        const { done, value } = await reader.read();
        if (done) return;
        buffer += decoder.decode(value, { stream: true });
        const chunks = buffer.split("\n");
        buffer = chunks.pop() ?? "";
        for (const chunk of chunks) {
            const trimmed = chunk.trim();
            if (trimmed) yield JSON.parse(trimmed) as StreamLine;
        }
    }
}

/** Flattens a message's text parts back into the plain string `/req/streamAI` expects — reasoning/tool-call parts are local-only and never sent back. */
function toMessageText(parts: MessagePart[]): string {
    return parts
        .filter((p): p is Extract<MessagePart, { type: "text" }> => p.type === "text")
        .map((p) => p.text)
        .join("");
}

type ApiMessage = { role: string; content: { text: string; attachments: [] } };

function toApiMessages(assistant: Assistant, history: ChatMessage[]): ApiMessage[] {
    const out: ApiMessage[] = [];

    const systemPrompt = assistant.systemPrompt.trim();
    if (systemPrompt) {
        out.push({ role: "system", content: { text: systemPrompt, attachments: [] } });
    }

    for (const m of history) {
        const text = toMessageText(m.parts);
        if (!text) continue;
        out.push({ role: m.role, content: { text, attachments: [] } });
    }

    return out;
}

export const provideChatStore = (config: ChatConfigApi): ChatStoreApi => {
    let messages = $state<ChatMessage[]>([]);
    let status = $state<ChatStatus>("idle");
    let error = $state<string | null>(null);
    let abortCtrl: AbortController | null = null;

    const clear = (): void => {
        abortCtrl?.abort();
        messages = [];
        error = null;
        status = "idle";
    };

    const send = async (text: string): Promise<void> => {
        const content = text.trim();
        if (!content || status === "streaming") return;
        if (!config.hasModel) return;

        const assistant = config.assistant;

        messages.push({
            id: crypto.randomUUID(),
            role: "user",
            parts: [{ type: "text", text: content }],
        });

        messages.push({
            id: crypto.randomUUID(),
            role: "assistant",
            parts: [],
            streaming: true,
        });
        const idx = messages.length - 1;

        const apiMessages = toApiMessages(assistant, messages.slice(0, -1));

        status = "streaming";
        error = null;
        abortCtrl = new AbortController();

        try {
            const response = await fetch("/req/streamAI", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                    "Accept": "application/json",
                },
                body: JSON.stringify({
                    broadcast: false,
                    threadIndex: 0,
                    slug: "",
                    payload: {
                        model: assistant.model,
                        stream: true,
                        messages: apiMessages,
                        tools: assistant.aiTools?.map((t) => t.name) ?? [],
                        params: { temp: assistant.temp, top_p: assistant.topP },
                    },
                }),
                signal: abortCtrl.signal,
            });

            if (!response.ok || !response.body) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            for await (const line of readStreamLines(response.body)) {
                const msg = messages[idx];
                switch (line.type) {
                    case "message": {
                        const delta = typeof line.content === "string" ? line.content : "";
                        if (delta) {
                            const last = msg.parts[msg.parts.length - 1];
                            if (last && last.type === "text") {
                                last.text += delta;
                            } else {
                                msg.parts.push({ type: "text", text: delta });
                            }
                        }
                        break;
                    }
                    case "status": {
                        const key = line.status?.key;
                        if (key === "reasoning" || key === "reasoning_delta") {
                            const delta = key === "reasoning_delta" && typeof line.status?.value === "string"
                                ? line.status.value
                                : "";
                            const last = msg.parts[msg.parts.length - 1];
                            if (last && last.type === "reasoning") {
                                last.text += delta;
                            } else {
                                msg.parts.push({ type: "reasoning", text: delta });
                            }
                        } else if (key === "tool_call" || key === "provider_tool_call") {
                            const name = typeof line.status?.value === "string" ? line.status.value : "tool";
                            msg.parts.push({ type: "tool-call", name });
                        }
                        break;
                    }
                    case "error": {
                        status = "error";
                        error = typeof line.content === "string" ? line.content : "Something went wrong.";
                        break;
                    }
                    // "header" carries author/model metadata we don't render here;
                    // "citation"/"completion" carry data already covered by the
                    // accumulated text deltas — nothing further to apply.
                }
                if (line.isDone) break;
            }

            messages[idx].streaming = false;
            if (status !== "error") status = "idle";
        } catch (err) {
            messages[idx].streaming = false;
            if (err instanceof DOMException && err.name === "AbortError") {
                status = "idle";
            } else {
                status = "error";
                error = err instanceof Error ? err.message : String(err);
            }
        } finally {
            abortCtrl = null;
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
