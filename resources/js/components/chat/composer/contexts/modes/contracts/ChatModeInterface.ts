import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {DisabledChatFeature} from '$lib/components/chat/composer/contexts/aspects/GuardAspect.svelte.js';

export interface ChatModeInterface<TData = any, TState = any> {
    disablesUiFeature(feature: DisabledChatFeature): boolean;

    canSend(context: ComposerContext, state: TState): boolean;

    allowsNestedModes(): boolean;

    exitAfterSend(context: ComposerContext, state: TState): boolean;

    canEnter(context: ComposerContext, data: TData): boolean | string;

    enter(context: ComposerContext, data: TData): TState;

    exit(context: ComposerContext, state: TState): void;
}
