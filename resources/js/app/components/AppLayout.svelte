<!--
  @component App-level layout assembly: the grid shell with the navigation
  sidebar in the `nav` column and the page content in the `main` column. The
  layout mechanics (open/closed state, rail collapse, off-canvas behaviour)
  live in the components under components/ui/sidebar.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import AppSidebar from '$lib/app/components/sidebar/AppSidebar.svelte';
    import SidebarContent from '$lib/components/ui/sidebar/SidebarContent.svelte';
    import SidebarRoot from '$lib/components/ui/sidebar/SidebarRoot.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import Toaster from '$lib/components/ui/toast/Toaster.svelte';
    import AnnouncementDialog from '$lib/app/components/announcements/AnnouncementDialog.svelte';

    interface Props {
        /** Page content, rendered in the layout's main column. */
        children: Snippet;
    }

    const {children}: Props = $props();
    const {__} = useTranslator();
</script>

<SidebarRoot>
    <a class="skip-link" href="#main-content">{__('ui.navigation.skipToContent')}</a>
    <AppSidebar />
    <SidebarContent>
        {@render children()}
    </SidebarContent>

    <Toaster />
    <AnnouncementDialog />
</SidebarRoot>

<style>
    .skip-link {
        position: fixed;
        top: var(--space-2);
        left: var(--space-2);
        /* Above the off-canvas nav drawer (--app-sidebar-z: 20), which it has
           to stay reachable over, but below --layer-overlay so a focused skip
           link can never paint on top of an open dialog. */
        --skip-link-z: 30;
        z-index: var(--skip-link-z);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--corner-sm);
        background: var(--color-interactive);
        color: var(--color-on-interactive);
        font-weight: var(--font-weight-semibold);
        transform: translateY(calc(-100% - var(--space-4)));
        transition: transform var(--duration-fast);
    }

    .skip-link:focus {
        transform: translateY(0);
    }
</style>
