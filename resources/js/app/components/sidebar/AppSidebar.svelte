<!--
  @component App navigation sidebar. Composes the generic sidebar components
  from components/ui/sidebar with HAWKI's brand and navigation entries. The
  module-sidebar area and the header/footer chrome are extendible: plugins
  contribute components via the `sidebarSlots` hook.
-->
<script lang="ts">
    import Sidebar from '$lib/components/ui/sidebar/Sidebar.svelte';
    import SidebarHeader from '$lib/components/ui/sidebar/SidebarHeader.svelte';
    import SidebarFooter from '$lib/components/ui/sidebar/SidebarFooter.svelte';
    import HawkLogo from '$lib/components/ui/logo/HawkLogo.svelte';
    import ModuleSelector from '$lib/app/components/sidebar/ModuleSelector.svelte';
    import ProfileButton from '$lib/app/components/sidebar/ProfileButton.svelte';
    import MobileNavCollapse from '$lib/app/components/sidebar/MobileNavCollapse.svelte';
    import {useSidebarSlots} from '$lib/app/ui/useSidebarHooks.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';

    const router = useRouter();
    const sidebar = useSidebar();
    const chatStore = useStore('chat');
    const {__} = useTranslator();

    const sidebarSlots = useSidebarSlots();
    const slots = $derived(sidebarSlots.entries);
    const headerSlots = $derived(slots.filter(slot => slot.position === 'header' && slot.active));
    const footerSlots = $derived(slots.filter(slot => slot.position === 'footer' && slot.active));
    const actionSlots = $derived(slots.filter(slot => slot.position === 'action' && slot.active));
    const panels = $derived(slots.filter(slot => slot.position === 'panel' && slot.active));

    const chatPath = router.getPath('chat.index');

    function startNewChat(event: MouseEvent) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        if (sidebar.mobile) sidebar.navOpen = false;
        chatStore.startNew();
        void router.goToRoute('chat.index');
    }
</script>

<Sidebar label={__('ui.navigation.label')}>
    <MobileNavCollapse />
    <SidebarHeader brandHref={chatPath} onBrandClick={startNewChat}>
        <HawkLogo label={__('ui.navigation.newChat')} />
        {#each headerSlots as slot (slot.id)}
            {@const HeaderExtra = slot.component}
            <HeaderExtra />
        {/each}
    </SidebarHeader>
    <div class="module-selector">
        <ModuleSelector />
    </div>
    <div class="module-sidebar">
        {#each panels as panel (panel.id)}
            {@const Panel = panel.component}
            <Panel />
        {/each}
    </div>
    <!-- The active module's primary action (e.g. "New Chat"), contributed
         via `sidebarSlots` and pinned directly above the profile footer. -->
    <div class="sidebar-actions">
        {#each actionSlots as slot (slot.id)}
            {@const Action = slot.component}
            <Action />
        {/each}
    </div>
    <SidebarFooter>
        <ProfileButton/>
        {#each footerSlots as slot (slot.id)}
            {@const FooterExtra = slot.component}
            <FooterExtra />
        {/each}
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

    .sidebar-actions {
        /* Pinned to the bottom of the column, directly above the profile
           footer; the module-sidebar area above it takes the free space. */
        margin-top: auto;
    }
</style>
