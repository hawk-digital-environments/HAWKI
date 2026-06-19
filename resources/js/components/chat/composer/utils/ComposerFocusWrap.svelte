<script lang="ts">
    import type {Snippet} from 'svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';

    const composerContext = useComposerContext();

    interface Props {
        textareaEl: HTMLTextAreaElement | null;
        buttonEl: HTMLButtonElement | null;
        /**
         * CSS class(es) to apply to the container element.
         */
        class?: string | { [key: string]: boolean } | Array<string>;

        children: Snippet;
    }

    const {textareaEl, buttonEl, children, class: cssClass}: Props = $props();

    const doFocus = $derived.by(() => {
        const _textAreaEl = textareaEl;
        const _buttonEl = buttonEl;
        return () => {
            // Wait until the next tick to ensure that the textarea and button have reached their final state
            // (e.g., enabled/disabled) before trying to focus them
            setTimeout(() => {
                if (_textAreaEl && !_textAreaEl.disabled) {
                    _textAreaEl.focus();
                }
                if (_buttonEl && !_buttonEl.disabled) {
                    _buttonEl.focus();
                }
            });
        };
    });

    function focusTextareaFromComposer(e: MouseEvent) {
        const target = e.target;

        if (
            target instanceof Element &&
            target.closest('button, a, input, textarea, select, [role="button"], [role="menuitem"]')
        ) {
            return;
        }

        doFocus();
    }

    $effect(() => composerContext.onFocusInput(doFocus));
</script>
<!-- svelte-ignore a11y_no_static_element_interactions -->
<!-- svelte-ignore a11y_click_events_have_key_events -->
<div
    class={cssClass}
    onclick={focusTextareaFromComposer}
>
    {@render children()}
</div>
