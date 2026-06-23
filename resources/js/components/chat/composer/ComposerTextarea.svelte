<script lang="ts">

    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import ComposerAssistantButton from '$lib/components/chat/composer/ComposerAssistantButton.svelte';

    const composerContext = useComposerContext();

    interface Props {
        onSend?: () => void;
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
