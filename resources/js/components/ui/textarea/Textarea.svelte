<!--
  @component Resizable multi-line text input. Forwards all native `<textarea>`
  attributes and supports two-way binding via `bind:value`.

  @example
  ```svelte
  <Textarea bind:value={draft} placeholder="Write a message..." rows={3}/>
  ```
-->
<script lang="ts">
    import type {HTMLTextareaAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    interface Props extends HTMLTextareaAttributes {
        /** Current text value. Supports bind:value for two-way binding. */
        value?: string;
        /** Bindable reference to the underlying textarea element. */
        ref?: HTMLTextAreaElement | null;
        /** Accessible label forwarded to the native textarea. */
        ariaLabel?: string;
    }

    let {value = $bindable(''), ref = $bindable(undefined), ariaLabel, ...restProps}: Props = $props();
</script>

<textarea
    {...mergeProps({class: 'textarea', 'aria-label': ariaLabel}, restProps)}
    bind:this={ref}
    bind:value
></textarea>

<style>
    .textarea {
        --textarea-border: var(--color-border);
        --textarea-bg: transparent;

        display: flex;
        min-height: 60px;
        width: 100%;
        border-radius: var(--corner-md);
        border: var(--border);
        border-color: var(--textarea-border);
        background-color: var(--textarea-bg);
        padding-inline: var(--space-3, calc(0.25rem * 3));
        padding-block-start: var(--space-2, calc(0.25rem * 2));
        padding-block-end: var(--space-4, calc(0.25rem * 4));
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        font-family: inherit;
        color: var(--color-text);
        box-shadow: var(--elevation-1);
        resize: vertical;

        &::placeholder {
            color: var(--color-text-muted);
            opacity: 0.75;
        }

        &:focus-visible {
            outline: none;
            --textarea-border: var(--color-focus-ring);
            box-shadow: 0 0 0 2px var(--color-focus-ring);
        }

        &:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
    }
</style>
