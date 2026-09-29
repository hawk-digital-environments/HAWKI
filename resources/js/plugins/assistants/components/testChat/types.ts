/**
 * A rendered segment of a chat message. Reasoning and tool-call segments are
 * synthesized client-side from the `/req/streamAI` `status` events (see
 * `stream/chatStore.svelte.ts`) — the backend doesn't persist them as part of
 * the message content, only as transient progress pings, so there is no
 * "tool-result" part: `StreamController::handleStreamingRequest()` never
 * yields one (see `app/Http/Controllers/StreamController.php`).
 */
export type MessagePart =
    | { type: "text"; text: string }
    | { type: "reasoning"; text: string }
    | { type: "tool-call"; name: string }
    /** Builder guide only: the (translated) labels of the fields this reply filled in. */
    | { type: "applied"; labels: string[] };

export type ChatMessage = {
    id: string;
    role: "user" | "assistant";
    parts: MessagePart[];
    /** Names of the files uploaded with this message. */
    attachments?: string[];
    streaming?: boolean;
};
