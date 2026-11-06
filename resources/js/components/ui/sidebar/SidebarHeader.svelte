<!--
  @component Sidebar header row: a brand/logo slot on the left, optional actions
  (e.g. a search button) pushed to the right. On desktop the nav's toggle lives outside
  it, in `SidebarContent`; only the mobile drawer, which covers that toggle, gets a
  close button of its own here.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import SidebarIconButton from '$lib/components/ui/sidebar/SidebarIconButton.svelte';
    import PanelLeftIcon from '$lib/components/ui/icons/iconset/PanelLeftIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Brand/logo content. */
        children?: Snippet;
        /** Turns the brand box itself into a link to this target. */
        brandHref?: string;
        /** Click handler for the brand link, e.g. to route client-side. */
        onBrandClick?: (event: MouseEvent) => void;
        /** Controls placed between the brand and the toggle, on the row's right side. */
        actions?: Snippet;
    }

    const {children: brand, brandHref, onBrandClick, actions, class: className, ...restProps}: Props = $props();

    const sidebar = useSidebar();
    const {__} = useTranslator();

    function closeNavigation() {
        sidebar.toggleNav();
        setTimeout(() => document.getElementById('mobile-navigation-trigger')?.focus());
    }
</script>

<div {...restProps} class={["sidebar-header", className]}>
    {#if brand}
        {#if brandHref}
            <a class="brand" href={brandHref} onclick={onBrandClick}>{@render brand()}</a>
        {:else}
            <div class="brand">{@render brand()}</div>
        {/if}
    {/if}
    {#if actions}
        <div class="actions">{@render actions()}</div>
    {/if}
    <div class="toggle">
        <SidebarIconButton
            id="app-navigation-toggle"
            icon={PanelLeftIcon}
            label={__('ui.navigation.close')}
            aria-expanded={sidebar.navOpen}
            aria-controls="app-navigation"
            onclick={closeNavigation}
        />
    </div>
</div>

<style>
    /* The panel's own padding sets the outer inset, so only the gap to the first
       row below is added here, the same size as that padding. */
    .sidebar-header {
        position: relative;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding-bottom: var(--space-4);
    }

    /* The wordmark's stem has no side bearing, so it is inset by the same extra
       amount as the text-only history rows below (ChatSidebar) to start on their
       line. The auto margin pushes everything after it to the row's right side. */
    .brand {
        flex-shrink: 0;
        margin-right: auto;
        display: inline-flex;
        align-items: center;
        height: var(--nav-row-h);
        padding-inline: var(--nav-text-inset) var(--nav-item-pad-x);
        color: inherit;
        text-decoration: none;
        border-radius: var(--corner-sm);
    }

    .brand :global(.mark) {
        color: var(--color-logo);
        transition: filter var(--duration-fast);
    }

    a.brand:hover :global(.mark) {
        filter: brightness(1.6);
    }

    /* The dark-mode logo is already near white, so hover can only go darker. */
    :global(html.darkMode) a.brand:hover :global(.mark) {
        filter: brightness(0.7);
    }

    .actions {
        display: flex;
        min-width: 0;
    }

    /* The mobile drawer covers the toggle outside the nav, so only there does it get
       one of its own. */
    .toggle {
        display: none;
    }

    @media (--bp-md-and-smaller) {
        .toggle {
            display: flex;
        }
    }
</style>
