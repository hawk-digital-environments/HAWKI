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
  `iconLeft`/`iconRight` slot icons into the row — the end one rides the row's end edge.

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
    import {useMenuListRow} from '$lib/components/ui/menu-list/MenuListContext.svelte.js';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import Tick02Icon from '../icons/iconset/Tick02Icon.svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The value this item represents. Must be unique within its DropdownMenuRadioGroup. */
        value: string;
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** What marks the selected row. @defaultValue 'dot' */
        indicator?: 'dot' | 'check' | 'none';
        /** Which end of the row the indicator sits at. @defaultValue 'start' */
        indicatorSide?: 'start' | 'end';
        /** When false, the menu stays open after selection. @defaultValue true */
        closeOnSelect?: boolean;
        /** Item label content. */
        children?: Snippet;
        /** An optional icon between the state indicator and the item's content. */
        iconLeft?: IconComponent;
        /** An optional icon after the item's content; pushed to the row's end edge. */
        iconRight?: IconComponent;
    }

    const {
        value,
        disabled = false,
        indicator = 'dot',
        indicatorSide = 'start',
        closeOnSelect = true,
        children,
        class: className,
        iconLeft: IconLeft,
        iconRight: IconRight,
        ...restProps
    }: Props = $props();

    // Inside a MenuList (every DropdownMenu provides one) the row is highlighted by the
    // list's sliding "magnetic" hover instead of its own :hover background.
    const listRow = useMenuListRow();
    const magnetic = $derived(!!listRow && !disabled);
</script>

<DropdownMenuPrimitive.RadioItem {value} {disabled} {closeOnSelect}>
    {#snippet child({props, checked: isChecked})}
        <div {@attach magnetic ? listRow : null} {...mergeProps({
            class: [
                'dropdown-radio-item',
                `dropdown-item--indicator-${indicator === 'none' ? 'none' : indicatorSide}`,
                magnetic && 'magnetic',
                className
            ]
        }, restProps, props)}>
            {#if indicator !== 'none'}
                <span class="dropdown-item-indicator">
                    {#if isChecked}
                        {#if indicator === 'check'}
                            <Tick02Icon size={12} aria-hidden="true"/>
                        {:else}
                            <span class="dropdown-radio-dot"></span>
                        {/if}
                    {/if}
                </span>
            {/if}
            {#if IconLeft}
                <IconLeft size="14" class="dropdown-item-icon-start"/>
            {/if}
            {@render children?.()}
            {#if IconRight}
                <IconRight size="14" class="dropdown-item-icon-end"/>
            {/if}
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
    .dropdown-radio-item:is(:focus-visible, :has(:focus-visible)) {
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

    .dropdown-radio-item:is([data-highlighted]:not(.magnetic), [data-highlighted]:focus-visible, :hover:not([data-disabled], .magnetic)) {
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
        color: var(--color-text);
    }

    .dropdown-item--indicator-end > .dropdown-item-indicator {
        right: var(--space-2, calc(0.25rem * 2));
        color: var(--color-text);
    }

    .dropdown-radio-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: var(--corner-full);
        background-color: currentColor;
    }

    /* The end icon rides the row's end edge. The class lives on the icon
       component's svg, hence :global. */
    .dropdown-radio-item :global(.dropdown-item-icon-end) {
        margin-inline-start: auto;
    }
</style>
