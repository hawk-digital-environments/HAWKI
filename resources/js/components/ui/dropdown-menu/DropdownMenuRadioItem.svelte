<!--
  @component A single radio option inside a `DropdownMenuRadioGroup`.
  Selecting it deselects all sibling `DropdownMenuRadioItem`s in the same
  group. Must be a descendant of a `DropdownMenuRadioGroup` — it renders the
  bits-ui `RadioItem` primitive, which reads the group's shared value from
  context.

  The selected state is shown either as a dot on the left (`indicator="dot"`,
  the default) or as a check mark on the right (`indicator="check"`, matching
  `DropdownMenuCheckboxItem` — use it for pickers in a `DropdownMenuSub`).

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
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import Tick02Icon from '../icons/iconset/Tick02Icon.svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The value this item represents. Must be unique within its DropdownMenuRadioGroup. */
        value: string;
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** How the selected state is displayed. @defaultValue 'dot' */
        indicator?: 'dot' | 'check';
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
        closeOnSelect = true,
        children,
        class: className,
        iconLeft: IconLeft,
        iconRight: IconRight,
        ...restProps
    }: Props = $props();
</script>

<DropdownMenuPrimitive.RadioItem {value} {disabled} {closeOnSelect}>
    {#snippet child({props, checked: isChecked})}
        <div {...mergeProps({class: `dropdown-radio-item indicator--${indicator}${className ? ` ${className}` : ''}`}, restProps, props)}>
            <span class="dropdown-item-indicator">
                {#if isChecked}
                    {#if indicator === 'check'}
                        <Tick02Icon size={12}/>
                    {:else}
                        <span class="dropdown-radio-dot"></span>
                    {/if}
                {/if}
            </span>
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
        cursor: default;
        align-items: center;
        border-radius: var(--corner-sm);
        padding-block: var(--space-1_5);
        padding-right: var(--space-2, calc(0.25rem * 2));
        padding-left: var(--space-8, calc(0.25rem * 8));
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        outline: none;
        user-select: none;
        transition: background-color var(--duration-fast, 150ms);
    }

    .dropdown-radio-item.indicator--check {
        padding-right: var(--space-8, calc(0.25rem * 8));
        padding-left: var(--space-2, calc(0.25rem * 2));
    }

    .dropdown-radio-item[data-highlighted] {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .dropdown-radio-item[data-disabled] {
        pointer-events: none;
        opacity: 0.5;
    }

    .dropdown-item-indicator {
        position: absolute;
        left: var(--space-2, calc(0.25rem * 2));
        display: flex;
        height: calc(0.25rem * 3.5);
        width: calc(0.25rem * 3.5);
        align-items: center;
        justify-content: center;
    }

    .indicator--check .dropdown-item-indicator {
        left: auto;
        right: var(--space-2, calc(0.25rem * 2));
        color: var(--color-text);
    }

    .dropdown-radio-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: var(--corner-full);
        background-color: currentColor;
    }

    /* No flex gap on the row itself, so the start icon brings its own; the end
       icon rides the row's end edge. The classes live on the icon components'
       svgs, hence :global. */
    .dropdown-radio-item :global(.dropdown-item-icon-start) {
        margin-inline-end: var(--space-2);
    }

    .dropdown-radio-item :global(.dropdown-item-icon-end) {
        margin-inline-start: auto;
    }
</style>
