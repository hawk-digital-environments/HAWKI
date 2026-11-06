<!--
  @component App navigation sidebar. Composes the generic sidebar components
  from components/ui/sidebar with HAWKI's navigation entries.

  @todo placeholder entries until the real navigation is wired up.
-->
<script lang="ts">
    import Sidebar from '$lib/components/ui/sidebar/Sidebar.svelte';
    import SidebarHeader from '$lib/components/ui/sidebar/SidebarHeader.svelte';
    import SidebarFooter from '$lib/components/ui/sidebar/SidebarFooter.svelte';
    import HawkLogo from '$lib/components/ui/logo/HawkLogo.svelte';
    import SidebarIconButton from '$lib/components/ui/sidebar/SidebarIconButton.svelte';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import SpaceTabs from '$lib/app/components/sidebar/SpaceTabs.svelte';
    import ProfileButton from '$lib/app/components/sidebar/ProfileButton.svelte';
    import MobileNavCollapse from '$lib/app/components/sidebar/MobileNavCollapse.svelte';
    import SearchDialog from '$lib/app/components/search/SearchDialog.svelte';
    import SettingsDialog, {type SettingsSection} from '$lib/app/components/settings/SettingsDialog.svelte';
    import {onMount} from 'svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';
    import {isApple} from '$lib/utils/platform.js';

    const app = useApp();
    const router = useRouter();
    const sidebar = useSidebar();
    const chatStore = useStore('chat');
    const {__} = useTranslator();
    const activeModule = $derived.by(() => app.modules.all.find(module =>
        router.isRouteActive(getModuleRouteGroupName(module.plugin.name, module.name))
    ) ?? null);
    const ModuleSidebar = $derived(activeModule?.sidebar?.(app.localization.locale) ?? null);

    const chatPath = router.getPath('chat.index');
    let searchOpen = $state(false);
    let settingsOpen = $state(false);
    let settingsSection = $state<SettingsSection | null>(null);
    // Holding Cmd (Ctrl off Apple) points out what has a shortcut in the sidebar.
    let shortcutsHeld = $state(false);

    function trackShortcutModifier(event: KeyboardEvent) {
        shortcutsHeld = isApple ? event.metaKey : event.ctrlKey;
    }

    function startNewChat(event: MouseEvent) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        if (sidebar.mobile) sidebar.navOpen = false;
        chatStore.requestNewChat();
        void router.goToRoute('chat.index');
    }

    function openSearch() {
        // The dialog shouldn't open over a drawer that would still cover the page
        // once it closes.
        if (sidebar.mobile) sidebar.navOpen = false;
        searchOpen = true;
    }

    function openSettings(section: SettingsSection | null = null) {
        settingsSection = section;
        settingsOpen = true;
    }

    onMount(() => app.events.sync.on('settingsRequested', section => openSettings(section)));
</script>

<!-- Blur clears it: a keyup in another window never reaches this one. -->
<svelte:window
    onkeydown={trackShortcutModifier}
    onkeyup={trackShortcutModifier}
    onblur={() => (shortcutsHeld = false)}
/>

<Sidebar label={__('ui.navigation.label')} class={shortcutsHeld ? 'shortcuts-held' : undefined}>
    <MobileNavCollapse />
    <SidebarHeader brandHref={chatPath} onBrandClick={startNewChat}>
        <HawkLogo label={__('ui.navigation.newChat')} />
        {#snippet actions()}
            <SidebarIconButton
                icon={Search01Icon}
                label={__('ui.navigation.search')}
                shortcut="K"
                aria-keyshortcuts={isApple ? 'Meta+K' : 'Control+K'}
                onclick={openSearch}
            >
                {#snippet tooltip()}
                    {__('ui.navigation.search')}
                    <span class="tooltip-shortcut">{isApple ? '⌘K' : 'Ctrl K'}</span>
                {/snippet}
            </SidebarIconButton>
        {/snippet}
    </SidebarHeader>
    <div class="sidebar-content">
        <div class="spaces">
            <SpaceTabs />
        </div>
        <div class="module-sidebar">
            {#if ModuleSidebar}
                <ModuleSidebar />
            {/if}
        </div>
        <SidebarFooter>
            <ProfileButton onOpenSettings={() => openSettings()}/>
        </SidebarFooter>
    </div>
</Sidebar>

<SearchDialog bind:open={searchOpen} />
<SettingsDialog bind:open={settingsOpen} section={settingsSection}/>

<style>
    .sidebar-content {
        display: flex;
        flex: 1;
        min-height: 0;
        flex-direction: column;
    }

    /* The panel's padding, matching the header's gap above the tabs. */
    .spaces {
        flex-shrink: 0;
        margin-bottom: var(--space-4);
    }

    .tooltip-shortcut {
        margin-left: var(--space-1);
        color: var(--color-text-muted);
    }

    .module-sidebar {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
    }
</style>
