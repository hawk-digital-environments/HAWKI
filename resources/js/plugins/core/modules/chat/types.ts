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
    status?: string;
}

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
