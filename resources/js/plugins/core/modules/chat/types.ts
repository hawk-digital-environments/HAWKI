import type {UrlCitation} from '$lib/components/ui/citations/types.js';

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
    /** Client-only message that is visible before it has been persisted. */
    isPending?: boolean;
    /** Assistant response whose persisted content is still arriving from the stream. */
    isStreaming?: boolean;
    /** Stable render key for a message whose `message_id` changes once it is persisted (pending/streaming → saved). */
    clientKey?: string;
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
    /** Number of characters in the generated answer. */
    characters: number;
    /** Number of text chunks received from the stream. */
    chunks: number;
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
    messages: ChatMessage[];
}

export interface EncryptedText {
    ciphertext: string;
    iv: string;
    tag: string;
}
