<!--
  @component The composer's right-hand action cluster: an "improve message"
  button (AI-only, sends the draft through `oldUiBridge.triggerImproveMessage`),
  a cancel/abort button, and the primary send button. Which buttons are shown
  and what they say depends entirely on `ComposerContext` state:

  - Improve button only shows when `guard.showsAiUiElements` is true.
  - Cancel button shows either while a non-default mode (edit/thread) is
    active but not yet sending, or while an abortable send is in progress —
    its label/action/tooltip adapt to which case applies.
  - Send button's icon/label switch to a checkmark + "Save" in edit mode,
    and it hides while an abortable send is active (the
    cancel button takes its place).

  Renders nothing itself beyond these buttons — layout (flex row, gaps) is the
  parent's responsibility.

  @example
  ```svelte
  <div class="chat-bottom-right">
      <ComposerActionButtons onSend={handleSend} bind:buttonRef={sendButtonEl}/>
  </div>
  ```
-->
<script lang="ts">

    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import SentIcon from '$lib/components/ui/icons/iconset/SentIcon.svelte';
    import ArtificialIntelligence08Icon from '$lib/components/ui/icons/iconset/ArtificialIntelligence08Icon.svelte';
    import SquareIcon from '$lib/components/ui/icons/iconset/SquareIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const toastContext = useToastContext();
    const composerContext = useComposerContext();
    const {__} = useTranslator();

    interface Props {
        /** Called when the send button is clicked and `guard.canSend` allows it. The button
         *  stays focusable while sending isn't allowed (`aria-disabled`, reason exposed via
         *  `aria-describedby`), so the click handler guards the call itself. Typically wired
         *  to `ComposerContext.send()` plus response handling in the parent (see
         *  `ChatComposer.svelte`'s `handleSend`). */
        onSend?: () => void;
        /** Bindable reference to the send `<button>` element, e.g. so `ComposerFocusWrap`
         *  can focus it as a fallback when the textarea is disabled. */
        buttonRef?: HTMLButtonElement | null;
    }

    let {
        onSend,
        buttonRef = $bindable(null)
    }: Props = $props();

    const uid = $props.id();
    const sendHintId = `${uid}-send-hint`;

    const canSend = $derived(composerContext.guard.canSend);

    // Doubles as the button's accessible description, so the reason the button
    // can't be used right now is read out along with it.
    const sendTooltip = $derived(__(composerContext.guard.cannotSendReason ?? 'chat.composer.actions.sendTooltip'));

    function handleSendClick() {
        if (!composerContext.guard.canSend) {
            return;
        }
        onSend?.();
    }

    const sendLabel = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return __('chat.composer.actions.saveLabel');
        }
        return __('chat.composer.actions.sendLabel');
    });

    const isNotSendingInNonDefaultMode = $derived.by(() => !composerContext.sendStatus?.active && !composerContext.mode.isDefault);
    const isSendingButCanAbort = $derived.by(() => composerContext.sendStatus?.active && composerContext.sendStatus.canBeAborted);

    const showCancel = $derived.by(() => isNotSendingInNonDefaultMode || isSendingButCanAbort);

    const cancelTooltip = $derived.by(() => {
        if (isNotSendingInNonDefaultMode) {
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
        if (composerContext.mode.isEdit) {
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
            composerContext.message = await composerContext.improveMessage(
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
            aria-label={__('chat.composer.actions.cancelLabel')}
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
        <!-- aria-disabled instead of disabled keeps the button reachable, so the
             reason it can't be used (aria-describedby) is discoverable by AT. -->
        <ButtonWithTooltip
            bind:ref={buttonRef}
            tooltip={sendTooltip}
            aria-label={sendLabel}
            aria-describedby={sendHintId}
            aria-disabled={!canSend}
            aria-keyshortcuts="Enter"
            variant="accent"
            iconRight={SendIcon}
            size="xs"
            class="chat-send-btn"
            onkeydown={handleSendButtonKeyDown}
            onclick={handleSendClick}
        >
            <Breakpoint>
                {#snippet bpMdAndBigger()}
                    {sendLabel}
                {/snippet}
            </Breakpoint>
        </ButtonWithTooltip>
        <span id={sendHintId} class="u-sr-only">{sendTooltip}</span>
    </div>
{/if}

<style>
    /* Inactive send button (aria-disabled, still focusable): mirror Button's
       disabled fill look. Combined selectors out-specify the accent variant's
       own background and hover rules. */
    :global(.btn.chat-send-btn[aria-disabled="true"]),
    :global(.btn.chat-send-btn[aria-disabled="true"]:hover) {
        --btn-bg: var(--color-surface-light);
        --btn-color: var(--color-text-disabled);
        cursor: not-allowed;
    }
</style>
