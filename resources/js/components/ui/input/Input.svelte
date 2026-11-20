<script lang="ts">
    import type { HTMLInputAttributes } from 'svelte/elements';
    import { mergeProps } from 'bits-ui';

    interface Props extends HTMLInputAttributes {
        value?: string;
        ref?: HTMLInputElement | null;
        ariaLabel?: string;
        /** Visually hidden accessible label; plugin forms pair it with their own visible text. */
        label?: string;
    }

    let { value = $bindable(''), ref = $bindable(undefined), ariaLabel, label, ...restProps }: Props = $props();
</script>

<input bind:this={ref} bind:value {...mergeProps({class: 'input', 'aria-label': ariaLabel ?? label}, restProps)}/>

<style>
    .input {
        width: 100%;
        min-height: 2.75rem;
        box-sizing: border-box;
        border: var(--border);
        border-radius: var(--corner-md);
        background: var(--color-surface-raised);
        color: var(--color-text);
        padding: 0 var(--space-3);
        font: inherit;
    }
    .input:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }
    .input:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }
</style>
