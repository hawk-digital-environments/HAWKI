<script lang="ts">
    import type {ComponentProps} from 'svelte';
    import ChatNameMenu from '$lib/components/chat/nameMenu/ChatNameMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import {__} from '$lib/utils/translator.js';
    import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
    import {Eye, LogOut, SlidersHorizontal} from '@lucide/svelte';
    import {oldUiMessageHistory} from '$lib/oldUi/OldUiMessageHistory.svelte.js';

    type Props = {
        /** Whether the conversation has unread messages. Shows a visual indicator if true. */
        hasUnreadMessages?: boolean;
    } & Pick<ComponentProps<typeof ChatNameMenu>,
        'name' | 'nameClickRenames' | 'slug' | 'allowRename' | 'isRenaming' |
        'class' | 'buttonProps' | 'block' | 'triggerIcon'>;

    let {
        name,
        slug,
        isRenaming = $bindable(false),
        hasUnreadMessages,
        ...restProps
    }: Props = $props();

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
    name={name ?? ''}
    slug={slug ?? ''}
    {...restProps}
>
    {#if !!slug}
        {#if oldUiMessageHistory.canAdministrate}
            <DropdownMenuItem
                icon={SlidersHorizontal}
                onclick={() => oldUiBridge.triggerOpenRoomControlPanel(slug ?? '')}>
                NT: Raum verwalten
            </DropdownMenuItem>
        {/if}
        <DropdownMenuItem
            icon={Eye}
            onclick={() => oldUiBridge.triggerMarkRoomMessagesAsRead(slug ?? '')}
            disabled={!hasUnreadMessages}>
            NT: Als gelesen markieren
        </DropdownMenuItem>
        <DropdownMenuSeparator/>

        <DropdownMenuItem
            variant="destructive"
            icon={LogOut}
            onclick={() => leaveConfirmOpen = true}>
            {__('chat.nameMenu.leaveAction')}
        </DropdownMenuItem>
        {#if oldUiMessageHistory.canAdministrate}
            <DropdownMenuItem
                variant="destructive"
                onclick={() => deleteConfirmOpen = true}>
                {__('chat.nameMenu.deleteAction')}
            </DropdownMenuItem>
        {/if}
    {/if}
</ChatNameMenu>
