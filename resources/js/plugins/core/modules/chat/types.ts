import type {UrlCitation} from '$lib/components/ui/citations/types.js';
import type {OldUiConversationMessage} from '$lib/legacy/OldUiBridge.svelte.js';

export interface ChatSummary {
    name: string;
    slug: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ChatMessage extends OldUiConversationMessage {
    citations?: UrlCitation[];
    /** Client-only message that is visible before it has been persisted. */
    isPending?: boolean;
    isStreaming?: boolean;
    status?: string;
}

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
