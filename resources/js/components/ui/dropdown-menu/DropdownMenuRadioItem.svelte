<!--
  @component A single radio option inside a `DropdownMenuRadioGroup`.
  Selecting it deselects all sibling `DropdownMenuRadioItem`s in the same
  group. Must be a descendant of a `DropdownMenuRadioGroup` — it renders the
  bits-ui `RadioItem` primitive, which reads the group's shared value from
  context.

  `indicator` picks what marks the selected row: the radio `'dot'` (default), the same
  `'check'` the checkbox rows use — for a list that reads as "which one is on" rather than
  a form control — or `'none'` for a row that shows its own state. `indicatorSide` moves it
  to the other end; either way only the side carrying it reserves the room.

  A disabled row stays readable rather than being dimmed as a whole, and still takes the
  pointer, so an unavailable option can explain itself (a status dot, a tooltip) instead of
  going quiet — the same treatment `DropdownMenuCheckboxItem` gives its disabled rows.
  Secondary controls inside the row can hide until it is hovered or focused by reading
  `--dropdown-item-action-opacity`.

  ```svelte
  <DropdownMenuRadioGroup bind:value={sortOrder}>
      <DropdownMenuRadioItem value="name">{__('menu.sortByName')}</DropdownMenuRadioItem>
      <DropdownMenuRadioItem value="date">{__('menu.sortByDate')}</DropdownMenuRadioItem>
  </DropdownMenuRadioGroup>
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import Tick02Icon from '../icons/iconset/Tick02Icon.svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The value this item represents. Must be unique within its DropdownMenuRadioGroup. */
        value: string;
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Item label content. */
        children?: Snippet;
        /** What marks the selected row. @defaultValue 'dot' */
        indicator?: 'dot' | 'check' | 'none';
        /** Which end of the row the indicator sits at. @defaultValue 'start' */
        indicatorSide?: 'start' | 'end';
    }

    const {
        value,
        disabled = false,
        children,
        class: className,
        indicator = 'dot',
        indicatorSide = 'start',
        ...restProps
    }: Props = $props();
</script>

<DropdownMenuPrimitive.RadioItem {value} {disabled}>
    {#snippet child({props, checked: isChecked})}
        <div {...mergeProps({
            class: [
                'dropdown-radio-item',
                `dropdown-item--indicator-${indicator === 'none' ? 'none' : indicatorSide}`,
                className
            ]
        }, restProps, props)}>
            {#if indicator !== 'none'}
                <span class="dropdown-item-indicator">
                    {#if isChecked}
                        {#if indicator === 'check'}
                            <Tick02Icon size={12}/>
                        {:else}
                            <span class="dropdown-radio-dot"></span>
                        {/if}
                    {/if}
                </span>
            {/if}
            {@render children?.()}
        </div>
    {/snippet}
</DropdownMenuPrimitive.RadioItem>

<style>
    .dropdown-radio-item {
        position: relative;
        display: flex;
        /* The whole row picks the option, so it reads as clickable. */
        cursor: pointer;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        border-radius: var(--corner-sm);
        padding-block: var(--space-1_5);
        padding-inline: var(--space-2, calc(0.25rem * 2));
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        outline: none;
        user-select: none;
        transition: background-color var(--duration-fast, 150ms);

        /* Read by secondary controls in the row (see `MenuPinButton`) so they only surface
           on the row being pointed at or focused. */
        --dropdown-item-action-opacity: 0;
    }

    /* Only the side carrying the indicator reserves room for it. */
    .dropdown-radio-item.dropdown-item--indicator-start {
        padding-left: var(--space-8, calc(0.25rem * 8));
    }

    .dropdown-radio-item.dropdown-item--indicator-end {
        padding-right: var(--space-8, calc(0.25rem * 8));
    }

    .dropdown-radio-item:hover,
    .dropdown-radio-item:focus-within {
        --dropdown-item-action-opacity: 1;
    }

    /* Icons in a row keep their size when the label beside them has to give way. */
    .dropdown-radio-item :global(svg) {
        flex-shrink: 0;
    }

    /* Touch-sized rows in the mobile sheet, where the same menu is finger-driven. */
    :global(.dropdown-content--sheet) .dropdown-radio-item {
        min-height: 2.5rem;
    }

    .dropdown-radio-item[data-highlighted] {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    /* Shown but not selectable: an unavailable option keeps its text and its pointer, so
       whatever explains why (a status dot, a tooltip) still works. */
    .dropdown-radio-item[data-disabled] {
        color: var(--color-text-disabled);
        cursor: not-allowed;
        opacity: 1;
        pointer-events: auto;
    }

    .dropdown-radio-item[data-disabled][data-highlighted] {
        background-color: transparent;
        color: var(--color-text-disabled);
    }

    .dropdown-item-indicator {
        position: absolute;
        display: flex;
        height: calc(0.25rem * 3.5);
        width: calc(0.25rem * 3.5);
        align-items: center;
        justify-content: center;
    }

    .dropdown-item--indicator-start > .dropdown-item-indicator {
        left: var(--space-2, calc(0.25rem * 2));
    }

    .dropdown-item--indicator-end > .dropdown-item-indicator {
        right: var(--space-2, calc(0.25rem * 2));
    }

    .dropdown-radio-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: var(--corner-full);
        background-color: currentColor;
    }
</style>
