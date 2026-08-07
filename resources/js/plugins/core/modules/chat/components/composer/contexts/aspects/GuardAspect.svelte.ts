import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';

const disableableFeatures = ['models', 'settings', 'attachments', 'tools', 'input', 'suggestions'] as const;
export type DisabledChatFeature = typeof disableableFeatures[number];

/**
 * Pure derived view of whether the composer allows certain actions right now.
 *
 * `GuardAspect` holds no mutable state of its own — all properties are
 * `$derived` from the rest of the context. It centralises the send-permission
 * and mode-change-permission logic so individual components don't need to
 * replicate those checks.
 *
 * Receives a `contextResolver` factory instead of the context directly to
 * break the circular construction dependency: `ComposerContext` owns
 * `GuardAspect`, but `GuardAspect` needs to read `ComposerContext`. The
 * factory is only called after construction, once the context is fully built.
 */
export class GuardAspect {
    constructor(
        private contextResolver: () => ComposerContext
    ) {
    }

    /** Whether the send button should be enabled. Requires: not forced-active, has write
     *  access, no active send in progress, non-empty message (handles excluded), no model
     *  compatibility issues, and the current mode's own `canSend` check passes. */
    public readonly canSend = $derived.by(() => {
        const context = this.contextResolver();
        if (context.forcedActive) {
            return false;
        }

        if (!context.hasWriteAccess) {
            return false;
        }

        if (context.sendStatus?.active) {
            return false;
        }

        if (context.messageWithoutHandles.trim().length <= 0) {
            return false;
        }

        // Ignore model usage issues when the user does not see any AI-related UI elements.
        if (context.modelUsage.issues.length > 0) {
            return false;
        }

        return context.mode.instance.canSend(
            context,
            context.mode.state
        );
    });

    /** Whether AI-related UI (model picker, tool menu, etc.) should be visible.
     *  Always `true` in `aiConv` mode; in `room` mode, only when regen is active
     *  or the message contains an `@ai-handle`. */
    public readonly showsAiUiElements = $derived.by(() => {
        const context = this.contextResolver();

        if (context.type === 'aiConv') {
            return true;
        }

        if (context.mode.is === 'regen') {
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
