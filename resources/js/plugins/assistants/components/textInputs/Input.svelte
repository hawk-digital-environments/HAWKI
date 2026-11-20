<!--
  @component Single-line text input. Forwards all native `<input>` attributes
  and supports two-way binding via `bind:value`. Field chrome (label, error,
  container) is owned by the caller — see BuilderInput.svelte.
-->
<script lang="ts">
    import type {HTMLInputAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    interface Props extends HTMLInputAttributes {
        /** Current value. Supports bind:value for two-way binding. */
        value?: string;
        /** Bindable reference to the underlying input element. */
        ref?: HTMLInputElement | null;
    }

    let {value = $bindable(''), ref = $bindable(undefined), ...restProps}: Props = $props();
</script>

<input
    {...mergeProps({class: 'input', type: 'text'}, restProps)}
    bind:this={ref}
    bind:value
/>

<!--
  Styling ported from the shared design-system Input primitive
  (hawki-frontend-only: components/ui/input) to match the sibling Textarea
  primitive. Self-contained here so the field owns its own look.
-->
<style>
    .input {
        --input-border: var(--color-border);

        width: 100%;
        box-sizing: border-box;
        min-height: 2.5rem;
        padding: var(--space-2) var(--space-2_5);
        border-radius: var(--corner-md);
        border: var(--border);
        border-color: var(--input-border);
        background-color: transparent;
        font-size: var(--font-size-sm);
        font-family: inherit;
        color: var(--color-text);
        box-shadow: var(--elevation-1);
        transition:
            border-color var(--duration-fast),
            box-shadow var(--duration-fast);
    }

    .input::placeholder {
        color: var(--color-text-muted);
    }

    .input:focus-visible {
        outline: none;
        --input-border: var(--color-focus-ring);
        box-shadow: 0 0 0 1px var(--color-focus-ring);
    }

    .input:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }
</style>
