<!--
  @component A single selectable item inside a DropdownMenu.
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Component, Snippet} from 'svelte';
    import {type LucideProps, Trash2} from '@lucide/svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Called when the item is selected. */
        onSelect?: (event: Event) => void;
        /** When false, the menu stays open after selection. @defaultValue true */
        closeOnSelect?: boolean;
        /** Item content. */
        children?: Snippet;
        /** An optional icon to display alongside the item. */
        icon?: Component<LucideProps>;
        /** Visual style variant. */
        variant?: 'default' | 'destructive';
    }

    const {
        disabled = false,
        onSelect,
        closeOnSelect = true,
        children,
        icon,
        variant = 'default',
        ...restProps
    }: Props = $props();

    const IconComponent = $derived.by(() => {
        if (variant === 'destructive' && !icon) {
            return Trash2;
        }
        if (!icon) return null;
        return icon;
    });

</script>

<DropdownMenuPrimitive.Item {disabled} {onSelect} {closeOnSelect}>
    {#snippet child({props})}
        <div {...mergeProps({
            class: [
                `dropdown-item`,
                variant === 'destructive' && 'variant--destructive'
            ]
        }, restProps, props)}>
            {#if IconComponent}
                <IconComponent size="14"/>
            {/if}
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
        gap: var(--space-2, calc(0.25rem * 1.5));
        border-radius: var(--corner-sm);
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-block: var(--space-1_5);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        outline: none;
        user-select: none;
        transition: background-color var(--duration-fast, 150ms);

        &.variant--destructive {
            color: var(--color-error);
        }
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
