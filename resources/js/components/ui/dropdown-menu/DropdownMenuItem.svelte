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
      <DropdownMenuItem iconLeft={PrintIcon} onclick={() => handleExport('print')}>
          {__('chat.export.print')}
      </DropdownMenuItem>
      <DropdownMenuItem iconRight={ShortcutIcon} onclick={() => handleExport('pdf')}>
          {__('chat.export.pdf')}
      </DropdownMenuItem>
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
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** When true, the item cannot be interacted with. */
        disabled?: boolean;
        /** Called when the item is selected via the bits-ui `Item` primitive (keyboard Enter/Space or its own click handling). */
        onSelect?: (event: Event) => void;
        /** When false, the menu stays open after selection. @defaultValue true */
        closeOnSelect?: boolean;
        /** Item content. */
        children?: Snippet;
        /** An optional icon before the item's content, e.g. `Settings05Icon`. */
        iconLeft?: IconComponent;
        /** An optional icon after the item's content; pushed to the row's end edge. */
        iconRight?: IconComponent;
        /** Visual style variant. If set to "destructive" without providing `iconLeft`, a trash-can icon is used. */
        variant?: 'default' | 'destructive';
    }

    const {
        disabled = false,
        onSelect,
        closeOnSelect = true,
        children,
        iconLeft,
        iconRight,
        variant = 'default',
        ...restProps
    }: Props = $props();

    const IconLeft = $derived.by(() => {
        if (variant === 'destructive' && !iconLeft) {
            return Delete02Icon;
        }
        if (!iconLeft) return null;
        return iconLeft;
    });

    const IconRight = $derived.by(() => iconRight ?? null);

</script>

<DropdownMenuPrimitive.Item {disabled} {onSelect} {closeOnSelect}>
    {#snippet child({props})}
        <div {...mergeProps({
            class: [
                `dropdown-item`,
                variant === 'destructive' && 'variant--destructive'
            ]
        }, restProps, props)}>
            {#if IconLeft}
                <IconLeft size="14"/>
            {/if}
            {@render children?.()}
            {#if IconRight}
                <IconRight size="14" class="dropdown-item-icon-end"/>
            {/if}
        </div>
    {/snippet}
</DropdownMenuPrimitive.Item>

<style>
    .dropdown-item {
        position: relative;
        display: flex;
        cursor: pointer;
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

    .dropdown-item.variant--destructive[data-highlighted] {
        background-color: color-mix(in oklch, var(--color-error) 10%, transparent);
        color: var(--color-error);
    }

    .dropdown-item[data-disabled] {
        pointer-events: none;
        opacity: 0.5;
    }

    /* `iconRight` rides the row's end edge (shortcut hints, badges). The class
       lives on the icon component's svg, hence the :global descendant. */
    .dropdown-item :global(.dropdown-item-icon-end) {
        margin-inline-start: auto;
    }
</style>
