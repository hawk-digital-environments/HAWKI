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
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';

    const app = useApp();
    const {__} = useTranslator();
    // The sidebar lives outside the RouterView subtree, so the router context
    // set there is not reachable — the app-level handle is used instead.
    const activeModule = $derived.by(() => app.modules.all.find(module =>
        app.router.isRouteActive(getModuleRouteGroupName(module.plugin.name, module.name))
    ) ?? null);
    const ModuleSidebar = $derived(activeModule?.sidebar?.(app.localization.locale) ?? null);

    const chatPath = app.router.p('/chat');

    // The chat module is a plugin, so its store is only there when it is
    // installed — without it the logo is a plain link to the chat route.
    function startNewChat(event: MouseEvent) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        if (app.stores.has('chat')) {
            app.stores.get('chat').startNew();
        }
        void app.router.goTo(chatPath);
    }
</script>

<Sidebar>
    <SidebarHeader brandHref={chatPath} onBrandClick={startNewChat}>
        <HawkLogo label={__('ui.navigation.newChat')} />
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
        /* Its own group, so it takes the sidebar's group gap like every other
           boundary in the column. */
        margin-bottom: var(--nav-group-gap);
    }
</style>
