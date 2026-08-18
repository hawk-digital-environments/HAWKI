<!--
  @component A single selectable item inside a `DropdownMenu` (optionally
  wrapped in a `DropdownMenuGroup`). Fires `onSelect` when activated by
  click, Enter or Space; closes the menu afterwards unless `closeOnSelect`
  is false.

  Note that unlike the other item components, plain click handling also
  works through the regular `onclick` HTML attribute (forwarded via
  `restProps`), as seen in real usages such as
  `plugins/core/modules/chat/components/header/ExportMenu.svelte`.

  ```svelte
  <DropdownMenu trigger="Export">
      <DropdownMenuItem onclick={() => handleExport('pdf')}>
          {__('chat.export.pdf')}
      </DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem variant="destructive" onclick={handleDelete}>
          {__('chat.export.delete')}
      </DropdownMenuItem>
  </DropdownMenu>
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import type {IconComponent} from '../icons/index.js';
    import Delete02Icon from '../icons/iconset/Delete02Icon.svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Called when the item is selected via the bits-ui `Item` primitive (keyboard Enter/Space or its own click handling). */
        onSelect?: (event: Event) => void;
        /** When false, the menu stays open after selection. @defaultValue true */
        closeOnSelect?: boolean;
        /** Item content. */
        children?: Snippet;
        /** An optional icon to display alongside the item, e.g. `Settings05Icon`. */
        icon?: IconComponent;
        /** Visual style variant. If set to "destructive" without providing an icon, the icon is automatically set to a trash can. */
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

    const Icon = $derived.by(() => {
        if (variant === 'destructive' && !icon) {
            return Delete02Icon;
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
            {#if Icon}
                <Icon size="14"/>
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
