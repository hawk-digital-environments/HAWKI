<!--
  @component The space switcher as a bar along the bottom of the screen on
  mobile, where the sidebar is an off-canvas drawer: switching spaces there
  takes one tap instead of opening the drawer first. On larger screens the
  tabs stay in the sidebar (see AppSidebar), so only one of the two is ever
  mounted and the Cmd+1…9 hotkeys register once.
-->
<script lang="ts">
    import SpaceTabs from '$lib/app/components/sidebar/SpaceTabs.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const sidebar = useSidebar();
    const {__} = useTranslator();
</script>

{#if sidebar.mobile}
    <nav class="mobile-space-bar u-print-hidden" aria-label={__('ui.navigation.spaces')}>
        <SpaceTabs />
    </nav>
{/if}

<style>
    .mobile-space-bar {
        grid-area: bottom;
        padding: var(--space-3);
        background: var(--panel-bg);
    }
</style>
