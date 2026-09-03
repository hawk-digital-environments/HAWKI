import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';

const disableableFeatures = ['models', 'settings', 'attachments', 'tools', 'input', 'suggestions'] as const;
export type DisabledChatFeature = typeof disableableFeatures[number];

/**
 * Pure derived view of whether the composer allows certain actions right now.
 *
 * `GuardSlice` holds no mutable state of its own — all properties are
 * `$derived` from the rest of the context. It centralises the send-permission
 * and mode-change-permission logic so individual components don't need to
 * replicate those checks.
 *
 * Receives a `contextResolver` factory instead of the context directly to
 * break the circular construction dependency: `ComposerContext` owns
 * `GuardSlice`, but `GuardSlice` needs to read `ComposerContext`. The
 * factory is only called after construction, once the context is fully built.
 */
export class GuardSlice {
    constructor(
        private contextResolver: () => ComposerContext
    ) {
    }

    /** Translation key explaining why sending is currently blocked, or `null` when it is allowed. */
    public readonly cannotSendReason = $derived.by((): string | null => {
        const context = this.contextResolver();
        if (context.forcedActive) {
            return 'chat.composer.actions.actionInProgressTooltip';
        }

        if (!context.hasWriteAccess) {
            return 'chat.composer.actions.noWriteAccessTooltip';
        }

        if (context.sendStatus?.active) {
            return 'chat.composer.actions.sendingInProgressTooltip';
        }

        if (context.messageWithoutHandles.trim().length <= 0) {
            return 'chat.composer.actions.noMessageTooltip';
        }

        // Ignore model usage issues when the user does not see any AI-related UI elements.
        if (context.modelUsage.issues.length > 0) {
            return 'chat.composer.actions.invalidModelTooltip';
        }

        if (context.mode.instance.canSend(
            context,
            context.mode.state
        )) {
            return null;
        }

        // @todo Let modes provide a more specific reason when their contract supports it.
        return 'chat.composer.actions.modeCannotSendTooltip';
    });

    /** Whether the send button should be enabled. */
    public readonly canSend = $derived(this.cannotSendReason === null);

    /** Whether AI-related UI (model picker, tool menu, etc.) should be visible.
     *  Always `true` in `aiConv` mode; in `room` mode, only when the message
     *  contains an `@ai-handle`. */
    public readonly showsAiUiElements = $derived.by(() => {
        const context = this.contextResolver();

        if (context.type === 'aiConv') {
            return true;
        }

        return context.containsAiHandle;
    });

    /** Whether mode transitions are currently allowed. Blocked while a send is active
     *  (sending or responding), while `forcedActive` is set, or without write access. */
    public readonly canChangeMode = $derived.by(() => {
        const context = this.contextResolver();
        return !(context.sendStatus?.active) && !context.forcedActive && context.hasWriteAccess;
    });

    /**
     * Whether a specific UI feature should be disabled right now.
     *
     * Two independent checks are combined: an optional activity lock (enabled while
     * a message is sending) and the current mode's own `disablesUiFeature()` decision.
     *
     * @param disableWhileActive Pass `false` to skip the activity lock and check only the
     *   mode's decision. Useful for features that should stay interactive during a send.
     */
    public disablesFeature(feature: DisabledChatFeature, disableWhileActive: boolean = true): boolean {
        const context = this.contextResolver();
        if (disableWhileActive && (context.sendStatus?.sending || context.forcedActive)) {
            return true;
        }

        return context.mode.instance.disablesUiFeature(feature);
    }
}
