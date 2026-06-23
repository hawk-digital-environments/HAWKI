import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import type {DisabledChatFeature} from '$lib/components/chat/composer/contexts/aspects/GuardAspect.svelte.js';

/**
 * Contract every composer mode must implement.
 *
 * `ModeAspect` drives the lifecycle: `canEnter` → `enter` → (user interacts)
 * → `exit`. All other methods are queried reactively by `GuardAspect` and the
 * UI to adapt their behaviour while the mode is active.
 *
 * `TData` is the input the caller passes to `enter()` (e.g. the message to
 * edit). `TState` is whatever the mode needs to remember while it is active
 * (e.g. the original message text, so `canSend` can compare against it).
 */
export interface ChatModeInterface<TData = any, TState = any> {
    /** Whether the mode is allowed to be entered right now. Return `false` to
     * silently abort, or a string to abort with an error toast. */
    canEnter(context: ComposerContext, data: TData): boolean | string;

    /**
     * Called when the mode is entered. Should mutate the context to set up
     * the mode's initial state (pre-fill message, load attachments, etc.).
     * The return value becomes the persistent `TState` for this mode instance.
     */
    enter(context: ComposerContext, data: TData): TState;

    /** Called when the mode is exited (after the checkpoint is restored). */
    exit(context: ComposerContext, state: TState): void;

    /** Whether sending a message should automatically exit the mode afterwards. */
    exitAfterSend(context: ComposerContext, state: TState): boolean;

    /** Whether a second `enter()` call is allowed while this mode is active.
     * Used by `ChatInThreadMode` to allow nested edit/regen inside a thread. */
    allowsNestedModes(): boolean;

    /** Additional send-readiness check specific to this mode (e.g. edit mode
     * blocks send when the message hasn't changed). */
    canSend(context: ComposerContext, state: TState): boolean;

    /** Whether a given UI feature should be locked while this mode is active. */
    disablesUiFeature(feature: DisabledChatFeature): boolean;
}
