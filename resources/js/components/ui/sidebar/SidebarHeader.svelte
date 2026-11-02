<!--
  @component Sidebar header row: a brand/logo slot, an optional search action and
  the collapse toggle. In the rail there is only room for one icon per
  line, so a logo link fades out, the toggle rides the panel edge inward and the
  search action glides down onto the icon column beneath it. A non-link brand
  is a control that has to stay reachable, so it glides down below search.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {Attachment} from 'svelte/attachments';
    import type {HTMLAttributes} from 'svelte/elements';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {createProximityHover} from '$lib/utils/proximityHover.svelte.js';
    import PanelLeftIcon from '$lib/components/ui/icons/iconset/PanelLeftIcon.svelte';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Brand/logo content. Hidden in the rail when it is a link (`brandHref`);
            otherwise it moves below the other actions, onto the icon column. */
        children?: Snippet;
        /** Turns the brand box itself into a link to this target. */
        brandHref?: string;
        /** Click handler for the brand link, e.g. to route client-side. */
        onBrandClick?: (event: MouseEvent) => void;
        /** Renders a search action left of the collapse toggle when provided.
            In the rail it moves below the toggle, onto the icon column. */
        onSearch?: () => void;
    }

    const {children, brandHref, onBrandClick, onSearch, class: className, ...restProps}: Props = $props();

    const sidebar = useSidebar();
    const {__} = useTranslator();

    // The header actions are full row-height squares, so a tooltip anchored to
    // the button would open wider of the icon than the nav rows' tooltips do.
    // Anchor to the icon box instead — same anchor the rows use, same gap.
    let searchIconEl = $state<HTMLElement | null>(null);
    let collapseIconEl = $state<HTMLElement | null>(null);
    const open = $derived(sidebar.navOpen);

    function toggleNavigation() {
        const returnFocus = sidebar.mobile && sidebar.navOpen;
        sidebar.toggleNav();
        if (returnFocus) {
            setTimeout(() => document.getElementById('mobile-navigation-trigger')?.focus());
        }
    }

    // ── Magnetic hover ───────────────────────────────────────────────────
    // One highlight glides between the brand and the actions: along the row while
    // the nav is open, down the stack in the rail.

    /** Keeps the highlight on while the pointer crosses the gap between two items. */
    const GAP_SLACK = 4;

    const hover = createProximityHover({axis: () => (open ? 'x' : 'y')});
    const hoverRect = $derived(hover.activeIndex !== null ? hover.itemRects[hover.activeIndex] : null);

    const attachHover: Attachment<HTMLElement> = (header) => {
        hover.setContainer(header);
        let items: HTMLElement[] = [];

        function syncItems() {
            items = Array.from(header.querySelectorAll<HTMLElement>('.brand:not([inert]), .header-action'));
            items.forEach((item, index) => hover.registerItem(index, item));
        }

        function nearItem(event: MouseEvent) {
            return items.some(item => {
                const r = item.getBoundingClientRect();
                return event.clientX >= r.left - GAP_SLACK && event.clientX <= r.right + GAP_SLACK
                    && event.clientY >= r.top - GAP_SLACK && event.clientY <= r.bottom + GAP_SLACK;
            });
        }

        const onmouseenter = () => {
            syncItems();
            hover.handlers.onmouseenter();
        };
        const onmousemove = (event: MouseEvent) => {
            syncItems();
            if (nearItem(event)) hover.handlers.onmousemove(event);
            else hover.handlers.onmouseleave();
        };

        header.addEventListener('mouseenter', onmouseenter);
        header.addEventListener('mousemove', onmousemove);
        header.addEventListener('mouseleave', hover.handlers.onmouseleave);
        return () => {
            header.removeEventListener('mouseenter', onmouseenter);
            header.removeEventListener('mousemove', onmousemove);
            header.removeEventListener('mouseleave', hover.handlers.onmouseleave);
            hover.setContainer(null);
        };
    };

    // The items move when the nav collapses, leaving the measured rect behind.
    // The next pointer move brings the highlight back on the item's new position.
    $effect(() => {
        void open;
        hover.handlers.onmouseleave();
    });
</script>

<div {...restProps} class={["sidebar-header", className]} class:open {@attach attachHover}>
    {#if hoverRect}
        <span
            class="hover-bg"
            style:top="{hoverRect.top}px"
            style:left="{hoverRect.left}px"
            style:width="{hoverRect.width}px"
            style:height="{hoverRect.height}px"
        ></span>
    {/if}
    {#if children}
        {#if brandHref}
            <a class="brand" href={brandHref} onclick={onBrandClick} inert={!open}>{@render children()}</a>
        {:else}
            <div class="brand">{@render children()}</div>
        {/if}
    {/if}
    {#if onSearch}
        <Tooltip
            tooltip={__('ui.navigation.search')}
            side={open ? 'bottom' : 'right'}
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
        side={open ? 'bottom' : 'right'}
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
                aria-label={sidebar.mobile
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
        /* Named because the absolutely-positioned brand has to inset itself by
           the same amounts — see below. */
        --header-pad-top: var(--space-1);
        /* A step beyond the sidebar's group gap: a switcher in the brand slot
           changes what the whole list below shows, so it gets clear separation
           from that list. */
        --header-pad-bottom: var(--space-5);
        /* Height of each line that stacks under the toggle in the rail. */
        --search-line: 0px;
        --brand-line: 0px;
        /* Horizontal room the actions occupy, for the brand's max-width. The
           actions are `--nav-row-h` squares: at rail width a row is square, so
           matching the row height gives them the same hit area, hover surface
           and column as every other item. */
        --actions-w: var(--nav-row-h);
        /* How far a right-aligned action has to slide to land on the rail's icon
           column: the difference between where the rows put their icon centre and
           where the panel's right edge leaves that button's centre. */
        --rail-nudge: calc(
            (var(--space-2) + var(--nav-item-pad-x) + var(--nav-icon-size) / 2) -
                (var(--rail-w) - var(--divider-width) - var(--space-2) - var(--nav-row-h) / 2)
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
        --actions-w: calc(2 * var(--nav-row-h) + var(--space-1));
        --search-line: calc(var(--nav-row-h) + var(--space-1));
    }

    .sidebar-header:has(div.brand) {
        --brand-line: calc(var(--nav-row-h) + var(--space-1));
    }

    /* In the rail the search action and a non-link brand drop onto lines of their
       own, so the row has to reserve those lines' height itself — they are
       absolutely positioned and would otherwise overlap the first nav item. */
    .sidebar-header:not(.open) {
        --header-pad-bottom: calc(var(--space-5) + var(--search-line) + var(--brand-line));
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
        height: var(--nav-row-h);
        display: inline-flex;
        align-items: center;
        /* Stop short of the actions so a long logo never runs under them. */
        max-width: calc(100% - var(--actions-w) - var(--space-2));
        /* Shrink-wrap the content so a hover surface hugs it instead of spanning
           the whole free width of the row. */
        width: fit-content;
        color: inherit;
        text-decoration: none;
        border-radius: var(--corner-sm);
        transition:
            opacity 160ms ease 100ms,
            transform 280ms var(--easing-spring);
    }

    /* A brand link is padded exactly as the header actions pad their icon — the
       toggle is a `--nav-row-h` square around a `--nav-icon-size` glyph — so both
       hover surfaces have the same inset and the same height. A non-link brand
       holds a control that brings its own padding and hover. */
    a.brand {
        overflow: hidden;
        padding: 0 calc((var(--nav-row-h) - var(--nav-icon-size)) / 2);
    }

    /* Magnetic highlight behind the brand and the actions, all positioned and
       later in the row. Same travel as the sidebar's list highlight. */
    .hover-bg {
        position: absolute;
        pointer-events: none;
        background: var(--color-hover);
        border-radius: var(--corner-sm);
        transition:
            top 200ms var(--easing-spring),
            left 200ms var(--easing-spring),
            width 200ms var(--easing-spring),
            height 200ms var(--easing-spring);
    }

    .sidebar-header:not(.open) a.brand {
        opacity: 0;
        transition: opacity 100ms ease;
    }

    /* Moves below the actions with the same spring as search. It already sits on
       the icon column, so it only travels down; the panel's width clamps it to
       the row's square. */
    .sidebar-header:not(.open) div.brand {
        max-width: 100%;
        transform: translateY(calc(var(--search-line) + var(--brand-line)));
    }

    .header-action {
        position: relative;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--nav-row-h);
        height: var(--nav-row-h);
        padding: 0;
        border: none;
        background: transparent;
        /* Recedes at rest and comes forward on hover, same as the section
           switcher's chevron. */
        color: var(--nav-icon-color);
        cursor: pointer;
        border-radius: var(--corner-sm);
        transition:
            color var(--duration-fast),
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
        right: calc(var(--nav-row-h) + var(--space-1));
        top: var(--header-pad-top);
    }

    /* No room beside the toggle in the rail, so it moves under it: right by its
       own offset from the toggle to sit on the same column, then down by one
       button plus the gap. Same spring as the toggle's nudge, so the pair reads
       as one movement. */
    .sidebar-header:not(.open) .header-action.search {
        transform: translate(
            calc(var(--rail-nudge) + var(--nav-row-h) + var(--space-1)),
            calc(var(--nav-row-h) + var(--space-1))
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
        color: var(--color-text);
    }

    /* Bump the chrome up a notch for easier tapping on small screens. The box
       needs no override — `--nav-row-h` grows on mobile and takes the actions
       (and the brand's max-width, which reads the same var) with it. */
    @media (--bp-md-and-smaller) {
        .header-action :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }
</style>
