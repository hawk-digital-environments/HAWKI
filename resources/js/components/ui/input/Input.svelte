<script lang="ts">
    import type { HTMLInputAttributes } from 'svelte/elements';
    import { mergeProps } from 'bits-ui';

    interface Props extends HTMLInputAttributes {
        value?: string;
        ref?: HTMLInputElement | null;
        ariaLabel?: string;
    }

    let { value = $bindable(''), ref = $bindable(undefined), ariaLabel, ...restProps }: Props = $props();
</script>

<input bind:this={ref} bind:value {...mergeProps({class: 'input', 'aria-label': ariaLabel}, restProps)}/>

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
