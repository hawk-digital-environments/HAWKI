<!--
  @component A radio item inside a DropdownMenuRadioGroup. Selecting it deselects all siblings.
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The value this item represents. Must be unique within its DropdownMenuRadioGroup. */
        value: string;
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Item label content. */
        children?: Snippet;
    }

    const {
        value,
        disabled = false,
        children,
        class: className,
        ...restProps
    }: Props = $props();
</script>

<DropdownMenuPrimitive.RadioItem {value} {disabled}>
    {#snippet child({props, checked: isChecked})}
        <div {...mergeProps({class: `dropdown-radio-item${className ? ` ${className}` : ''}`}, restProps, props)}>
            <span class="dropdown-item-indicator">
                {#if isChecked}
                    <span class="dropdown-radio-dot"></span>
                {/if}
            </span>
            {@render children?.()}
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

    .dropdown-radio-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: var(--corner-full);
        background-color: currentColor;
    }
</style>
