<!--
  @component The composer's main message input. Renders the assistant toggle
  button (`ComposerAssistantButton`) inline at its start, an auto-growing
  `Textarea` bound to `ComposerContext.message`, and handles the
  Enter-to-send / Shift+Enter-newline / Escape-to-exit-mode keyboard
  shortcuts. Hides itself entirely when the current mode disables the
  `'input'` feature (e.g. while a non-abortable send is in flight).

  Reads/writes `ComposerContext` directly — there is no props-based way to
  set the message text; bind to `composerContext.message` from a parent if
  you need to observe or set it externally.

  @example
  ```svelte
      // inside a component nested under createComposerContext()
  <ComposerTextarea bind:ref={textareaEl} onSend={handleSend}/>
  ```
-->
<script lang="ts">

    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import ComposerAssistantButton from '$plugins/core/modules/chat/components/composer/ComposerAssistantButton.svelte';

    const composerContext = useComposerContext();
    const {__} = useTranslator();

    interface Props {
        /** Called when the user presses Enter (without Shift) to submit the message.
         *  The textarea itself does not clear or send anything — the parent (typically
         *  `ChatComposer`) owns the actual send flow via `ComposerContext.send()`. */
        onSend?: () => void;
        /** Bindable reference to the underlying `<textarea>` element, e.g. so a parent
         *  can pass it to `ComposerFocusWrap` for click-to-focus behaviour. */
        ref?: HTMLTextAreaElement | null;
    }

    let {
        onSend,
        ref = $bindable(null)
    }: Props = $props();

    const textareaPlaceholder = $derived.by(() => {
        if (composerContext.type === 'aiConv') {
            return __('chat.composer.textareaPlaceholder', {model: composerContext.model?.current.label ?? ''});
        } else {
            return __('chat.composer.textareaPlaceholderRoom');
        }
    });

    function handleKeyDown(e: KeyboardEvent) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            onSend?.();
        }
        if (e.key === 'Escape' && !composerContext.mode.isDefault) {
            e.preventDefault();
            composerContext.mode.exit();
        }
    }

    let oldMessage = composerContext.message;
    $effect(() => {
        if (ref && composerContext.message !== oldMessage) {
            ref.style.height = 'auto';
            ref.style.height = Math.min(ref.scrollHeight, 250) + 'px';
        }
    });
</script>
{#if !composerContext.guard.disablesFeature('input', false)}
    <div
        class={'chat-textarea-wrapper'}
        transition:growTransition
    >
        <ComposerAssistantButton/>
        <Textarea
            bind:ref={ref}
            bind:value={composerContext.message}
            disabled={composerContext.sendStatus?.sending}
            onkeydown={handleKeyDown}
            class="chat-textarea"
            rows={1}
            placeholder={textareaPlaceholder}
        />
    </div>
{/if}

<style>
    .chat-textarea-wrapper {
        display: flex;
        align-items: flex-end;
        padding-left: 0.5rem;
    }

    /* ── Textarea ─────────────────────────────────────────────────────── */
    :global(.chat-textarea.chat-textarea) {
        width: 100%;
        min-height: 0.8lh;
        height: auto;
        resize: none;
        background: transparent;
        border: none;
        outline: none;
        padding-block: calc(var(--space-1) * 1);
        line-height: 1.25rem;
        box-shadow: none;

        &:focus,
        &:focus-visible {
            outline: none;
            border: none;
            box-shadow: none;
        }
    }
</style>
