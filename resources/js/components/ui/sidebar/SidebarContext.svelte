<!--
  @component Grid shell for the sidebar layout. Provides the shared
  SidebarState to the subtree and owns the column tracks for the nav sidebar,
  main content and aside, animating them open/closed. Child panels (Sidebar,
  SidebarContent) place themselves into the named grid areas via their own
  roots.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import {setSidebarContext, SidebarState} from '$lib/components/ui/sidebar/SidebarState.svelte.js';

    interface Props {
        /** The layout panels (Sidebar, SidebarContent) to assemble into the grid. */
        children: Snippet;
    }

    const {children}: Props = $props();

    const sidebar = new SidebarState();
    setSidebarContext(sidebar);

    // Breakpoints change `--nav-w` / `--aside-w`, so crossing one while resizing
    // would animate the tracks to their new size — the open/close animation
    // replayed for what is really just a layout swap. Suppress the transition
    // for the duration of the resize and restore it once it settles.
    let resizing = $state(false);

    $effect(() => {
        let timer: ReturnType<typeof setTimeout>;

        const onResize = () => {
            resizing = true;
            clearTimeout(timer);
            timer = setTimeout(() => (resizing = false), 150);
        };

        window.addEventListener('resize', onResize);
        return () => {
            window.removeEventListener('resize', onResize);
            clearTimeout(timer);
        };
    });
</script>

<div
    class="sidebar-layout"
    class:nav-open={sidebar.navOpen}
    class:aside-open={sidebar.asideOpen}
    class:resizing
>
    {@render children()}
</div>

<style>
    /* `--nav-track` / `--aside-track` are registered via `@property` in
       app.css — that registration is what lets them animate here. */
    .sidebar-layout {
        position: relative;
        display: grid;
        height: 100dvh;
        width: 100%;
        /* Off-canvas panels sit outside the viewport; keep them from
           creating scrollable overflow. */
        overflow: hidden;
        /* nav | main(fill) | aside */
        grid-template-columns: var(--nav-track) minmax(0, 1fr) var(--aside-track);
        grid-template-areas: 'nav main aside';
        /* Only the two panel tracks animate. Transitioning `grid-template-columns`
           itself would also interpolate the resolved pixel width of the `1fr`
           main track, so every viewport resize replayed the open/close animation
           as the layout chased the new width. */
        transition:
            --nav-track 280ms var(--easing-spring),
            --aside-track 280ms var(--easing-spring);

        --nav-w: 16rem;
        /* Sized so a collapsed row is square: the panel's content width (the
           rail less its own padding and the right-hand divider) comes out equal
           to the row height, so rows and their highlights read as squares. */
        --rail-w: calc(var(--nav-row-h) + 2 * var(--space-2) + var(--divider-width));
        --aside-w: 20rem;

        /* The rail's icon column. Every sidebar row uses `--nav-item-pad-x` as
           its left padding in BOTH states, so an icon never moves sideways when
           the column animates — and the value is derived so that at rail width
           the icon lands dead centre of the panel's *content* box: the rail,
           less `--space-2` padding on each side and the right-hand divider,
           less the icon, halved. Change the rail width, the icon size or the
           divider and the alignment follows on its own. */
        --nav-icon-size: 18px;
        --nav-item-pad-x: calc(
            (var(--rail-w) - 2 * var(--space-2) - var(--divider-width) - var(--nav-icon-size)) / 2
        );
        /* Actual width of the nav column; shrinks to the icon rail when collapsed. */
        --nav-track: var(--nav-w);
        /* Actual width of the aside column; collapses to 0 when closed. */
        --aside-track: 0rem;

        /* Translucent surface shared by every panel (composer card look). */
        --panel-bg: color-mix(in oklch, var(--color-surface-raised) 60%, transparent);

        background: var(--color-bg);
    }

    .sidebar-layout.resizing {
        transition: none;
    }

    /* Aside open → expand its column; closed leaves it at the 0 default. */
    .sidebar-layout.aside-open {
        --aside-track: var(--aside-w);
    }

    /* Sidebar collapsed → shrink the nav column to the icon rail. */
    .sidebar-layout:not(.nav-open) {
        --nav-track: var(--rail-w);
    }

    /* lg and smaller: aside narrows. */
    @media (--bp-lg-and-smaller) {
        .sidebar-layout {
            --aside-w: 16rem;
        }
    }

    /* md and smaller: nav narrows and the aside leaves the persistent grid
       (it becomes a full-width overlay). */
    @media (--bp-md-and-smaller) {
        .sidebar-layout {
            --nav-w: 13rem;
            grid-template-columns: var(--nav-track) minmax(0, 1fr);
            grid-template-areas: 'nav main';
        }
    }

    /* mobile: the nav also leaves the grid and becomes an off-canvas overlay. */
    @media (--bp-mode-mobile) {
        .sidebar-layout {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas: 'main';
        }
    }
</style>
