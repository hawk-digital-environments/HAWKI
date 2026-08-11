<!--
  @component Makes the whole composer card behave like one big click target
  for focusing the message input: any click on the card that doesn't land on
  an interactive element (button/link/input/textarea/select/`role="button"`/
  `role="menuitem"`) focuses the textarea (cursor at the end) or, if the
  textarea is disabled, falls back to focusing the send button. This is why
  the composer card has `cursor: text` — clicking empty space "feels" like
  clicking into a text field even though the actual `<textarea>` might be
  small or positioned elsewhere.

  Also subscribes to `ComposerContext.onFocusInput` so that
  `composerContext.focusInput()` (called e.g. after a mode pre-fills the
  message, or after sending) reuses the same focus logic. Uses a `setTimeout`
  before focusing so the textarea/button's enabled state has settled first
  (e.g. right after a send completes and re-enables the input).

  @example
  ```svelte
  <ComposerFocusWrap {textareaEl} {buttonEl} class="chat-composer-card">
      // textarea, buttons, chips, etc.
  </ComposerFocusWrap>
  ```
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';

    const composerContext = useComposerContext();

    interface Props {
        /** Current textarea element (typically bound from `ComposerTextarea`'s `ref`).
         *  Focused (with the caret moved to the end of its value) on qualifying clicks
         *  and whenever `composerContext.focusInput()` is called, unless disabled. */
        textareaEl: HTMLTextAreaElement | null;
        /** Fallback focus target (typically the send button) used when `textareaEl` is
         *  missing or disabled — e.g. while the model is unusable so the input is hidden. */
        buttonEl: HTMLButtonElement | null;
        /**
         * CSS class(es) to apply to the container element.
         */
        class?: string | { [key: string]: boolean } | Array<string>;

        /** The composer's content, rendered inside the click-to-focus wrapper `<div>`. */
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
                    const len = _textAreaEl.value.length;
                    _textAreaEl.setSelectionRange(len, len);
                    return;
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
