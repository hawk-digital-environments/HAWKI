<!--
  @component Sidebar profile control. Opens the account dropdown, owns the
  light/dark theme switcher, and launches the routed settings dialog.
-->
<script lang="ts">
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import DropdownMenuRadioGroup from '$lib/components/ui/dropdown-menu/DropdownMenuRadioGroup.svelte';
    import DropdownMenuRadioItem from '$lib/components/ui/dropdown-menu/DropdownMenuRadioItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import SettingsDialog from '$lib/app/components/settings/SettingsDialog.svelte';
    import Settings05Icon from '$lib/components/ui/icons/iconset/Settings05Icon.svelte';
    import type {AppTheme} from '$plugins/core/stores/ThemeStore.svelte.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useConnectionWithUserInfo} from '$lib/app/hooks/useConnection.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const app = useApp();
    const connection = useConnectionWithUserInfo();
    const themeStore = useStore('theme');
    const {__} = useTranslator();

    const userName = connection?.userinfo.name || __('ui.profile.fallbackName');
    const userEmail = connection?.userinfo.email ?? '';
    const avatarIdentifier = connection && 'avatar' in connection.userinfo && typeof connection.userinfo.avatar === 'string'
        ? connection.userinfo.avatar
        : null;
    const avatarUrl = app.uriBuilder.storageFileUri(avatarIdentifier) ?? undefined;

    let menuOpen = $state(false);
    let settingsOpen = $state(false);

    function setTheme(value: string): void {
        themeStore.theme = value as AppTheme;
    }

    function openSettings(): void {
        menuOpen = false;
        settingsOpen = true;
    }
</script>

<SettingsDialog bind:open={settingsOpen}/>

<DropdownMenu
    bind:open={menuOpen}
    title={__('ui.profile.menuTitle')}
    side="top"
    align="start"
    sideOffset={8}
    contentProps={{class: 'profile-menu-content'}}
>
    {#snippet trigger({props})}
        <SidebarItem label={userName} {...props}>
            {#snippet media()}
                <Avatar src={avatarUrl} name={userName} size={22}/>
            {/snippet}
        </SidebarItem>
    {/snippet}

    <div class="profile-summary">
        <Avatar src={avatarUrl} name={userName} size={32}/>
        <div class="profile-summary__text">
            <strong>{userName}</strong>
            {#if userEmail}<span>{userEmail}</span>{/if}
        </div>
    </div>

    <DropdownMenuSeparator/>
    <DropdownMenuLabel>{__('ui.profile.appearanceLabel')}</DropdownMenuLabel>
    <DropdownMenuRadioGroup value={themeStore.theme} onValueChange={setTheme}>
        <DropdownMenuRadioItem value="light">{__('ui.profile.lightMode')}</DropdownMenuRadioItem>
        <DropdownMenuRadioItem value="dark">{__('ui.profile.darkMode')}</DropdownMenuRadioItem>
    </DropdownMenuRadioGroup>
    <DropdownMenuSeparator/>
    <DropdownMenuItem icon={Settings05Icon} onclick={openSettings}>
        {__('ui.profile.settings')}
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
