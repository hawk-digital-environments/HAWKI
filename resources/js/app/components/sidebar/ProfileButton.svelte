<!--
  @component Sidebar profile control. Opens the account dropdown with the
  settings entry, a light/dark theme toggle and the logout action. The
  settings dialog itself is owned by `AppSidebar` (it is also opened from the
  search palette); this component only asks for it via `onOpenSettings`.
-->
<script lang="ts">
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import Settings03Icon from '$lib/components/ui/icons/iconset/Settings03Icon.svelte';
    import Settings05Icon from '$lib/components/ui/icons/iconset/Settings05Icon.svelte';
    import SunIcon from '$lib/components/ui/icons/iconset/SunIcon.svelte';
    import MoonIcon from '$lib/components/ui/icons/iconset/MoonIcon.svelte';
    import Logout02Icon from '$lib/components/ui/icons/iconset/Logout02Icon.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useConnection} from '$lib/app/hooks/useConnection.svelte.js';

    interface Props {
        /** Called when the user picks "Settings" from the menu. */
        onOpenSettings: () => void;
    }

    let {onOpenSettings}: Props = $props();

    const app = useApp();
    const themeStore = useStore('theme');
    const {__} = useTranslator();
    const connection = useConnection();
    const userinfo = $derived(connection.hasUserInfo ? connection.userinfo : null);
    const userName = $derived(userinfo?.name || __('ui.profile.fallbackName'));
    const userEmail = $derived(userinfo?.email ?? '');
    const avatarIdentifier = $derived(
        userinfo && 'avatar' in userinfo && typeof userinfo.avatar === 'string'
            ? userinfo.avatar
            : null
    );
    const avatarUrl = $derived(app.uriBuilder.storageFileUri(avatarIdentifier) ?? undefined);

    let menuOpen = $state(false);

    function toggleTheme(): void {
        themeStore.theme = themeStore.isDark ? 'light' : 'dark';
    }

    function openSettings(): void {
        menuOpen = false;
        onOpenSettings();
    }

    function logout(): void {
        app.logout();
    }
</script>

<DropdownMenu
    bind:open={menuOpen}
    title={__('ui.profile.menuTitle')}
    side="top"
    align="start"
    sideOffset={8}
    contentProps={{class: 'profile-menu-content'}}
>
    {#snippet trigger({props})}
        <SidebarItem label={userName} active={menuOpen} {...props}>
            {#snippet media()}
                <Avatar src={avatarUrl} name={userName} label={userName} size={22}/>
            {/snippet}
            {#snippet trailing()}
                <Settings03Icon size={16} strokeWidth={2}/>
            {/snippet}
        </SidebarItem>
    {/snippet}

    <div class="profile-summary">
        <Avatar src={avatarUrl} name={userName} label={userName} size={32}/>
        <div class="profile-summary__text">
            <strong>{userName}</strong>
            {#if userEmail}<span>{userEmail}</span>{/if}
        </div>
    </div>

    <DropdownMenuSeparator/>
    <DropdownMenuItem icon={Settings05Icon} onclick={openSettings}>
        {__('ui.profile.settings')}
    </DropdownMenuItem>
    <DropdownMenuItem icon={themeStore.isDark ? SunIcon : MoonIcon} closeOnSelect={false} onclick={toggleTheme}>
        {themeStore.isDark ? __('ui.profile.lightMode') : __('ui.profile.darkMode')}
    </DropdownMenuItem>
    <DropdownMenuSeparator/>
    <DropdownMenuItem icon={Logout02Icon} onclick={logout}>
        {__('ui.profile.logout')}
    </DropdownMenuItem>
</DropdownMenu>

<style>
    :global(.profile-menu-content.profile-menu-content) {
        width: min(15rem, calc(100vw - 2 * var(--space-4)));
    }

    .profile-summary {
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        padding: var(--space-2);
    }

    .profile-summary__text {
        display: flex;
        min-width: 0;
        flex: 1;
        flex-direction: column;
        gap: var(--space-0_5);
    }

    .profile-summary__text strong,
    .profile-summary__text span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .profile-summary__text strong {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }

    .profile-summary__text span {
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
    }
</style>
