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
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {reportAttachmentIssues} from '$plugins/core/modules/chat/components/utils/attachmentIssues.js';

    const composerContext = useComposerContext();
    const translator = useTranslator();
    const {__} = translator;

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

    const textareaLabel = $derived(
        composerContext.type === 'aiConv'
            ? __('chat.composer.textareaLabel')
            : __('chat.composer.textareaLabelRoom')
    );

    function handleKeyDown(e: KeyboardEvent) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            onSend?.();
        }
        if (e.key === 'Escape' && !composerContext.mode.isDefault) {
            e.preventDefault();
            composerContext.mode.exit();
        }
        // The paste event carries no modifier state, so remember Ctrl/Cmd+Shift+V
        // here; any other keystroke clears it again.
        forceInlinePaste = e.shiftKey && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v';
    }

    let oldMessage = composerContext.message;
    $effect(() => {
        if (ref && composerContext.message !== oldMessage) {
            ref.style.height = 'auto';
            ref.style.height = Math.min(ref.scrollHeight, 250) + 'px';
        }
    });

    const toastContext = useToastContext();

    /** Pasted plain text longer than this (in characters) is attached as a `.txt` file instead of inserted inline. */
    const PASTE_AS_FILE_THRESHOLD = 1000;

    /** True while the pending paste was triggered via Ctrl/Cmd+Shift+V, which forces inline text insertion. */
    let forceInlinePaste = false;

    function handlePaste(e: ClipboardEvent) {
        const pasteInline = forceInlinePaste;
        forceInlinePaste = false;

        const clipboard = e.clipboardData;
        if (!clipboard) return;

        if (Array.from(clipboard.types).includes('Files')) {
            e.preventDefault();
            reportAttachmentIssues(translator, toastContext, composerContext.attachments.add(clipboard.files));
            return;
        }

        if (pasteInline) return;

        const text = clipboard.getData('text/plain');
        if (text.length <= PASTE_AS_FILE_THRESHOLD || composerContext.guard.disablesFeature('attachments')) return;

        const file = new File([text], __('chat.composer.pastedTextFileName') + '.txt', {type: 'text/plain'});
        const result = composerContext.attachments.add(file);
        // If the file can't be attached (e.g. text/plain not allowed), fall back to inserting the text inline.
        if (result !== true) return;

        e.preventDefault();
    }

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
            onpaste={handlePaste}
            class="chat-textarea"
            rows={1}
            ariaLabel={textareaLabel}
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
