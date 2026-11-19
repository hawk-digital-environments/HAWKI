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
    import type {HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';
    import {useKeyboard} from '$lib/components/ui/keyboard/KeyboardState.svelte.js';
    import ModelsDialog from '$plugins/core/components/ModelsDialog.svelte';
    import AccessibilityIcon from '$lib/components/ui/icons/iconset/AccessibilityIcon.svelte';
    import ExternalLinkIcon from '$lib/components/ui/icons/iconset/ExternalLinkIcon.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';
    import {useSidebarSlots} from '$lib/app/ui/useSidebarHooks.svelte.js';

    const app = useApp();
    const router = useRouter();
    const sidebar = useSidebar();
    const chatStore = useStore('chat');
    const config = useConfig();
    const {__} = useTranslator();
    const keyboard = useKeyboard();

    // Plugins contribute sidebar chrome (header/footer/action extras, panels) via the `sidebarSlots` hook.
    const sidebarSlots = useSidebarSlots();
    const slots = $derived(sidebarSlots.entries);
    const headerSlots = $derived(slots.filter(slot => slot.position === 'header' && slot.active));
    const footerSlots = $derived(slots.filter(slot => slot.position === 'footer' && slot.active));
    const actionSlots = $derived(slots.filter(slot => slot.position === 'action' && slot.active));
    const panels = $derived(slots.filter(slot => slot.position === 'panel' && slot.active));

    // Only rendered when an operator configured one (ACCESSIBILITY_STATEMENT_URL).
    const accessibilityStatementUrl = $derived(config.accessibility?.statementUrl ?? null);

    const activeModule = $derived.by(() => app.modules.all.find(module =>
        router.isRouteActive(getModuleRouteGroupName(module.plugin.name, module.name))
    ) ?? null);

    // On routes that belong to no module (e.g. the announcements page) the
    // module sidebar sticks to the last active module instead of vanishing,
    // falling back to the first module for direct page loads.
    let lastActiveModule = $state<HawkiModuleWithPlugin | null>(null);
    $effect(() => {
        if (activeModule) {
            lastActiveModule = activeModule;
        }
    });
    const sidebarModule = $derived(activeModule ?? lastActiveModule ?? app.modules.all[0] ?? null);
    const ModuleSidebar = $derived(sidebarModule?.sidebar?.(app.localization.locale) ?? null);

    const chatPath = router.getPath('chat.index');
    let searchOpen = $state(false);
    let settingsOpen = $state(false);
    let settingsSection = $state<SettingsSection | null>(null);
    let modelsOpen = $state(false);

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

<Sidebar label={__('ui.navigation.label')} class={keyboard.shortcutModifierHeld ? 'shortcuts-held' : undefined}>
    <MobileNavCollapse />
    <SidebarHeader brandHref={chatPath} onBrandClick={startNewChat}>
        <HawkLogo label={__('ui.navigation.newChat')} />
        {#snippet actions()}
            <SidebarIconButton
                icon={Search01Icon}
                label={__('ui.navigation.search')}
                shortcut="K"
                aria-keyshortcuts={keyboard.aria({key: 'k', ctrl: true})}
                onclick={openSearch}
            >
                {#snippet tooltip()}
                    {__('ui.navigation.search')}
                    <span class="tooltip-shortcut">{keyboard.label({key: 'k', ctrl: true})}</span>
                {/snippet}
            </SidebarIconButton>
            {#each headerSlots as slot (slot.id)}
                {@const HeaderExtra = slot.component}
                <HeaderExtra />
            {/each}
        {/snippet}
    </SidebarHeader>
    <div class="sidebar-content">
        <div class="spaces">
            <SpaceTabs />
        </div>
        <div class="module-sidebar">
            <!-- An active panel owns the module-sidebar area (see sidebarHooks.ts);
                 the module's own sidebar stands down so it cannot stack on top. -->
            {#if ModuleSidebar && panels.length === 0}
                <ModuleSidebar />
            {/if}
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

    .sidebar-actions {
        /* Pinned to the bottom of the column, directly above the profile
           footer; the module-sidebar area above it takes the free space. */
        margin-top: auto;
    }

    .module-sidebar {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
    }
</style>
