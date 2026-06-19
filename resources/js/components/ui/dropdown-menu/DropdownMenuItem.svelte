<!--
  @component A single selectable item inside a DropdownMenu.
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Called when the item is selected. */
        onSelect?: (event: Event) => void;
        /** When false, the menu stays open after selection. @defaultValue true */
        closeOnSelect?: boolean;
        /** Item content. */
        children?: Snippet;
    }

    const {
        disabled = false,
        onSelect,
        closeOnSelect = true,
        children,
        class: className,
        ...restProps
    }: Props = $props();
</script>

<DropdownMenuPrimitive.Item {disabled} {onSelect} {closeOnSelect}>
    {#snippet child({props})}
        <div {...mergeProps({class: `dropdown-item${className ? ` ${className}` : ''}`}, restProps, props)}>
            {@render children?.()}
        </div>
    {/snippet}
</DropdownMenuPrimitive.Item>

<style>
    .dropdown-item {
        position: relative;
        display: flex;
        cursor: default;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        border-radius: var(--corner-sm);
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-block: var(--space-1_5);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        outline: none;
        user-select: none;
        transition: background-color var(--duration-fast, 150ms);
    }

    .dropdown-item[data-highlighted] {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .dropdown-item[data-disabled] {
        pointer-events: none;
        opacity: 0.5;
    }
</style>
