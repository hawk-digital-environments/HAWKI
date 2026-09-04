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
    import Settings05Icon from '$lib/components/ui/icons/iconset/Settings05Icon.svelte';
    import UserIcon from '$lib/components/ui/icons/iconset/UserIcon.svelte';
    import FlaskConicalIcon from '$lib/components/ui/icons/iconset/FlaskConicalIcon.svelte';
    import SunIcon from '$lib/components/ui/icons/iconset/SunIcon.svelte';
    import MoonIcon from '$lib/components/ui/icons/iconset/MoonIcon.svelte';
    import Logout02Icon from '$lib/components/ui/icons/iconset/Logout02Icon.svelte';
    import {onMount} from 'svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';

    const app = useApp();
    const router = useRouter();
    const sidebar = useSidebar();
    const chatStore = useStore('chat');
    const themeStore = useStore('theme');
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

    function openSettings(section: SettingsSection | null = null) {
        settingsSection = section;
        settingsOpen = true;
    }

    // The account actions (settings sections, theme, logout) live in the app
    // shell rather than in a plugin, so they are contributed to the search
    // palette from here — the one place that owns the settings dialog. The
    // getters read `themeStore` and the translator reactively, so the theme
    // row flips its label and icon with the current theme. Registered from
    // `onMount`, not `$effect`: `addGroup` reads and writes the registry's
    // `$state`, which inside an effect would re-trigger it endlessly.
    const SETTINGS_GROUP_ID = 'app:settings';
    onMount(() => {
        app.search.addGroup({
            id: SETTINGS_GROUP_ID,
            label: () => __('ui.search.settings.label'),
            items: () => [
                {
                    id: `${SETTINGS_GROUP_ID}/general`,
                    title: __('ui.search.settings.general'),
                    icon: Settings05Icon,
                    keywords: [__('ui.settings.nav.general'), __('ui.settings.general.languageLabel')],
                    onSelect: () => openSettings('general')
                },
                {
                    id: `${SETTINGS_GROUP_ID}/profile`,
                    title: __('ui.search.settings.profile'),
                    icon: UserIcon,
                    keywords: [__('ui.settings.nav.profile')],
                    onSelect: () => openSettings('profile')
                },
                {
                    id: `${SETTINGS_GROUP_ID}/experiments`,
                    title: __('ui.settings.nav.experiments'),
                    icon: FlaskConicalIcon,
                    onSelect: () => openSettings('experiments')
                },
                {
                    id: `${SETTINGS_GROUP_ID}/theme`,
                    title: themeStore.isDark ? __('ui.profile.lightMode') : __('ui.profile.darkMode'),
                    icon: themeStore.isDark ? SunIcon : MoonIcon,
                    keywords: [__('ui.settings.general.themeLabel'), __('ui.settings.general.themeLight'), __('ui.settings.general.themeDark')],
                    onSelect: () => (themeStore.theme = themeStore.isDark ? 'light' : 'dark')
                },
                {
                    id: `${SETTINGS_GROUP_ID}/logout`,
                    title: __('ui.profile.logout'),
                    icon: Logout02Icon,
                    onSelect: () => app.logout()
                }
            ]
        });
        return () => app.search.removeGroup(SETTINGS_GROUP_ID);
    });
</script>

<Sidebar label={__('ui.navigation.label')}>
    <MobileNavCollapse />
    <SidebarHeader brandHref={chatPath} onBrandClick={startNewChat} onSearch={() => searchOpen = true}>
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
