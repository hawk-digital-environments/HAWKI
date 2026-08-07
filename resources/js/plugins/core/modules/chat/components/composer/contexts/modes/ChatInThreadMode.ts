import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {ChatDefaultModeState} from '$lib/components/chat/composer/contexts/modes/ChatDefaultMode.js';
import {AbstractMode} from '$lib/components/chat/composer/contexts/modes/contracts/AbstractMode.js';

export interface ChatThreadModeState {
    threadId: string;
}

/**
 * Mode for composing a reply inside a message thread.
 *
 * Allows nested modes (`allowsNestedModes = true`) so the user can enter
 * edit or regen mode for messages within the thread without losing the thread
 * context — the thread checkpoint is preserved on the stack beneath the nested
 * mode checkpoint. Stays active after send so the user can keep the conversation
 * going without having to re-enter thread mode.
 */
export class ChatInThreadMode extends AbstractMode<string, ChatThreadModeState> {
    public allowsNestedModes(): boolean {
        // When in tread mode, we allow creating nested checkpoints
        // meaning we can enter edit or regen mode for messages in the thread without losing the thread context.
        return true;
    }

    public enter(context: ComposerContext, data: string): ChatThreadModeState {
        context.reset();
        context.focusInput();
        return {
            threadId: data
        };
    }

    public exitAfterSend(context: ComposerContext, state: ChatDefaultModeState): boolean {
        // When sending a message in thread mode, we want to stay in thread mode to allow the user to continue
        // the conversation in the thread.
        return false;
    }
}
