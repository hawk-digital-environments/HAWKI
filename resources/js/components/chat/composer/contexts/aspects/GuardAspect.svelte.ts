import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';

const disableableFeatures = ['models', 'settings', 'attachments', 'tools', 'input', 'suggestions'] as const;
export type DisabledChatFeature = typeof disableableFeatures[number];

export class GuardAspect {
    constructor(
        private contextResolver: () => ComposerContext
    ) {
    }

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

    public readonly canChangeMode = $derived.by(() => {
        const context = this.contextResolver();
        return !(context.sendStatus?.active) && !context.forcedActive && context.hasWriteAccess;
    });

    public disablesFeature(feature: DisabledChatFeature, disableWhileActive: boolean = true): boolean {
        const context = this.contextResolver();
        if (disableWhileActive && (context.sendStatus?.sending || context.forcedActive)) {
            return true;
        }

        return context.mode.instance.disablesUiFeature(feature);
    }
}
