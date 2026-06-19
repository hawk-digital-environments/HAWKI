<script lang="ts">

    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {__} from '$lib/utils/translator.js';

    const composerContext = useComposerContext();

    interface Props {
        onSend?: () => void;
        ref?: HTMLTextAreaElement | null;
    }

    let {
        onSend,
        ref = $bindable(null)
    }: Props = $props();

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
        <Textarea
            bind:ref={ref}
            bind:value={composerContext.message}
            disabled={composerContext.sendStatus?.sending}
            onkeydown={handleKeyDown}
            class="chat-textarea"
            rows={1}
            placeholder={__('chat.composer.textareaPlaceholder')}
        />
    </div>
{/if}
