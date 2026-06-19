import type {ChatModeInterface} from '$lib/components/chat/composer/contexts/modes/contracts/ChatModeInterface.js';
import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {DisabledChatFeature} from '$lib/components/chat/composer/contexts/aspects/GuardAspect.svelte.js';

export abstract class AbstractMode<TData = any, TState = any> implements ChatModeInterface<TData, TState> {
    public allowsNestedModes(): boolean {
        return false;
    }

    public canSend(context: ComposerContext, state: TState): boolean {
        return true;
    }

    public disablesUiFeature(feature: DisabledChatFeature): boolean {
        return false;
    }

    public canEnter(context: ComposerContext, data: TData): boolean | string {
        return true;
    }

    public abstract enter(context: ComposerContext, data: TData): TState

    public exit(context: ComposerContext, state: TState): void {
    }

    public exitAfterSend(context: ComposerContext, state: TState): boolean {
        return true;
    }
}
