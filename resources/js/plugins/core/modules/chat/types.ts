import type {UrlCitation} from '$lib/components/ui/citations/types.js';
import type {OldUiConversationMessage} from '$lib/legacy/OldUiBridge.svelte.js';

export interface ChatSummary {
    id: number;
    name: string;
    slug: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface ChatMessage extends OldUiConversationMessage {
    citations?: UrlCitation[];
    isStreaming?: boolean;
    status?: string;
}

export interface ChatConversation {
    id: number;
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
