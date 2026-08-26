<!--
  @component A nested submenu inside a `DropdownMenu`: a trigger row
  (`label · value ›`) that opens a floating panel with more menu items to the
  side. Wraps bits-ui's `Sub`/`SubTrigger`/`SubContent` and takes care of the
  hover/keyboard behaviour: the panel opens after a short pointer delay
  (`openDelay`), stays open while the pointer travels into it, closes when the
  pointer leaves both, and can be opened with →/Enter/Space and closed with ←.
  The panel is portaled so the parent menu's scroll container does not clip it.

  Compose the panel from other family members, typically
  `DropdownMenuRadioGroup` + `DropdownMenuRadioItem` for a single choice:

  ```svelte
  <DropdownMenu trigger="Settings">
      <DropdownMenuSub label={__('chat.effort')} value={currentLabel}>
          <DropdownMenuRadioGroup value={effort} onValueChange={setEffort}>
              <DropdownMenuRadioItem value="low" indicator="check">{__('chat.low')}</DropdownMenuRadioItem>
              <DropdownMenuRadioItem value="high" indicator="check">{__('chat.high')}</DropdownMenuRadioItem>
          </DropdownMenuRadioGroup>
      </DropdownMenuSub>
  </DropdownMenu>
  ```
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, type DropdownMenuSubContentProps, mergeProps} from 'bits-ui';
    import type {HTMLAttributes} from 'svelte/elements';
    import type {Snippet} from 'svelte';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';

    interface Props extends Omit<HTMLAttributes<HTMLDivElement>, 'children'> {
        /** Whether the submenu is open. Supports bind:open. */
        open?: boolean;
        /** Label of the trigger row. */
        label: Snippet | string;
        /** Optional summary of the current selection, shown muted on the right of the row. */
        value?: Snippet | string | null;
        /** When true, the row cannot be interacted with and the submenu cannot open. */
        disabled?: boolean;
        /** Delay in ms between the pointer entering the row and the submenu opening. @defaultValue 100 */
        openDelay?: number;
        /** Submenu items. */
        children?: Snippet;
        /** Additional props forwarded to the `SubContent` element. */
        contentProps?: Omit<DropdownMenuSubContentProps, 'children'>;
    }

    let {
        open = $bindable(false),
        label,
        value = null,
        disabled = false,
        openDelay = 100,
        children,
        contentProps,
        class: className,
        ...restProps
    }: Props = $props();

    const fullContentProps = $derived.by(() => mergeProps(
        {
            sideOffset: 8,
            class: 'dropdown-content dropdown-content--dropdown dropdown-sub-content'
        },
        contentProps ?? {}
    ) as DropdownMenuSubContentProps);
</script>

<DropdownMenuPrimitive.Sub bind:open>
    <DropdownMenuPrimitive.SubTrigger {disabled} {openDelay}>
        {#snippet child({props})}
            <div {...mergeProps({class: `dropdown-sub-trigger${className ? ` ${className}` : ''}`}, restProps, props)}>
                <span class="dropdown-sub-trigger-label">
                    <SnippetOrString value={label}/>
                </span>
                {#if value}
                    <span class="dropdown-sub-trigger-value">
                        <SnippetOrString value={value}/>
                    </span>
                {/if}
                <ArrowRight01Icon size={14} class="dropdown-sub-trigger-chevron"/>
            </div>
        {/snippet}
    </DropdownMenuPrimitive.SubTrigger>
    <DropdownMenuPrimitive.Portal>
        <DropdownMenuPrimitive.SubContent {...fullContentProps as any}>
            {@render children?.()}
        </DropdownMenuPrimitive.SubContent>
    </DropdownMenuPrimitive.Portal>
</DropdownMenuPrimitive.Sub>

<style>
    .dropdown-sub-trigger {
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
        color: var(--color-text);
        outline: none;
        user-select: none;
        transition: background-color var(--duration-fast, 150ms);
    }

    .dropdown-sub-trigger[data-highlighted],
    .dropdown-sub-trigger[data-state="open"] {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .dropdown-sub-trigger[data-disabled] {
        pointer-events: none;
        opacity: 0.5;
    }

    .dropdown-sub-trigger-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dropdown-sub-trigger-value,
    :global(.dropdown-sub-trigger-chevron) {
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    :global(.dropdown-sub-content) {
        min-width: calc(0.25rem * 40);
    }
</style>
