<!--
  @component Container for a set of `RadioRow`s sharing a single selection.

  Bind `value` to the selected row's value; `onChange` fires on selection.
  Disabling the group disables every row it contains. The rows sit in a
  `MenuList`, whose sliding highlights mark the selection and the pointer; the
  list is sized by its rows, so the surrounding panel does the scrolling.

  The group is a single Tab stop (the selected row). ArrowUp/ArrowDown move to
  and select the previous/next enabled row, wrapping at the ends; Home/End jump
  to the first/last. ArrowLeft/ArrowRight are left to the surrounding panel.

  Publishes a `RadioRowContext` (see `RadioRowContext.svelte.ts`) that its
  `RadioRow` children read via `getRadioRowContext()` — always wrap
  `RadioRow`s in this group, never render them standalone.

  @example
  ```svelte
  <RadioRowGroup value={variant} onChange={(v) => (variant = v)} name="tool-variant">
      <RadioRow value="auto">Auto</RadioRow>
      <RadioRow value="native">Native</RadioRow>
  </RadioRowGroup>
  ```
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {createRadioRowContext} from './RadioRowContext.svelte.js';
    import MenuList from '$lib/components/ui/menu-list/MenuList.svelte';

    interface Props extends Omit<HTMLAttributes<HTMLDivElement>, 'onchange'> {
        /** The selected value. Leave unset for an uncontrolled group. */
        value?: string;
        /** Disable every row in the group. */
        disabled?: boolean;
        /** Shared `name` applied to each row's radio input. */
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

    const ctx = createRadioRowContext(
        () => value,
        (newValue) => {
            if (value === newValue) return;
            value = newValue;
            onChange?.(newValue);
        },
        () => disabled,
        () => name
    );

    function onkeydown(event: KeyboardEvent) {
        if (!['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
        const group = event.currentTarget as HTMLElement;
        const radios = Array.from(group.querySelectorAll<HTMLElement>('[role="radio"]:not([aria-disabled="true"])'));
        // Only from a radio: keys pressed on a row's trailing controls aren't the group's.
        const current = radios.indexOf(event.target as HTMLElement);
        if (current === -1) return;

        const next = {
            ArrowUp: (current - 1 + radios.length) % radios.length,
            ArrowDown: (current + 1) % radios.length,
            Home: 0,
            End: radios.length - 1
        }[event.key]!;

        event.preventDefault();
        event.stopPropagation();
        radios[next].focus();
        const nextValue = radios[next].dataset.value;
        if (nextValue !== undefined) ctx.value = nextValue;
    }
</script>

<div
    {...mergeProps(
        {
            class: ['radio-row-group', className],
            role: 'radiogroup',
            'aria-disabled': disabled ? 'true' : undefined,
            'data-disabled': disabled ? '' : undefined,
            onkeydown,
        },
        restProps,
    )}
>
    <MenuList class="radio-row-list">
        {@render children?.()}
    </MenuList>
</div>

<style>
    .radio-row-group {
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }

    .radio-row-group :global(.radio-row-list) {
        flex: none;
    }
</style>
