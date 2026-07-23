<!--
  @component Container for a set of `RadioCard`s sharing a single selection.

  Bind `value` to the selected card's value; `onChange` fires on selection.
  Disabling the group disables (and dims) every card it contains.
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {createRadioCardContext} from './RadioCardContext.svelte.js';

    interface Props extends Omit<HTMLAttributes<HTMLDivElement>, 'onchange'> {
        /** The selected value. Leave unset for an uncontrolled group. */
        value?: string;
        /** Disable every card in the group. */
        disabled?: boolean;
        /** Shared `name` applied to each card's radio input. */
        name?: string;
        /** Called with the newly selected value. */
        onChange?: (newValue: string) => void;
    }

    let {
        value = $bindable(''),
        children,
        onChange,
        disabled = false,
        name,
        class: className,
        ...restProps
    }: Props = $props();

    createRadioCardContext(
        () => value,
        (newValue) => {
            if (value === newValue) return;
            value = newValue;
            onChange?.(newValue);
        },
        () => disabled,
        () => name
    );
</script>

<div
    {...mergeProps(
        {
            class: `radio-card-group${className ? ` ${className}` : ''}`,
            role: 'radiogroup',
            'aria-disabled': disabled ? 'true' : undefined,
            'data-disabled': disabled ? '' : undefined,
        },
        restProps,
    )}
>
    {@render children?.()}
</div>

<style>
    .radio-card-group {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: var(--space-1, 0.25rem);
    }

    .radio-card-group[data-disabled] {
        opacity: 0.5;
        pointer-events: none;
    }
</style>
