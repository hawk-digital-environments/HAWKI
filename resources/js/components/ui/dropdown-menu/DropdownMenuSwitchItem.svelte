<!--
  @component A menu item with a switch (toggle) indicator inside a
  `DropdownMenu`. Use `bind:checked` for two-way state, or `checked` +
  `onCheckedChange` for externally-controlled state. Visually and behind the
  scenes it is a `DropdownMenuCheckboxItem` styled as a switch.

  By default, like `DropdownMenuCheckboxItem`, clicking anywhere on the row
  toggles it. Set `toggleOnIndicatorOnly` when the row itself also needs its
  own click action (e.g. it opens something else) — in that case only the
  switch indicator toggles the checked state, and the indicator becomes a
  focusable `role="switch"` button in its own right, so provide `switchLabel`
  for accessibility. Pair with `rowClickable` to show a pointer cursor on the
  row in that mode.

  Simple case, the whole row toggles:
  ```svelte
  <DropdownMenu trigger="Settings">
      <DropdownMenuSwitchItem bind:checked={notificationsEnabled}>
          {__('settings.notifications')}
      </DropdownMenuSwitchItem>
  </DropdownMenu>
  ```

  Row has its own click action; only the switch indicator toggles:
  ```svelte
  <DropdownMenuSwitchItem
      checked={entry.active}
      onCheckedChange={entry.onToggle}
      toggleOnIndicatorOnly
      rowClickable
      switchLabel={__('menu.toggleTool', {name: entry.tool.displayName})}
      onclick={() => openDetail(entry)}>
      {entry.tool.displayName}
  </DropdownMenuSwitchItem>
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import Switch from '../switch/Switch.svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Whether the switch is checked. Supports bind:checked. */
        checked?: boolean;
        /** Called when the checked state changes. */
        onCheckedChange?: (checked: boolean) => void;
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Item label content. */
        children?: Snippet;
        /** Set to false to keep the menu open after selecting this item. Defaults to true. */
        closeOnSelect?: boolean;

        /**
         * When true, only the switch indicator toggles the checked state —
         * clicks/keyboard activation on the rest of the row are passed through
         * instead (e.g. to `onclick`). Use this when the row itself opens a
         * detail view or performs another action independent of the toggle.
         */
        toggleOnIndicatorOnly?: boolean;
        /** Accessible label for the switch when `toggleOnIndicatorOnly` is enabled, e.g. `__('menu.toggleTool', {name: tool.displayName})`. */
        switchLabel?: string;
        /** Shows the pointer cursor on the full row when the row itself has a click action (typically set alongside `toggleOnIndicatorOnly`). */
        rowClickable?: boolean;
    }

    let {
        checked = $bindable(false),
        onCheckedChange,
        disabled = false,
        children,
        class: className,
        closeOnSelect = true,
        toggleOnIndicatorOnly = false,
        switchLabel = __('ui.dropdownMenu.switchItem.toggleLabel'),
        rowClickable = false,
        ...restProps
    }: Props = $props();

    function rowProps(props: Record<string, unknown>): Record<string, unknown> {
        if (!toggleOnIndicatorOnly) {
            return props;
        }

        const passiveProps = {...props};
        delete passiveProps.onclick;
        delete passiveProps.onpointerdown;
        delete passiveProps.onpointerup;
        delete passiveProps.onkeydown;

        return passiveProps;
    }

    function toggleFromIndicator(event: MouseEvent) {
        event.preventDefault();
        event.stopPropagation();

        if (disabled) {
            return;
        }

        checked = !checked;
        onCheckedChange?.(checked);
    }
</script>

<DropdownMenuPrimitive.CheckboxItem bind:checked {onCheckedChange} {disabled} {closeOnSelect}>
    {#snippet child({props, checked: isChecked})}
        <div {...mergeProps(
            {
                class: className
            },
            {
                class: [
                    'dropdown-checkbox-item',
                    rowClickable && 'dropdown-checkbox-item--clickable'
                ]
            },
            restProps,
            rowProps(props)
        )}>
            <button
                type="button"
                class="dropdown-item-indicator dropdown-item-indicator--button"
                role="switch"
                aria-label={switchLabel}
                aria-checked={isChecked}
                {disabled}
                onclick={toggleFromIndicator}>
                <Switch checked={isChecked} {disabled}/>
            </button>
            {@render children?.()}
        </div>
    {/snippet}
</DropdownMenuPrimitive.CheckboxItem>

<style>
    .dropdown-checkbox-item {
        position: relative;
        display: flex;
        cursor: default;
        align-items: center;
        border-radius: var(--corner-sm);
        padding-block: var(--space-1_5);
        padding-left: var(--space-2, calc(0.25rem * 2));
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        outline: none;
        user-select: none;
        transition: background-color var(--duration-fast, 150ms);
        padding-right: calc(0.25rem * 12);
    }

    .dropdown-checkbox-item[data-highlighted] {
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

    .dropdown-checkbox-item--clickable,
    .dropdown-checkbox-item--clickable[data-disabled] {
        cursor: pointer;
    }

    .dropdown-item-indicator {
        position: absolute;
        right: var(--space-2, calc(0.25rem * 2));
        display: flex;
        min-height: calc(0.25rem * 3.5);
        min-width: calc(0.25rem * 3.5);
        align-items: center;
        justify-content: center;
        color: var(--color-text);
    }

    .dropdown-item-indicator--button {
        padding: 0;
        border: none;
        background: none;
        cursor: pointer;
    }

    .dropdown-item-indicator--button:disabled {
        cursor: not-allowed;
    }

</style>
