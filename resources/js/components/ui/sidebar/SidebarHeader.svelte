<!--
  @component Sidebar header row: a brand/logo slot, an optional search action and
  the collapse toggle. In the rail there is only room for one icon per
  line, so the logo fades out, the toggle rides the panel edge inward and the
  search action glides down onto the icon column beneath it.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import PanelLeftIcon from '$lib/components/ui/icons/iconset/PanelLeftIcon.svelte';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** Brand/logo content, shown only while the nav is open. */
        children?: Snippet;
        /** Renders a search action left of the collapse toggle when provided.
            In the rail it moves below the toggle, onto the icon column. */
        onSearch?: () => void;
    }

    const {children, onSearch}: Props = $props();

    const sidebar = useSidebar();
    const {__} = useTranslator();

    // The header actions are full row-height squares, so a tooltip anchored to
    // the button would open wider of the icon than the nav rows' tooltips do.
    // Anchor to the icon box instead — same anchor the rows use, same gap.
    let searchIconEl = $state<HTMLElement | null>(null);
    let collapseIconEl = $state<HTMLElement | null>(null);
    const open = $derived(sidebar.navOpen);

    function toggleNavigation() {
        const returnFocus = sidebar.mobile.current && sidebar.navOpen;
        sidebar.toggleNav();
        if (returnFocus) {
            setTimeout(() => document.getElementById('mobile-navigation-trigger')?.focus());
        }
    }
</script>

<div class="sidebar-header" class:open>
    {#if children}
        <div class="brand" inert={!open}>{@render children()}</div>
    {/if}
    {#if onSearch}
        <Tooltip
            tooltip={__('ui.navigation.search')}
            side="right"
            sideOffset={open ? 12 : 24}
            delayDuration={300}
            customAnchor={searchIconEl}
        >
            {#snippet children({props})}
                <button
                    type="button"
                    {...props}
                    class="header-action search"
                    aria-label={__('ui.navigation.search')}
                    onclick={onSearch}
                >
                    <span class="icon-wrap" bind:this={searchIconEl}>
                        <Search01Icon size={18} strokeWidth={2} aria-hidden="true" />
                    </span>
                </button>
            {/snippet}
        </Tooltip>
    {/if}
    <Tooltip
        tooltip={open ? __('ui.navigation.collapse') : __('ui.navigation.expand')}
        side="right"
        sideOffset={open ? 12 : 24}
        delayDuration={300}
        customAnchor={collapseIconEl}
    >
        {#snippet children({props})}
            <button
                type="button"
                {...props}
                id="app-navigation-toggle"
                class="header-action collapse"
                aria-label={sidebar.mobile.current
                    ? __('ui.navigation.close')
                    : open
                        ? __('ui.navigation.collapse')
                        : __('ui.navigation.expand')}
                aria-expanded={open}
                aria-controls="app-navigation"
                onclick={toggleNavigation}
            >
                <span class="icon-wrap" bind:this={collapseIconEl}>
                    <PanelLeftIcon size={18} strokeWidth={2} aria-hidden="true" />
                </span>
            </button>
        {/snippet}
    </Tooltip>
</div>

<style>
    /* Brand + collapse toggle row. Horizontal padding 0 so the logo and toggle
       sit flush with the item rows below. */
    .sidebar-header {
        /* The nav rows' height: at rail width a row is square, so matching it
           gives the header actions the exact same square as every other item —
           same hit area, same hover surface, same column. */
        --collapse-size: var(--nav-row-h);
        /* Named because the absolutely-positioned brand has to inset itself by
           the same amounts — see below. */
        --header-pad-top: var(--space-1);
        /* `--space-3` is the sidebar's group gap: the same separation the
           footer leaves between its action and the profile, so the whole
           column is one rhythm. */
        --header-pad-bottom: var(--space-3);
        /* Horizontal room the actions occupy, for the brand's max-width. */
        --actions-w: var(--collapse-size);
        /* How far a right-aligned action has to slide to land on the rail's icon
           column: the difference between where the rows put their icon centre and
           where the panel's right edge leaves that button's centre. */
        --rail-nudge: calc(
            (var(--space-2) + var(--nav-item-pad-x) + var(--nav-icon-size) / 2) -
                (var(--rail-w) - var(--divider-width) - var(--space-2) - var(--collapse-size) / 2)
        );

        position: relative;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        /* The toggle is the only item in the flow, pinned to the right, so it
           rides the panel's edge inward as the column animates — no
           mid-transition re-anchoring. */
        justify-content: flex-end;
        padding: var(--header-pad-top) 0 var(--header-pad-bottom);
        /* The rail deepens the row to make room for the stacked search action;
           animate it so the items below glide down with it. */
        transition: padding-bottom 280ms var(--easing-spring);
    }

    .sidebar-header:has(.search) {
        --actions-w: calc(2 * var(--collapse-size) + var(--space-1));
    }

    /* In the rail the search action drops onto a second line, so the row has to
       reserve that line's height itself — it is absolutely positioned and would
       otherwise overlap the first nav item. */
    .sidebar-header:has(.search):not(.open) {
        --header-pad-bottom: calc(var(--space-3) + var(--collapse-size) + var(--space-1));
    }

    /* The brand is positioned out of the flex line on purpose. In the flow it
       shrinks to zero width at rail size but the row's `gap` survives it, and
       the overflowing line then pins the toggle to the LEFT instead of the right
       — putting it several pixels off the icon column. Absolute keeps the
       toggle's geometry a pure function of the panel edge. */
    .brand {
        position: absolute;
        left: 0;
        /* Match the toggle's box exactly rather than insetting to the row's
           content box: absolute offsets resolve against the padding box, so
           `top/bottom: 0` would centre the logo lower than the toggle — and the
           bottom padding grows in the rail, which would drag the logo down with
           it mid-collapse. */
        top: var(--header-pad-top);
        height: var(--collapse-size);
        display: inline-flex;
        align-items: center;
        /* Stop short of the actions so a long logo never runs under them. */
        max-width: calc(100% - var(--actions-w) - var(--space-2));
        overflow: hidden;
        transition: opacity 160ms ease 100ms;
    }

    .sidebar-header:not(.open) .brand {
        opacity: 0;
        transition: opacity 100ms ease;
    }

    .header-action {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--collapse-size);
        height: var(--collapse-size);
        padding: 0;
        border: none;
        background: transparent;
        color: var(--color-text);
        opacity: 0.85;
        cursor: pointer;
        border-radius: var(--corner-sm);
        transition:
            opacity var(--duration-fast),
            background var(--duration-fast),
            transform 280ms var(--easing-spring);
    }

    /* The toggle rides the panel's right edge inward as the column animates, but
       being right-aligned it settles a couple of pixels off the rail's icon
       column. Nudge it onto that column. Transform, not margin, so it glides
       with the collapse instead of re-laying-out. */
    .sidebar-header:not(.open) .collapse {
        transform: translateX(var(--rail-nudge));
    }

    /* Absolute like the brand, and for the same reason: the toggle must stay the
       only item in the flex line, or at rail width the line overflows and the
       toggle is pinned left instead of riding the panel's right edge. Its height
       matches the toggle's, so insetting the top alone lines the two up. */
    .header-action.search {
        position: absolute;
        right: calc(var(--collapse-size) + var(--space-1));
        top: var(--header-pad-top);
    }

    /* No room beside the toggle in the rail, so it moves under it: right by its
       own offset from the toggle to sit on the same column, then down by one
       button plus the gap. Same spring as the toggle's nudge, so the pair reads
       as one movement. */
    .sidebar-header:not(.open) .header-action.search {
        transform: translate(
            calc(var(--rail-nudge) + var(--collapse-size) + var(--space-1)),
            calc(var(--collapse-size) + var(--space-1))
        );
    }

    /* Shrink-wraps the icon so it can serve as the tooltip's anchor — matching
       the nav rows' `.icon-wrap`. */
    .icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .header-action:hover {
        opacity: 1;
        background: var(--color-hover);
    }

    /* Bump the chrome up a notch for easier tapping on small screens. The box
       needs no override — `--nav-row-h` grows on mobile and takes the actions
       (and the brand's max-width, which reads the same var) with it. */
    @media (--bp-md-and-smaller) {
        .header-action :global(svg) {
            width: 20px;
            height: 20px;
        }
    }
</style>
