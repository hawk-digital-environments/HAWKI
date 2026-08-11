import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
import {AbstractMode} from '$plugins/core/modules/chat/components/composer/contexts/modes/contracts/AbstractMode.js';


export interface ChatDefaultModeState {
}

/** Normal compose mode. Active from the start; never auto-exits after send. */
export class ChatDefaultMode extends AbstractMode<null, ChatDefaultModeState> {
    public enter(context: ComposerContext, data: null): ChatDefaultModeState {
        return {};
    }

    public exitAfterSend(context: ComposerContext, state: ChatDefaultModeState): boolean {
        return false;
    }
}
