<!--
  @component Left navigation panel. Occupies the grid's `nav` area on desktop
  and collapses to an icon rail when closed; on mobile it becomes an off-canvas
  overlay driven by the shared sidebar context.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';

    interface Props {
        /** Sidebar content, e.g. header / navigation items / footer. */
        children: Snippet;
    }

    const {children}: Props = $props();

    const sidebar = useSidebar();
</script>

<nav class="app-sidebar" class:open={sidebar.navOpen} aria-label="Navigation">
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
        padding: var(--space-2);
        border-right: var(--divider);
    }

    @media (--bp-mode-mobile) {
        .app-sidebar {
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: min(18rem, 80vw);
            z-index: 20;
            background: var(--color-surface-raised);
            transform: translateX(-100%);
            visibility: hidden;
            transition:
                transform 280ms var(--easing-spring),
                visibility 280ms;
        }

        .app-sidebar.open {
            transform: translateX(0);
            visibility: visible;
        }

        .inner {
            width: 100%;
        }
    }
</style>
