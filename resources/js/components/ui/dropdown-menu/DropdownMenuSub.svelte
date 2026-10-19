<!--
  @component A nested submenu inside a `DropdownMenu`: a trigger row
  (`label · value ›`) that opens a floating panel with more menu items to the
  side. Wraps bits-ui's `Sub`/`SubTrigger`/`SubContent` and takes care of the
  hover/keyboard behaviour: the panel opens after a short pointer delay
  (`openDelay`), stays open while the pointer travels into it, closes when the
  pointer leaves both, and can be opened with →/Enter/Space and closed with ←.
  The panel is portaled so the parent menu's scroll container does not clip it.
  Below `bpMd` (where the parent menu renders as a `BottomSheet`) the panel
  opens below the row at the row's full width instead of to the side.

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
    import {useBreakpoint} from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import {noHoverFocusProps} from '$lib/components/ui/dropdown-menu/noHoverFocus.js';
    import MenuList from '$lib/components/ui/menu-list/MenuList.svelte';
    import {useMenuListRow} from '$lib/components/ui/menu-list/MenuListContext.svelte.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    interface Props extends Omit<HTMLAttributes<HTMLDivElement>, 'children'> {
        /** Whether the submenu is open. Supports bind:open. */
        open?: boolean;
        /** Label of the trigger row. */
        label: Snippet | string;
        /** An optional icon before the trigger row's label, aligned like `DropdownMenuItem`'s `iconLeft`. */
        iconLeft?: IconComponent;
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
        iconLeft: IconLeft,
        value = null,
        disabled = false,
        openDelay = 100,
        children,
        contentProps,
        class: className,
        ...restProps
    }: Props = $props();

    const breakpoint = useBreakpoint();

    // The trigger joins the parent menu's sliding hover (see DropdownMenuItem); the
    // submenu panel gets a list of its own.
    const listRow = useMenuListRow();
    const magnetic = $derived(!!listRow && !disabled);

    // Gap between the trigger row and the parent panel's outer edge (its padding + border),
    // measured on mount and again on each open: the flyout clears that edge instead of overlapping the parent panel,
    // whatever padding the parent menu uses.
    let triggerEl = $state<HTMLElement | null>(null);
    let parentInset = $state(0);
    $effect(() => {
        void open;
        if (!triggerEl) return;
        const panel = triggerEl.closest('.dropdown-content');
        if (panel) parentInset = panel.getBoundingClientRect().right - triggerEl.getBoundingClientRect().right;
    });

    const fullContentProps = $derived.by(() => mergeProps(
        breakpoint.is('bpSmallerThanMd')
            ? {side: 'bottom', align: 'end', sideOffset: 4}
            // Sit flush against the parent panel, and pull the flyout up by its own
            // padding + border so its first row lines up with the trigger row.
            : {sideOffset: parentInset, alignOffset: -5},
        {
            class: 'dropdown-content dropdown-content--dropdown dropdown-sub-content'
        },
        noHoverFocusProps,
        contentProps ?? {}
    ) as DropdownMenuSubContentProps);
</script>

<DropdownMenuPrimitive.Sub bind:open>
    <DropdownMenuPrimitive.SubTrigger {disabled} {openDelay}>
        {#snippet child({props})}
            <div bind:this={triggerEl} {@attach magnetic ? listRow : null} {...mergeProps({class: [`dropdown-sub-trigger${className ? ` ${className}` : ''}`, magnetic && 'magnetic']}, restProps, props)}>
                {#if IconLeft}
                    <IconLeft size="14" aria-hidden="true"/>
                {/if}
                <span class="dropdown-sub-trigger-label">
                    <SnippetOrString value={label}/>
                </span>
                {#if value}
                    <span class="dropdown-sub-trigger-value">
                        <SnippetOrString value={value}/>
                    </span>
                {/if}
                <ArrowRight01Icon size={14} class="dropdown-sub-trigger-chevron" aria-hidden="true"/>
            </div>
        {/snippet}
    </DropdownMenuPrimitive.SubTrigger>
    <DropdownMenuPrimitive.Portal>
        <DropdownMenuPrimitive.SubContent {...fullContentProps as any}>
            <MenuList class="dropdown-menu-list" bridgeSections>
                {@render children?.()}
            </MenuList>
        </DropdownMenuPrimitive.SubContent>
    </DropdownMenuPrimitive.Portal>
</DropdownMenuPrimitive.Sub>

<style>
    .dropdown-sub-trigger {
        position: relative;
        display: flex;
        cursor: pointer;
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

    .dropdown-sub-trigger:is([data-highlighted]:not(.magnetic), [data-highlighted]:focus-visible, :hover:not([data-disabled], .magnetic)),
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

    /* Grow out of the trigger row and drift in from it, instead of the root menu's centred scale. */
    :global(.dropdown-content.dropdown-sub-content[data-state="open"]) {
        transform-origin: var(--bits-dropdown-menu-content-transform-origin);
        animation: dropdown-sub-in var(--duration-extra-fast) var(--easing-spring);
    }

    :global(.dropdown-content.dropdown-sub-content[data-state="closed"]) {
        transform-origin: var(--bits-dropdown-menu-content-transform-origin);
        animation: dropdown-sub-out var(--duration-extra-fast) var(--easing-in);
    }

    :global(.dropdown-sub-content[data-side="right"]) { --dropdown-sub-shift: -4px 0; }
    :global(.dropdown-sub-content[data-side="left"]) { --dropdown-sub-shift: 4px 0; }
    :global(.dropdown-sub-content[data-side="bottom"]) { --dropdown-sub-shift: 0 -4px; }
    :global(.dropdown-sub-content[data-side="top"]) { --dropdown-sub-shift: 0 4px; }

    @keyframes -global-dropdown-sub-in {
        from {
            opacity: 0;
            scale: 0.96;
            translate: var(--dropdown-sub-shift, 0 0);
        }
    }

    @keyframes -global-dropdown-sub-out {
        to {
            opacity: 0;
            scale: 0.98;
            translate: var(--dropdown-sub-shift, 0 0);
        }
    }

    @media (--bp-smaller-than-md) {
        :global(.dropdown-sub-content) {
            width: var(--bits-floating-anchor-width);
        }
    }
</style>
