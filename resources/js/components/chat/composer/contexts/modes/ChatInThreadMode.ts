import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {ChatDefaultModeState} from '$lib/components/chat/composer/contexts/modes/ChatDefaultMode.js';
import {AbstractMode} from '$lib/components/chat/composer/contexts/modes/contracts/AbstractMode.js';

export interface ChatThreadModeState {
    threadId: string;
}

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
