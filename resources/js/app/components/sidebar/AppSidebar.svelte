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
    import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
    import ModuleSelector from '$lib/app/components/sidebar/ModuleSelector.svelte';
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

<Sidebar label={__('ui.navigation.label')}>
    <MobileNavCollapse />
    <SidebarHeader brandHref={chatPath} onBrandClick={startNewChat}>
        <HawkLogo label={__('ui.navigation.newChat')} />
        {#snippet actions()}
            <ModuleSelector />
        {/snippet}
    </SidebarHeader>
    <button type="button" class="search" aria-label={__('ui.navigation.search')} onclick={openSearch}>
        <Search01Icon size={18} strokeWidth={2} aria-hidden="true" />
        <span class="search-placeholder">{__('ui.search.placeholder')}</span>
        <span class="search-shortcut" aria-hidden="true">{isApple ? '⌘K' : 'Ctrl K'}</span>
    </button>
    <div class="module-sidebar">
        {#if ModuleSidebar}
            <ModuleSidebar />
        {/if}
    </div>
    <SidebarFooter>
        <ProfileButton onOpenSettings={() => openSettings()}/>
    </SidebarFooter>
</Sidebar>

<SearchDialog bind:open={searchOpen} />
<SettingsDialog bind:open={settingsOpen} section={settingsSection}/>

<style>
    /* Rests like a row and turns into a field on hover or focus. It opens the search
       dialog rather than taking input, since the results need more room. The stroke
       is an inset outline, so it takes no layout space and the icon stays on the
       rows' column. */
    .search {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: var(--space-2);
        width: 100%;
        min-height: var(--nav-row-h);
        margin-bottom: var(--nav-group-gap);
        padding: 0 var(--space-3) 0 var(--nav-item-pad-x);
        border: none;
        outline: var(--divider-width) solid transparent;
        outline-offset: calc(var(--divider-width) * -1);
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--nav-icon-color);
        font-size: var(--nav-font-size);
        text-align: left;
        cursor: text;
        transition: outline-color var(--duration-fast);
    }

    .search:hover,
    .search:focus-visible {
        outline-color: var(--color-border);
    }

    .search :global(svg) {
        flex-shrink: 0;
    }

    .search-placeholder {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        color: var(--color-text-muted);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .search-shortcut {
        flex-shrink: 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        opacity: 0;
        transition: opacity var(--duration-fast);
    }

    .search:hover .search-shortcut,
    .search:focus-visible .search-shortcut {
        opacity: 1;
    }

    /* Bumped up a notch on small screens, like the sidebar's rows. */
    @media (--bp-md-and-smaller) {
        .search {
            font-size: var(--font-size-sm);
        }

        .search :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
            margin-inline: -1px;
        }
    }

    .module-sidebar {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
    }
</style>
