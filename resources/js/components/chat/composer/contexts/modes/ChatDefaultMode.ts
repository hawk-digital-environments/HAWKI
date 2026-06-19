import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import {AbstractMode} from '$lib/components/chat/composer/contexts/modes/contracts/AbstractMode.js';


export interface ChatDefaultModeState {
}

export class ChatDefaultMode extends AbstractMode<null, ChatDefaultModeState> {
    public enter(context: ComposerContext, data: null): ChatDefaultModeState {
        return {};
    }

    public exitAfterSend(context: ComposerContext, state: ChatDefaultModeState): boolean {
        return false;
    }
}
