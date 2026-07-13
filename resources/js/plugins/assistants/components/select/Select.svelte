<!--
  @component Single-select dropdown. Renders one `<option>` per entry in
  `options` and marks the one matching `value` as selected. Entries can be
  plain strings (value = label) or `{value, label?, disabled?}` objects —
  the object form supports ids that differ from their display label and
  disabled placeholder entries. Forwards all native `<select>` attributes.
  Field chrome (label, error, container) is owned by the caller — see
  BuilderInput.svelte.
-->
<script lang="ts">
    import type {HTMLSelectAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    /** A select entry whose value differs from (or carries extra state beyond) its label. */
    export interface SelectOption {
        value: string;
        label?: string;
        disabled?: boolean;
    }

    interface Props extends HTMLSelectAttributes {
        /** Options to render: plain strings or SelectOption objects. */
        options?: (string | SelectOption)[];
        /** Currently selected value. */
        value?: string;
        /** Bindable reference to the underlying select element. */
        ref?: HTMLSelectElement | null;
    }

    let {options = [], value, ref = $bindable(undefined), ...restProps}: Props = $props();

    const normalizedOptions = $derived(
        options.map((option) => (typeof option === 'string' ? {value: option} : option))
    );
</script>

<select {...mergeProps({class: 'select'}, restProps)} bind:this={ref}>
    {#each normalizedOptions as option (option.value)}
        <option value={option.value} disabled={option.disabled} selected={option.value === value}>
            {option.label ?? option.value}
        </option>
    {/each}
</select>

<!--
  Styling matched to the sibling Input/Textarea primitives so the select is
  self-contained and owns its own look.
-->
<style>
    .select {
        --select-border: var(--color-border);

        width: 100%;
        box-sizing: border-box;
        min-height: 2.5rem;
        padding: var(--space-2) var(--space-2_5);
        border-radius: var(--corner-md);
        border: var(--border);
        border-color: var(--select-border);
        background-color: transparent;
        font-size: var(--font-size-sm);
        font-family: inherit;
        color: var(--color-text);
        box-shadow: var(--elevation-1);
        transition:
            border-color var(--duration-fast),
            box-shadow var(--duration-fast);
    }

    .select:focus-visible {
        outline: none;
        --select-border: var(--color-focus-ring);
        box-shadow: 0 0 0 1px var(--color-focus-ring);
    }

    .select:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }
</style>
