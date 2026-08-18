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
    }

    let oldMessage = composerContext.message;
    $effect(() => {
        if (ref && composerContext.message !== oldMessage) {
            ref.style.height = 'auto';
            ref.style.height = Math.min(ref.scrollHeight, 250) + 'px';
        }
    });

    const toastContext = useToastContext();

    /** Renders the byte limit from a file issue as human-readable megabytes for the error toast. */
    function formatMaxSize(maxSize: number | undefined): string {
        if (maxSize === undefined || !Number.isFinite(maxSize)) {
            return '';
        }
        return `${Math.round(maxSize / (1024 * 1024))} MB`;
    }

    function handlePaste(e: ClipboardEvent) {
        if (e.clipboardData && e.clipboardData.types[0] === "Files") {
            e.preventDefault();
            const files = e.clipboardData.files;
            for (let file of files) {
                const issues = composerContext.attachments.add(file)
                if (issues !== true) {
                    for (let issue of issues) {
                        console.log(file)
                        toastContext.error(__(`chat.composer.error.${issue.type}`, {
                            name: issue.file.name,
                            type: file.name.indexOf(".") !== -1 ? ("." + file.name.split('.').pop()) : __("chat.composer.error.unknown_file"),
                            maxSize: formatMaxSize(issue.maxSize)
                        }))
                    }
                }
            }
        }
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
            aria-label={textareaLabel}
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
