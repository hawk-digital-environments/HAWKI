<script lang="ts">
    import type {ComponentProps} from 'svelte';
    import ChatNameMenu from '$lib/components/chat/nameMenu/ChatNameMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import {__} from '$lib/utils/translator.js';
    import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
    import {oldUiMessageHistory} from '$lib/oldUi/OldUiMessageHistory.svelte.js';
    import Settings05Icon from '$lib/components/ui/icons/iconset/Settings05Icon.svelte';
    import ViewIcon from '$lib/components/ui/icons/iconset/ViewIcon.svelte';
    import Logout02Icon from '$lib/components/ui/icons/iconset/Logout02Icon.svelte';

    type Props = {
        /** Whether the conversation has unread messages. Shows a visual indicator if true. */
        hasUnreadMessages?: boolean;
        /**
         * The current user's role in this room. Required when the menu is not rendered for the active room
         * (e.g. in the sidebar); if omitted, the role of the active conversation is used.
         */
        role?: string;
    } & Pick<ComponentProps<typeof ChatNameMenu>,
        'name' | 'nameClickRenames' | 'slug' | 'isRenaming' |
        'class' | 'buttonProps' | 'block' | 'triggerIcon'>;

    let {
        name,
        slug,
        isRenaming = $bindable(false),
        hasUnreadMessages,
        role,
        ...restProps
    }: Props = $props();

    const canAdministrate = $derived(role !== undefined ? role === 'admin' : oldUiMessageHistory.canAdministrate);

    let leaveConfirmOpen = $state(false);
    let deleteConfirmOpen = $state(false);
</script>
<ConfirmDialog
    bind:open={leaveConfirmOpen}
    title={__('chat.nameMenu.leaveConfirmTitle', {name: name})}
    description={__('chat.nameMenu.leaveConfirmDescription')}
    onConfirm={() => slug && oldUiBridge.triggerLeaveRoom(slug)}
/>
<ConfirmDialog
    bind:open={deleteConfirmOpen}
    title={__('chat.nameMenu.deleteConfirmTitle', {name: name})}
    description={__('chat.nameMenu.deleteConfirmDescription')}
    onConfirm={() => slug && oldUiBridge.triggerDeleteChat(slug)}
/>

<ChatNameMenu
    bind:isRenaming={isRenaming}
    allowRename={canAdministrate}
    name={name ?? ''}
    slug={slug ?? ''}
    {...restProps}
>
    {#if !!slug}
        {#if canAdministrate}
            <DropdownMenuItem
                icon={Settings05Icon}
                onclick={() => oldUiBridge.triggerOpenRoomControlPanel(slug ?? '')}>
                {__('chat.nameMenu.manageRoom')}
            </DropdownMenuItem>
        {/if}
        <DropdownMenuItem
            icon={ViewIcon}
            onclick={() => oldUiBridge.triggerMarkRoomMessagesAsRead(slug ?? '')}
            disabled={!hasUnreadMessages}>
            {__('chat.nameMenu.markAsRead')}
        </DropdownMenuItem>
        <DropdownMenuSeparator/>

        <DropdownMenuItem
            variant="destructive"
            icon={Logout02Icon}
            onclick={() => leaveConfirmOpen = true}>
            {__('chat.nameMenu.leaveAction')}
        </DropdownMenuItem>
        {#if canAdministrate}
            <DropdownMenuItem
                variant="destructive"
                onclick={() => deleteConfirmOpen = true}>
                {__('chat.nameMenu.deleteAction')}
            </DropdownMenuItem>
        {/if}
    {/if}
</ChatNameMenu>
