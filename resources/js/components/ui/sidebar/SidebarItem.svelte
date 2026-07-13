<!--
  @component A single nav row. Renders an optional icon (or custom media) and a
  label — with neither, the row is label-only — and
  registers itself as a MenuListItem so the enclosing SidebarItems can track it with
  its proximity-hover and active highlights. When given `children` (its
  sub-items) it owns its own chevron and toggles the sub-tree inline — the
  layout never injects a trailing icon. In the collapsed rail it shrinks to an
  icon with a tooltip.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {Attachment} from 'svelte/attachments';
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useMenuList} from '$lib/components/ui/menu-list/MenuListContext.svelte.js';
    import MenuListItem from '$lib/components/ui/menu-list/MenuListItem.svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import ChevronRightIcon from '$lib/components/ui/icons/iconset/ChevronRightIcon.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';

    interface Props extends HTMLButtonAttributes {
        /** Hugeicons icon shown before the label. Ignored when `media` is provided.
            Optional: with neither `icon` nor `media` the row is label-only and
            the leading slot is omitted entirely. */
        icon?: IconComponent;
        /** Custom leading visual (e.g. an avatar) rendered in place of the icon. */
        media?: Snippet;
        /** Text label. */
        label: string;
        /** Marks the row as the current selection. */
        active?: boolean;
        /** Indents the row to mark it as a child in the nav tree. */
        indent?: boolean;
        /** Renders a static trailing chevron marking the row as a drill-in
            (navigates to a deeper level). Ignored when the row has `children`. */
        drill?: boolean;
        /** Whether the sub-tree starts expanded (only relevant with `children`). */
        defaultExpanded?: boolean;
        /** Bindable expansion state of the sub-tree; defaults to `defaultExpanded`. */
        expanded?: boolean;
        /** Custom trailing visual (e.g. a settings icon). Ignored when the row
            renders a chevron of its own (`children` or `drill`). */
        trailing?: Snippet;
        /** Sub-items revealed beneath this row. Presence renders a chevron. */
        children?: Snippet;
    }

    let {
        icon: Icon,
        media,
        label,
        active = false,
        indent = false,
        drill = false,
        trailing,
        defaultExpanded = false,
        expanded = $bindable(defaultExpanded),
        children,
        onclick,
        class: className,
        ...rest
    }: Props = $props();

    const sidebar = useSidebar();
    const collapsed = $derived(!sidebar.navOpen);

    // Optional: rows used outside a list container (e.g. a footer) have no
    // sliding highlight behind them, so they paint their own hover surface.
    const standalone = !useMenuList();

    // The rail row still spans the full panel width, so a tooltip anchored to the
    // button would open a row's width away from the icon it describes. Anchor it
    // to the icon instead.
    let iconEl = $state<HTMLElement | null>(null);

    // A row wired up as a popup trigger is marked active while its surface is
    // showing, which is a pressed state — not "this is the current page". Let
    // the trigger's own aria-expanded carry it instead of claiming aria-current.
    const isPopupTrigger = $derived(!!rest['aria-haspopup']);

    const hasChildren = $derived(!!children);
    // Children never render in the rail; the chevron and sub-tree are hidden there.
    const showChildren = $derived(hasChildren && expanded && !collapsed);

    function handleClick(event: MouseEvent & {currentTarget: EventTarget & HTMLButtonElement}) {
        // Expandable rows toggle their sub-tree inline (only when not collapsed)
        // and never navigate, so the off-canvas nav stays open for them.
        if (hasChildren && !collapsed) {
            expanded = !expanded;
        } else if (sidebar.mobile) {
            // Tapping a leaf row navigates; collapse the off-canvas overlay so it
            // doesn't cover the destination.
            sidebar.navOpen = false;
        }
        onclick?.(event);
    }
</script>

{#snippet row(attach: Attachment<HTMLElement>, triggerProps: Record<string, unknown> = {})}
    <button
        type="button"
        {@attach attach}
        aria-current={active && !isPopupTrigger ? 'page' : undefined}
        aria-expanded={hasChildren ? expanded : undefined}
        {...mergeProps(triggerProps, rest, {
            class: ['sidebar-item', className],
            onclick: handleClick
        })}
        class:active
        class:indent
        class:collapsed
        class:drill
        class:standalone
    >
        {#if media || Icon}
            <span class="icon-wrap" bind:this={iconEl}>
                {#if media}
                    {@render media()}
                {:else if Icon}
                    <Icon size={18} strokeWidth={2} aria-hidden="true" />
                {/if}
            </span>
        {/if}
        <span class="label">{label}</span>
        {#if hasChildren}
            <span class="caret" class:open={expanded} aria-hidden="true">
                <ChevronRightIcon size={16} strokeWidth={2} />
            </span>
        {:else if drill}
            <span class="caret" aria-hidden="true">
                <ChevronRightIcon size={16} strokeWidth={2} />
            </span>
        {:else if trailing}
            <span class="caret trailing" aria-hidden="true">
                {@render trailing()}
            </span>
        {/if}
    </button>
{/snippet}

<MenuListItem {active} inset={indent}>
    {#snippet children({attach})}
        {#if collapsed}
            <Tooltip
                tooltip={label}
                side="right"
                sideOffset={24}
                delayDuration={300}
                customAnchor={iconEl}
            >
                {#snippet children({props})}
                    {@render row(attach, props)}
                {/snippet}
            </Tooltip>
        {:else}
            {@render row(attach)}
        {/if}
    {/snippet}
</MenuListItem>

{#if showChildren}
    <div class="subtree" transition:growTransition>
        {@render children?.()}
    </div>
{/if}

<style>
    .sidebar-item {
        position: relative;
        /* Rows paint above the sliding highlight behind them. */
        --sidebar-item-z: 1;
        z-index: var(--sidebar-item-z);
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        width: 100%;
        min-height: var(--nav-row-h);
        padding: 0 var(--space-2_5) 0 var(--nav-item-pad-x);
        border: none;
        background: transparent;
        /* Rows recede at rest and come forward on hover — the same treatment as
           the section switcher's chevron and the header actions, but held part
           way to the full text color: a list of chat titles is content, not
           chrome, so it stays comfortably readable while unselected. */
        color: color-mix(in oklab, var(--color-text) 60%, var(--color-text-muted));
        font-size: var(--font-size-xs);
        cursor: pointer;
        border-radius: var(--corner-sm);
        text-align: left;
        transition: color var(--duration-fast);
    }

    /* Hover/active surfaces are provided by the sliding highlights in List. */
    .sidebar-item:hover {
        color: var(--color-text);
    }

    .sidebar-item.active {
        color: var(--color-active-text);
    }

    /* The --drill-stop-* properties are registered in resources/css/properties.css;
       a gradient can't be interpolated, but registered color properties can. */

    /* Drill-ins lead somewhere new rather than switching the current view, so
       they read as an action — the one row in the sidebar carrying the brand
       gradient, which sets it apart from every translucent nav highlight. */
    .sidebar-item.drill {
        --drill-stop-1: var(--gradient-brand-1);
        --drill-stop-2: var(--gradient-brand-2);
        --drill-stop-3: var(--gradient-brand-3);
        background: linear-gradient(
            135deg,
            var(--drill-stop-1),
            var(--drill-stop-2) 55%,
            var(--drill-stop-3)
        );
        color: var(--color-on-accent-fill);
        transition:
            --drill-stop-1 var(--duration-fast),
            --drill-stop-2 var(--duration-fast),
            --drill-stop-3 var(--duration-fast);
    }

    /* Rows outside a list container have no sliding highlight behind them —
       they paint hover, and their active state, themselves. A standalone row is
       marked active while the surface it opens is showing, so the surface has
       to persist once the pointer moves off the row and onto that menu. */
    .sidebar-item.standalone:not(.drill):hover,
    .sidebar-item.standalone.active:not(.drill) {
        background: var(--color-hover);
    }

    /* Drill rows keep their gradient on hover — the ramp deepens instead of
       being washed out, which is why the plain hover surface skips them. */
    .sidebar-item.drill:hover {
        --drill-stop-1: var(--gradient-brand-hover-1);
        --drill-stop-2: var(--gradient-brand-hover-2);
        --drill-stop-3: var(--gradient-brand-hover-3);
    }

    /* Child rows sit indented under their parent, aligning with the label. */
    .sidebar-item.indent {
        padding-left: calc(var(--nav-item-pad-x) + var(--nav-icon-size) + var(--space-2_5));
    }

    /* Icon-only rail. The padding is deliberately unchanged: the icon keeps the
       exact same offset from the panel edge in both states, so it never drifts
       sideways while the column animates. Label and chevron fade out and are
       clipped by the panel instead of being pulled from the flow. */
    .sidebar-item.collapsed .label,
    .sidebar-item.collapsed .caret {
        opacity: 0;
        pointer-events: none;
        transition: opacity 100ms ease;
    }

    /* Pinned to the icon column rather than shrink-wrapped, so leading visuals
       that are not glyph-sized — an avatar, say — still centre on the same
       column as every icon, in the rail as well as the open panel. */
    .icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--nav-icon-size);
        height: var(--nav-icon-size);
        flex-shrink: 0;
    }

    .label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        /* Out fast, in late — the text is gone well before the rail is narrow
           enough to squeeze it, and only returns once there is room again. */
        transition: opacity 160ms ease 100ms;
    }

    /* Caret on expandable rows; rotates down when the sub-tree is open. */
    .caret {
        display: inline-flex;
        flex-shrink: 0;
        color: inherit;
        transition:
            transform 120ms var(--easing-spring),
            color var(--duration-fast),
            opacity 160ms ease 100ms;
    }

    .caret.open {
        transform: rotate(90deg);
    }

    /* A trailing visual recedes at rest and comes forward on hover or while the
       row is active — same treatment as the section switcher's chevron. */
    .caret.trailing {
        color: var(--color-text-muted);
    }

    .sidebar-item:hover .caret.trailing,
    .sidebar-item.active .caret.trailing {
        color: inherit;
    }

    .subtree {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    /* Bump rows up a notch for easier tapping on small screens. */
    @media (--bp-md-and-smaller) {
        .sidebar-item {
            font-size: var(--font-size-sm);
        }

        .icon-wrap :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }
</style>
