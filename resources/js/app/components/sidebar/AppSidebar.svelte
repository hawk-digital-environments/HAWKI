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
    import MobileNavCollapse from '$lib/app/components/sidebar/MobileNavCollapse.svelte';
    import SearchDialog from '$lib/app/components/search/SearchDialog.svelte';
    import SettingsDialog, {type SettingsSection} from '$lib/app/components/settings/SettingsDialog.svelte';
    import AccessibilityIcon from '$lib/components/ui/icons/iconset/AccessibilityIcon.svelte';
    import ExternalLinkIcon from '$lib/components/ui/icons/iconset/ExternalLinkIcon.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import {onMount} from 'svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';
    import type {HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';

    const app = useApp();
    const router = useRouter();
    const sidebar = useSidebar();
    const chatStore = useStore('chat');
    const config = useConfig();
    const {__} = useTranslator();
    // Only rendered when an operator configured one (ACCESSIBILITY_STATEMENT_URL).
    const accessibilityStatementUrl = $derived(config.accessibility?.statementUrl ?? null);
    const activeModule = $derived.by(() => app.modules.all.find(module =>
        router.isRouteActive(getModuleRouteGroupName(module.plugin.name, module.name))
    ) ?? null);

    const visibleModules = $derived(app.modules.all.filter(module => module.visible?.(app) ?? true));

    // On routes that belong to no module at all (e.g. the announcements page,
    // where `activeModule` is null) the module sidebar and the module
    // selector stick to the last active module instead of vanishing, falling
    // back to the first module for direct page loads.
    //
    // Only a *visible* active module is remembered here, so this fallback
    // chain never lands on a module hidden from the selector (e.g. the
    // assistants builder — see `BuilderModule.visible()`); an active-but-
    // invisible module is instead handled directly by `sidebarModule` below,
    // without ever consulting this fallback.
    let lastActiveModule = $state<HawkiModuleWithPlugin | null>(null);
    $effect(() => {
        if (activeModule && visibleModules.includes(activeModule)) {
            lastActiveModule = activeModule;
        }
    });

    // The active module always wins, whether or not it is visible in the
    // selector (an invisible-but-active module, like the builder, still owns
    // its own sidebar — see `BuilderModule.sidebar()` — and must render it
    // directly rather than falling through to some other module, which is
    // what made this regress on a cold/direct page load into the builder:
    // `lastActiveModule` starts out `null`, so the old fallback chain landed
    // on `visibleModules[0]` instead). The fallback chain below therefore
    // only ever runs for a route that belongs to no module at all.
    const sidebarModule = $derived(
        activeModule
        ?? [lastActiveModule].find(module => module && visibleModules.includes(module))
        ?? visibleModules[0]
        ?? null
    );
    const ModuleSidebar = $derived(sidebarModule?.sidebar?.(app.localization.locale) ?? null);

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
    </SidebarHeader>
    <nav class="module-selector" aria-label={__('ui.navigation.mainLabel')}>
        <ModuleSelector module={sidebarModule} />
    </nav>
    <div class="module-sidebar">
        {#if ModuleSidebar}
            <ModuleSidebar />
        {/if}
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
</style>
