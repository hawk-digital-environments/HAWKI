<script lang="ts">

    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import SentIcon from '$lib/components/ui/icons/iconset/SentIcon.svelte';
    import ArtificialIntelligence08Icon from '$lib/components/ui/icons/iconset/ArtificialIntelligence08Icon.svelte';
    import SquareIcon from '$lib/components/ui/icons/iconset/SquareIcon.svelte';

    const toastContext = useToastContext();
    const composerContext = useComposerContext();

    interface Props {
        onSend?: () => void;
        buttonRef?: HTMLButtonElement | null;
    }

    let {
        onSend,
        buttonRef = $bindable(null)
    }: Props = $props();

    const sendTooltip = $derived.by(() => {
        if (!composerContext.message.trim()) {
            return __('chat.composer.actions.noMessageTooltip');
        }
        if (composerContext.modelUsage.isValid) {
            return __('chat.composer.actions.invalidModelTooltip');
        }
        if (!composerContext.message.trim()) {
            return __('chat.composer.actions.emptyMessageTooltip');
        }
        return __('chat.composer.actions.sendTooltip');
    });

    const sendLabel = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return __('chat.composer.actions.saveLabel');
        }
        if (composerContext.mode.isRegen) {
            return __('chat.composer.actions.regenerateLabel');
        }
        return __('chat.composer.actions.sendLabel');
    });

    const isNotSendingInNonDefaultMode = $derived.by(() => !composerContext.sendStatus?.active && !composerContext.mode.isDefault);
    const isSendingButCanAbort = $derived.by(() => composerContext.sendStatus?.active && composerContext.sendStatus.canBeAborted);

    const showCancel = $derived.by(() => isNotSendingInNonDefaultMode || isSendingButCanAbort);

    const cancelTooltip = $derived.by(() => {
        if (isNotSendingInNonDefaultMode) {
            if (composerContext.mode.isRegen) {
                return __('chat.composer.actions.cancelRegeneration');
            }
            if (composerContext.mode.isThread) {
                return __('chat.composer.actions.leaveThread');
            }
            return __('chat.composer.actions.cancelEdit');
        }

        if (isSendingButCanAbort) {
            return __('chat.composer.actions.cancelResponse');
        }

        return '';
    });

    const cancelAction = $derived.by(() => {
        if (isNotSendingInNonDefaultMode) {
            return () => composerContext.mode.exit();
        }
        if (isSendingButCanAbort) {
            return () => composerContext.sendStatus?.response.then(response => response.abort());
        }
    });

    const SendIcon = $derived.by(() => {
        if (composerContext.mode.isRegen || composerContext.mode.isEdit) {
            return Tick02Icon;
        }
        return SentIcon;
    });

    function handleSendButtonKeyDown(e: KeyboardEvent) {
        if (e.key === 'Escape' && !composerContext.mode.isDefault) {
            e.preventDefault();
            composerContext.mode.exit();
        }
    }

    async function handleImprovement() {
        if (!composerContext.guard.canSend) {
            return;
        }

        composerContext.forcedActive = true;
        try {
            composerContext.message = await oldUiBridge.triggerImproveMessage(
                composerContext.message,
                composerContext.systemPrompt
            );
        } catch (error) {
            console.error('Error fetching improvement suggestions:', error);
            toastContext.error(__('chat.composer.actions.improvementError'));
        } finally {
            composerContext.forcedActive = false;
        }
    }

</script>

{#if composerContext.guard.showsAiUiElements}
    <div transition:growTransition={{mode: 'horizontal'}}>
        <ButtonWithTooltip
            tooltip={__('chat.composer.actions.improveTooltip')}
            size="xs"
            variant="ghost"
            iconRight={ArtificialIntelligence08Icon}
            onclick={handleImprovement}
            disabled={!composerContext.guard.canSend || composerContext.guard.disablesFeature('suggestions')}
        />
    </div>
{/if}

{#if showCancel}
    <div transition:growTransition={{mode: 'horizontal'}}>
        <ButtonWithTooltip
            iconRight={SquareIcon}
            tooltip={cancelTooltip}
            size="xs"
            variant="stroke"
            onclick={cancelAction}
        >
            <Breakpoint>
                {#snippet bpMdAndBigger()}
                    {__('chat.composer.actions.cancelLabel')}
                {/snippet}
            </Breakpoint>
        </ButtonWithTooltip>
    </div>
{/if}

{#if !(composerContext.sendStatus?.active && composerContext.sendStatus?.canBeAborted)}
    <div transition:growTransition={{mode: 'horizontal'}}>
        <ButtonWithTooltip
            bind:ref={buttonRef}
            tooltip={sendTooltip}
            disabled={!composerContext.guard.canSend}
            variant="accent"
            iconRight={SendIcon}
            size="xs"
            class="chat-send-btn"
            onkeydown={handleSendButtonKeyDown}
            onclick={onSend}
        >
            <Breakpoint>
                {#snippet bpMdAndBigger()}
                    {sendLabel}
                {/snippet}
            </Breakpoint>
        </ButtonWithTooltip>
    </div>
{/if}

<style>
    /* Inactive (disabled) send button: the fill variant defaults to the
       slightly blue-tinted --color-bg-secondary. Combine with .btn--fill so
       this out-specifies Button's own disabled rule. */
    :global(.btn--fill.chat-send-btn:disabled) {
        --btn-bg: var(--color-surface-light);
    }
</style>
