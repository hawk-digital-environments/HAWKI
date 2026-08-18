<!--
  @component Main content area. Fills the grid's `main` column and renders the
  page content next to the sidebar.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import PanelLeftIcon from '$lib/components/ui/icons/iconset/PanelLeftIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';

    interface Props {
        /** Page content. */
        children: Snippet;
    }

    const {children}: Props = $props();
    const sidebar = useSidebar();
    const {__} = useTranslator();

    function openNavigation() {
        if (!sidebar.navOpen) sidebar.toggleNav();
        setTimeout(() => document.getElementById('app-navigation-toggle')?.focus());
    }
</script>

<main
    id="main-content"
    class="content"
    tabindex="-1"
    inert={sidebar.mobile.current && sidebar.navOpen}
>
    <button
        id="mobile-navigation-trigger"
        class="mobile-navigation-trigger"
        type="button"
        aria-label={__('ui.navigation.open')}
        aria-controls="app-navigation"
        aria-expanded={sidebar.navOpen}
        onclick={openNavigation}
    >
        <PanelLeftIcon size={20} strokeWidth={2} aria-hidden="true" />
    </button>
    {@render children()}
</main>

<style>
    .content {
        position: relative;
        grid-area: main;
        min-width: 0;
        overflow: hidden;
        background: var(--panel-bg);
    }

    .mobile-navigation-trigger {
        position: absolute;
        top: var(--space-2_5);
        left: var(--space-3);
        z-index: 10;
        display: none;
        /* Only ever shown at md-and-smaller, where the nav row token is the
           same 2.5rem square this button used to hardcode. */
        width: var(--nav-row-h);
        height: var(--nav-row-h);
        padding: 0;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-text);
        cursor: pointer;
    }

    .mobile-navigation-trigger:hover {
        background: var(--color-hover);
    }

    @media (--bp-md-and-smaller) {
        .mobile-navigation-trigger {
            display: inline-flex;
        }
    }
</style>
