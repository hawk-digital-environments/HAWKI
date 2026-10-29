<!--
  @component A menu item with a checkbox indicator inside a `DropdownMenu`.
  Use `bind:checked` for two-way state, or `checked` + `onCheckedChange` for
  externally-controlled state (e.g. `entry.active` / `entry.onToggle` in
  `ToolMenuListItem.svelte`). Clicking anywhere on the row toggles it, unlike
  `DropdownMenuSwitchItem`, which can restrict toggling to its indicator.

  `indicator` moves or drops the check: `'none'` is for rows that show their checked state
  themselves (over an icon swatch, say), and drops the padding the indicator would reserve.
  `iconLeft`/`iconRight` slot icons into the row — the end one rides the row's end edge.
  Secondary controls inside the row — a pin, an info button — can hide until the row is
  hovered or focused by reading `--dropdown-item-action-opacity`, which this row sets.

  ```svelte
  <DropdownMenu trigger="Tools">
      <DropdownMenuCheckboxItem checked={entry.active} onCheckedChange={entry.onToggle}>
          {#snippet children(checked)}
              <span>{entry.tool.displayName}</span>
          {/snippet}
      </DropdownMenuCheckboxItem>
  </DropdownMenu>
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import {useMenuList} from '$lib/components/ui/menu-list/MenuListContext.svelte.js';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import type {Attachment} from 'svelte/attachments';
    import Tick02Icon from '../icons/iconset/Tick02Icon.svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    interface Props extends Omit<HTMLAttributes<HTMLDivElement>, 'children'> {
        /** Whether the checkbox is checked. Supports bind:checked. */
        checked?: boolean;
        /** Called when the checked state changes. */
        onCheckedChange?: (checked: boolean) => void;
        /** Whether the checkbox represents a partially selected group. */
        indeterminate?: boolean;
        /** Called when the indeterminate state changes. */
        onIndeterminateChange?: (indeterminate: boolean) => void;
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Item label content. Receives the current checked state, e.g. `{#snippet children(checked)}`. */
        children?: Snippet<[boolean]>;
        /** Set to false to keep the menu open after selecting this item. Defaults to true. */
        closeOnSelect?: boolean;
        /** Bindable reference to the rendered item element. */
        ref?: HTMLDivElement | null;
        /** An optional icon between the state indicator and the item's content. */
        iconLeft?: IconComponent;
        /** An optional icon after the item's content; pushed to the row's end edge. */
        iconRight?: IconComponent;
        /** `check` (default) draws the check; `none` leaves it to the row's own content and
         *  gives the space back. */
        indicator?: 'check' | 'none';
        /** Which end of the row the check sits at. @defaultValue 'end' */
        indicatorSide?: 'start' | 'end';
        /** Inset the enclosing MenuList's sliding highlights for a nested row. */
        inset?: boolean;
    }

    let {
        checked = $bindable(false),
        onCheckedChange,
        indeterminate = $bindable(false),
        onIndeterminateChange,
        disabled = false,
        children,
        class: className,
        closeOnSelect = true,
        ref = $bindable(null),
        iconLeft: IconLeft,
        iconRight: IconRight,
        indicator = 'check',
        indicatorSide = 'end',
        inset = false,
        ...restProps
    }: Props = $props();

    // Inside a MenuList (every DropdownMenu provides one) the row is highlighted by the
    // list's sliding "magnetic" hover instead of its own :hover background.
    const list = useMenuList();
    let listIndex = $state(-1);
    const listRow: Attachment<HTMLDivElement> = (element) => {
        if (!list) return;
        const index = list.register(element);
        listIndex = index;
        return () => {
            list.unregister(index);
            listIndex = -1;
        };
    };
    const magnetic = $derived(!!list && !disabled);

    $effect(() => {
        if (list && listIndex >= 0) {
            list.setState(listIndex, {active: false, inset});
        }
    });
</script>

<DropdownMenuPrimitive.CheckboxItem
    bind:checked
    {onCheckedChange}
    bind:indeterminate
    {onIndeterminateChange}
    {disabled}
    {closeOnSelect}>
    {#snippet child({props, checked: isChecked, indeterminate: isIndeterminate})}
        <div
            bind:this={ref}
            {@attach magnetic ? listRow : null}
            {...mergeProps({
                class: [
                    'dropdown-checkbox-item',
                    `dropdown-item--indicator-${indicator === 'none' ? 'none' : indicatorSide}`,
                    magnetic && 'magnetic',
                    className
                ]
            }, restProps, props)}>
            {#if indicator !== 'none'}
                <span class="dropdown-item-indicator">
                    {#if isIndeterminate}
                        <span class="dropdown-item-indicator-mixed" aria-hidden="true"></span>
                    {:else if isChecked}
                        <Tick02Icon size={12} aria-hidden="true"/>
                    {/if}
                </span>
            {/if}
            {#if IconLeft}
                <IconLeft size="14" class="dropdown-item-icon-start"/>
            {/if}
            {@render children?.(isChecked)}
            {#if IconRight}
                <IconRight size="14" class="dropdown-item-icon-end"/>
            {/if}
        </div>
    {/snippet}
</DropdownMenuPrimitive.CheckboxItem>

<style>
    .dropdown-checkbox-item {
        position: relative;
        display: flex;
        /* The whole row toggles, so it reads as clickable. */
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
    .dropdown-checkbox-item.dropdown-item--indicator-end {
        padding-right: var(--space-8, calc(0.25rem * 8));
    }

    .dropdown-checkbox-item.dropdown-item--indicator-start {
        padding-left: var(--space-8, calc(0.25rem * 8));
    }

    .dropdown-checkbox-item:hover,
    .dropdown-checkbox-item:is(:focus-visible, :has(:focus-visible)) {
        --dropdown-item-action-opacity: 1;
    }

    /* Icons in a row keep their size when the label beside them has to give way. */
    .dropdown-checkbox-item :global(svg) {
        flex-shrink: 0;
    }

    /* Touch-sized rows in the mobile sheet, where the same menu is finger-driven. */
    :global(.dropdown-content--sheet) .dropdown-checkbox-item {
        min-height: 2.5rem;
    }

    .dropdown-checkbox-item:is([data-highlighted]:not(.magnetic), [data-highlighted]:focus-visible, :hover:not([data-disabled], .magnetic)) {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    /* The row a composer `@`/`/` command points at while focus stays in the textarea
       (see `composer/utils/commandMenu.ts`). */
    .dropdown-checkbox-item[data-command-active] {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .dropdown-checkbox-item[data-disabled] {
        color: var(--color-text-disabled);
        cursor: not-allowed;
        opacity: 1;
        pointer-events: auto;
    }

    .dropdown-checkbox-item[data-disabled][data-highlighted] {
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
        color: var(--color-text);
    }

    .dropdown-item-indicator-mixed {
        width: calc(0.25rem * 2);
        height: 1.5px;
        border-radius: 999px;
        background-color: currentColor;
    }

    .dropdown-item--indicator-end > .dropdown-item-indicator {
        right: var(--space-2, calc(0.25rem * 2));
    }

    .dropdown-item--indicator-start > .dropdown-item-indicator {
        left: var(--space-2, calc(0.25rem * 2));
    }

    /* The end icon rides the row's end edge, before the absolutely positioned state
       indicator's reserved zone. The class lives on the icon component's svg,
       hence :global. */
    .dropdown-checkbox-item :global(.dropdown-item-icon-end) {
        margin-inline-start: auto;
    }
</style>
