<!--
  @component Left navigation panel. Occupies the grid's `nav` area on desktop
  and collapses to an icon rail when closed; on mobile it becomes an off-canvas
  overlay driven by the shared sidebar context.
-->
<script lang="ts">
    import {tick, type Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';

    interface Props extends HTMLAttributes<HTMLElement> {
        /** Sidebar content, e.g. header / navigation items / footer. */
        children: Snippet;
        /** Translated accessible name for the navigation landmark. */
        label: string;
    }

    const {children, label, class: className, ...rest}: Props = $props();

    const sidebar = useSidebar();

    async function handleKeyDown(event: KeyboardEvent) {
        if (event.key !== 'Escape' || !sidebar.mobile || !sidebar.navOpen) return;
        event.preventDefault();
        sidebar.toggleNav();
        await tick();
        document.getElementById('mobile-navigation-trigger')?.focus();
    }
</script>

<svelte:window onkeydown={handleKeyDown} />

<nav
    {...mergeProps(rest, {
        id: 'app-navigation',
        class: ['app-sidebar', 'u-print-hidden', sidebar.navOpen && 'open', className],
        'aria-label': label
    })}
>
    <div class="inner">
        {@render children()}
    </div>
</nav>

<style>
    .app-sidebar {
        grid-area: nav;
        overflow: hidden;
        background: var(--panel-bg);
    }

    .inner {
        display: flex;
        flex-direction: column;
        width: var(--nav-track);
        height: 100%;
        /* A little more breathing room at the bottom edge than on the sides, so
           the footer row doesn't sit on the edge. At the top the header brings
           its own inset, so the padding here is trimmed to leave the same gap
           above the header as the header leaves below itself. */
        padding: calc(var(--nav-group-gap) - var(--space-1)) var(--space-2) var(--space-3);
        border-right: var(--divider);
    }

    @media (--bp-md-and-smaller) {
        .app-sidebar {
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 100%;
            /* Off-canvas drawer: above the page content it slides over. */
            --app-sidebar-z: 20;
            z-index: var(--app-sidebar-z);
            background: var(--color-surface-raised);
            transform: translateX(-100%);
            visibility: hidden;
            transition:
                transform 280ms var(--easing-spring),
                visibility 0s linear 280ms;
        }

        .app-sidebar.open {
            transform: translateX(0);
            visibility: visible;
            transition-delay: 0s;
        }

        .inner {
            width: 100%;
        }
    }
</style>
