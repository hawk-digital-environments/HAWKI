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
    import SearchDialog from '$lib/app/components/search/SearchDialog.svelte';
    import SettingsDialog, {type SettingsSection} from '$lib/app/components/settings/SettingsDialog.svelte';
    import AccessibilityIcon from '$lib/components/ui/icons/iconset/AccessibilityIcon.svelte';
    import ExternalLinkIcon from '$lib/components/ui/icons/iconset/ExternalLinkIcon.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import {onMount} from 'svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';
    import {useSidebarSlots} from '$lib/app/ui/useSidebarHooks.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';

    const app = useApp();
    const router = useRouter();
    const sidebar = useSidebar();
    const chatStore = useStore('chat');
    const config = useConfig();
    const {__} = useTranslator();

    const sidebarSlots = useSidebarSlots();
    const slots = $derived(sidebarSlots.entries);
    const headerSlots = $derived(slots.filter(slot => slot.position === 'header' && slot.active));
    const footerSlots = $derived(slots.filter(slot => slot.position === 'footer' && slot.active));
    const actionSlots = $derived(slots.filter(slot => slot.position === 'action' && slot.active));
    const panels = $derived(slots.filter(slot => slot.position === 'panel' && slot.active));

    // Only rendered when an operator configured one (ACCESSIBILITY_STATEMENT_URL).
    const accessibilityStatementUrl = $derived(config.accessibility?.statementUrl ?? null);

    const chatPath = router.getPath('chat.index');
    let searchOpen = $state(false);
    let settingsOpen = $state(false);
    let settingsSection = $state<SettingsSection | null>(null);

    function startNewChat(event: MouseEvent) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        if (sidebar.mobile) sidebar.navOpen = false;
        chatStore.requestNewChat();
        void router.goToRoute('chat.index');
    }

    function openSettings(section: SettingsSection | null = null) {
        settingsSection = section;
        settingsOpen = true;
    }

    onMount(() => app.events.sync.on('settingsRequested', section => openSettings(section)));
</script>

<Sidebar label={__('ui.navigation.label')}>
    <MobileNavCollapse />
    <SidebarHeader brandHref={chatPath} onBrandClick={startNewChat} onSearch={() => searchOpen = true}>
        <HawkLogo label={__('ui.navigation.newChat')} />
        {#each headerSlots as slot (slot.id)}
            {@const HeaderExtra = slot.component}
            <HeaderExtra />
        {/each}
    </SidebarHeader>
    <nav class="module-selector" aria-label={__('ui.navigation.mainLabel')}>
        <ModuleSelector />
    </nav>
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
        {#if accessibilityStatementUrl}
            {#snippet externalHint()}
                <ExternalLinkIcon size={14} strokeWidth={2} />
            {/snippet}
            <SidebarItem
                href={accessibilityStatementUrl}
                target="_blank"
                icon={AccessibilityIcon}
                label={__('ui.navigation.accessibilityStatement')}
                trailing={externalHint}
            />
        {/if}
        <ProfileButton onOpenSettings={() => openSettings()}/>
        {#each footerSlots as slot (slot.id)}
            {@const FooterExtra = slot.component}
            <FooterExtra />
        {/each}
    </SidebarFooter>
</Sidebar>

<SearchDialog bind:open={searchOpen} />
<SettingsDialog bind:open={settingsOpen} section={settingsSection}/>

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
