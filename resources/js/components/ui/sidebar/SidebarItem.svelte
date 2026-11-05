<!--
  @component A single nav row. Renders an optional icon (or custom media) and a
  label — with neither, the row is label-only — and
  registers itself as a MenuListItem so the enclosing SidebarItems can track it with
  its proximity-hover and active highlights. When given `children` (its
  sub-items) it owns its own chevron and toggles the sub-tree inline — the
  layout never injects a trailing icon.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {Attachment} from 'svelte/attachments';
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {slide} from 'svelte/transition';
    import {cubicOut} from 'svelte/easing';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useMenuList} from '$lib/components/ui/menu-list/MenuListContext.svelte.js';
    import MenuListItem from '$lib/components/ui/menu-list/MenuListItem.svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import ChevronRightIcon from '$lib/components/ui/icons/iconset/ChevronRightIcon.svelte';

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

    // Optional: rows used outside a list container (e.g. a footer) have no
    // sliding highlight behind them, so they paint their own hover surface.
    const standalone = !useMenuList();

    // A row wired up as a popup trigger is marked active while its surface is
    // showing, which is a pressed state — not "this is the current page". Let
    // the trigger's own aria-expanded carry it instead of claiming aria-current.
    const isPopupTrigger = $derived(!!rest['aria-haspopup']);

    const hasChildren = $derived(!!children);
    const showChildren = $derived(hasChildren && expanded);

    function handleClick(event: MouseEvent & {currentTarget: EventTarget & HTMLButtonElement}) {
        // Expandable rows toggle their sub-tree inline and never navigate, so the
        // off-canvas nav stays open for them.
        if (hasChildren) {
            expanded = !expanded;
        } else if (sidebar.mobile) {
            // Tapping a leaf row navigates; collapse the off-canvas overlay so it
            // doesn't cover the destination.
            sidebar.navOpen = false;
        }
        onclick?.(event);
    }
</script>

{#snippet row(attach: Attachment<HTMLElement>)}
    <button
        type="button"
        {@attach attach}
        aria-current={active && !isPopupTrigger ? 'page' : undefined}
        aria-expanded={hasChildren ? expanded : undefined}
        {...mergeProps(rest, {
            class: ['sidebar-item', className],
            onclick: handleClick
        })}
        class:active
        class:indent
        class:drill
        class:standalone
    >
        {#if media || Icon}
            <span class="icon-wrap">
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
        {@render row(attach)}
    {/snippet}
</MenuListItem>

{#if showChildren}
    <div class="subtree" transition:slide={{duration: 120, easing: cubicOut}}>
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
        gap: var(--space-2);
        width: 100%;
        min-height: var(--nav-row-h);
        padding-inline: var(--nav-item-pad-x);
        border: none;
        background: transparent;
        color: var(--color-nav-text);
        font-size: var(--nav-font-size);
        cursor: pointer;
        border-radius: var(--corner-sm);
        text-align: left;
        transition: color var(--duration-fast);
    }

    /* Hover/active surfaces are provided by the sliding highlights in List. */
    .sidebar-item:hover {
        color: var(--color-nav-text-hover);
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
        padding-left: calc(var(--nav-item-pad-x) + var(--nav-icon-size) + var(--space-2));
    }

    /* Pinned to the icon column rather than shrink-wrapped, so leading visuals
       that are not glyph-sized — an avatar, say — still centre on the same
       column as every icon. Media wider
       than the column spills symmetrically; --nav-media-size declares the
       media's actual extent (an avatar's diameter, say) so the right-hand
       spill can be handed back and the gap before the label stays even with
       the icon rows. */
    .icon-wrap {
        --wrap-media-size: var(--nav-media-size, var(--nav-icon-size));
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--nav-icon-size);
        height: var(--nav-icon-size);
        margin-right: calc((var(--wrap-media-size) - var(--nav-icon-size)) / 2);
        flex-shrink: 0;
        color: var(--color-nav-icon);
        transition: color var(--duration-fast);
    }

    .label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Caret on expandable rows; rotates down when the sub-tree is open. */
    .caret {
        display: inline-flex;
        flex-shrink: 0;
        color: var(--color-nav-icon);
        transition:
            transform 120ms var(--easing-spring),
            color var(--duration-fast);
    }

    .caret.open {
        transform: rotate(90deg);
    }

    /* Icons recede at rest and come forward on hover or while the row is active
       — same treatment as the section switcher's chevron. Drill rows sit on the
       brand gradient, so their icons always take the row's color. */
    .sidebar-item:hover .icon-wrap,
    .sidebar-item.active .icon-wrap,
    .sidebar-item.drill .icon-wrap,
    .sidebar-item:hover .caret,
    .sidebar-item.active .caret,
    .sidebar-item.drill .caret {
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

        /* Glyph rows grow their svg below while the column stays pinned, so
           the bumped glyph — not the column — is the extent whose spill the
           margin returns. */
        .icon-wrap:has(:global(svg)) {
            --wrap-media-size: max(var(--nav-media-size, var(--nav-icon-size)), var(--space-5));
        }

        .icon-wrap :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }
</style>
