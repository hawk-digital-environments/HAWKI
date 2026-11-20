import type { UrlCitation } from '$lib/components/ui/citations/types.js';

/** Display identity of the assistant that answered an AI message: shown as
 *  the author (name + glyph in a tinted circle) instead of the model label.
 *  Persisted in the message metadata as `metadata.assistant`, so reloaded
 *  conversations keep showing who answered. */
export interface ChatAssistantIdentity {
    name: string;
    /** The glyph its creator picked (emoji or first letter). */
    icon: string;
    /** One hsl() color stop from its appearance, tinting the icon circle. */
    tint?: string;
    /** Bare handle (no `@`) of the assistant that authored the message; lets a
     *  regen re-run with the same assistant even after the composer switched. */
    handle?: string;
}

export interface ChatSummary {
    name: string;
    slug: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ChatMessage {
    author: {
        username: string;
        name: string;
        avatar_url: string;
    };
    completion: number;
    content: {
        text: string;
        attachments: Array<{
            fileData: {
                uuid: string;
                name: string;
                mime: string;
                type: string;
                url: string;
                category: string;
            };
        }>;
    };
    created_at: string;
    message_id: string;
    message_role: 'user' | 'assistant';
    metadata: {
        tools: null | Record<string, unknown>;
        params: null | Record<string, unknown>;
    };
    model: null | string;
    updated_at: string;
    citations?: UrlCitation[];
    /** Identity of the assistant that authored this AI message, when one did. */
    assistant?: ChatAssistantIdentity;
    /** Client-only message that is visible before it has been persisted. */
    isPending?: boolean;
    /** Assistant response whose persisted content is still arriving from the stream. */
    isStreaming?: boolean;
    /** Stable render key for a message whose `message_id` changes once it is persisted (pending/streaming → saved). */
    clientKey?: string;
    /** Client-only thread index for a message without a persisted `W.DDD` id yet (pending/streaming): `0` = trunk, otherwise the owning trunk message's whole number. */
    threadId?: number;
    status?: string;
    /** Model reasoning (thinking) steps streamed alongside the answer. Stored inside the encrypted message content. */
    reasoning?: ReasoningPart[];
    /** Generation metrics for the "Stats for Nerds" experiment. Stored inside the encrypted message content. */
    stats?: MessageStats;
}

/** Metrics collected while an assistant response streamed in. */
export interface MessageStats {
    /** Output tokens reported by the provider; `null` while streaming or when the provider did not report usage. */
    outputTokens: number | null;
    /** Prompt tokens reported by the provider; `null` while streaming or when the provider did not report usage. */
    promptTokens: number | null;
    /** Output tokens (incl. reasoning) divided by the total request duration. */
    tokensPerSecond: number | null;
    /** Milliseconds from sending the request until the first text chunk arrived. */
    timeToFirstTokenMs: number | null;
    /** Total milliseconds from sending the request until the stream ended. */
    durationMs: number;
}

/** One step of the model's reasoning: a block of thinking text or a web search it performed. */
export type ReasoningPart =
    | {type: 'text'; text: string}
    | {
        type: 'web_search';
        /** What the model did: a search, opening a page or searching within a page. */
        action: 'search' | 'open_page' | 'find_in_page' | string;
        /** The search query, only present for `search` actions. */
        query: string | null;
        /** URLs the model found or looked at. */
        sources: string[];
    };

export interface ChatConversation {
    name: string;
    slug: string;
    system_prompt: string;
    /** Assistant handle (without `@`) the conversation is bound to; null for plain chats. */
    assistant_handle: string | null;
    messages: ChatMessage[];
}

export interface EncryptedText {
    ciphertext: string;
    iv: string;
    tag: string;
}
