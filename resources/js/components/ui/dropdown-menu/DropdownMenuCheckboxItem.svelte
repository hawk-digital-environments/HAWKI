<!--
  @component A menu item with a checkbox indicator. Use bind:checked for two-way state.
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import Tick02Icon from '../icons/iconset/Tick02Icon.svelte';

    interface Props extends Omit<HTMLAttributes<HTMLDivElement>, 'children'> {
        /** Whether the checkbox is checked. Supports bind:checked. */
        checked?: boolean;
        /** Called when the checked state changes. */
        onCheckedChange?: (checked: boolean) => void;
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Item label content. Receives the current checked state. */
        children?: Snippet<[boolean]>;
        /** Set to false to keep the menu open after selecting this item. Defaults to true. */
        closeOnSelect?: boolean;
        /** Bindable reference to the rendered item element. */
        ref?: HTMLDivElement | null;
    }

    let {
        checked = $bindable(false),
        onCheckedChange,
        disabled = false,
        children,
        class: className,
        closeOnSelect = true,
        ref = $bindable(null),
        ...restProps
    }: Props = $props();
</script>

<DropdownMenuPrimitive.CheckboxItem bind:checked {onCheckedChange} {disabled} {closeOnSelect}>
    {#snippet child({props, checked: isChecked})}
        <div bind:this={ref} {...mergeProps({class: `dropdown-checkbox-item${className ? ` ${className}` : ''}`}, restProps, props)}>
            <span class="dropdown-item-indicator">
                {#if isChecked}
                    <Tick02Icon size={12}/>
                {/if}
            </span>
            {@render children?.(isChecked)}
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
        padding-right: var(--space-8, calc(0.25rem * 8));
        padding-left: var(--space-2, calc(0.25rem * 2));
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        outline: none;
        user-select: none;
        transition: background-color var(--duration-fast, 150ms);
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

    .dropdown-item-indicator {
        position: absolute;
        right: var(--space-2, calc(0.25rem * 2));
        display: flex;
        height: calc(0.25rem * 3.5);
        width: calc(0.25rem * 3.5);
        align-items: center;
        justify-content: center;
        color: var(--color-text);
    }
</style>
