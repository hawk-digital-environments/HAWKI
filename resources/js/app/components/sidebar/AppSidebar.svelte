<!--
  @component App navigation sidebar. Composes the generic sidebar components
  from components/ui/sidebar with HAWKI's brand and navigation entries.

  @todo placeholder entries until the real navigation is wired up.
-->
<script lang="ts">
    import Sidebar from '$lib/components/ui/sidebar/Sidebar.svelte';
    import SidebarHeader from '$lib/components/ui/sidebar/SidebarHeader.svelte';
    import SidebarFooter from '$lib/components/ui/sidebar/SidebarFooter.svelte';
    import HawkLogo from '$lib/components/ui/logo/HawkLogo.svelte';
    import ModuleSelector from '$lib/app/components/sidebar/ModuleSelector.svelte';
    import ProfileButton from '$lib/app/components/sidebar/ProfileButton.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';

    const app = useApp();
    // The sidebar lives outside the RouterView subtree, so the router context
    // set there is not reachable — the app-level handle is used instead.
    const activeModule = $derived.by(() => app.modules.all.find(module =>
        app.router.isRouteActive(getModuleRouteGroupName(module.plugin.name, module.name))
    ) ?? null);
    const ModuleSidebar = $derived(activeModule?.sidebar?.(app.localization.locale) ?? null);
</script>

<Sidebar>
    <SidebarHeader>
        <HawkLogo />
    </SidebarHeader>
    <div class="module-selector">
        <ModuleSelector />
    </div>
    <div class="module-sidebar">
        {#if ModuleSidebar}
            <ModuleSidebar />
        {/if}
    </div>
    <SidebarFooter>
        <ProfileButton/>
    </SidebarFooter>
</Sidebar>

<style>
    .module-sidebar {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
    }

    .module-selector {
        margin-bottom: var(--space-2);
    }
</style>
