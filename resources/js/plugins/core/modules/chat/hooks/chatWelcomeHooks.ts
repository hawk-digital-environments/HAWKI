import type {ChatConversation} from '$plugins/core/modules/chat/types.js';
import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';

/**
 * One rendered welcome section for the chat empty state: a title with an
 * optional description and starter prompt suggestions.
 *
 * Contributed through the `chatWelcome` hook — the chat module supplies
 * `null` (its default hero) and handlers may replace it with a section
 * describing the currently addressed participant, e.g. the assistants
 * plugin shows the addressed assistant's name, greeting and starter
 * prompts.
 */
export interface ChatWelcomeSection {
    /** Stable owner id, e.g. `'assistants:welcome'`. */
    id: string;
    /** Section headline — the participant's display name. */
    title: string;
    /** Supporting text below the title. */
    description?: string;
    /**
     * Suggested opening messages rendered as selectable chips. Selecting
     * one pre-fills the composer (and re-addresses `handle` when present).
     */
    starterPrompts?: string[];
    /** `@handle` re-inserted into the composer when a starter prompt is selected. */
    handle?: string;
}

/** Context for the `chatWelcome` hook. */
export interface ChatWelcomeContext {
    /** The page's composer; null while it has not signalled readiness. */
    composer: ComposerContext | null;
    /** The opened conversation, or null on the new-chat page. */
    conversation: ChatConversation | null;
}

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiHooks {
        chatWelcome: { value: ChatWelcomeSection | null; ctx: ChatWelcomeContext };
    }
}
