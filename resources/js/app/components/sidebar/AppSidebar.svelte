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
    import SidebarIconButton from '$lib/components/ui/sidebar/SidebarIconButton.svelte';
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import SpaceTabs from '$lib/app/components/sidebar/SpaceTabs.svelte';
    import ProfileButton from '$lib/app/components/sidebar/ProfileButton.svelte';
    import MobileNavCollapse from '$lib/app/components/sidebar/MobileNavCollapse.svelte';
    import SearchDialog from '$lib/app/components/search/SearchDialog.svelte';
    import ModelsDialog from '$plugins/core/components/ModelsDialog.svelte';
    import SettingsDialog, {type SettingsSection} from '$lib/app/components/settings/SettingsDialog.svelte';
    import AccessibilityIcon from '$lib/components/ui/icons/iconset/AccessibilityIcon.svelte';
    import ExternalLinkIcon from '$lib/components/ui/icons/iconset/ExternalLinkIcon.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import {onMount} from 'svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';
    import {useModuleSelectorEntries, useSidebarSlots} from '$lib/app/ui/useSidebarHooks.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {isApple} from '$lib/utils/platform.js';

    const app = useApp();
    const router = useRouter();
    const sidebar = useSidebar();
    const chatStore = useStore('chat');
    const config = useConfig();
    const {__} = useTranslator();

    // A focused flow (e.g. the assistant builder's wizard) may empty the
    // collected entries to take the switcher out of the sidebar altogether.
    const moduleSelector = useModuleSelectorEntries();
    const hasModuleSelector = $derived(moduleSelector.entries.length > 0);

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
    let modelsOpen = $state(false);
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
    onMount(() => app.events.sync.on('modelsRequested', () => modelsOpen = true));
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
            {#each headerSlots as slot (slot.id)}
                {@const HeaderExtra = slot.component}
                <HeaderExtra />
            {/each}
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
        <!-- A focused flow (e.g. the assistant builder) empties the entries to hide the tabs. -->
        {#if hasModuleSelector}
            <nav class="spaces" aria-label={__('ui.navigation.mainLabel')}>
                <SpaceTabs />
            </nav>
        {/if}
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
    </div>
</Sidebar>

<SearchDialog bind:open={searchOpen} />
<SettingsDialog bind:open={settingsOpen} section={settingsSection}/>
<ModelsDialog bind:open={modelsOpen}/>

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

    .sidebar-actions {
        /* Pinned to the bottom of the column, directly above the profile
           footer; the module-sidebar area above it takes the free space. */
        margin-top: auto;
    }
</style>
