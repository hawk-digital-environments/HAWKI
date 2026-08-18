<!--
  @component Chat name menu specialised for multi-user rooms.

  Wraps `ChatNameMenu` and adds room-only actions to its dropdown: "Manage
  room" (admin only, opens the room control panel), "Mark as read" (disabled
  when there are no unread messages), "Leave room", and — for admins —
  "Delete room". The destructive actions each gate behind a `ConfirmDialog`
  before dispatching to the legacy UI (`oldUiBridge.triggerLeaveRoom` /
  `triggerDeleteChat`). Forwards the rename surface (`name`, `slug`,
  `isRenaming`, …) straight through to `ChatNameMenu`.

  @example
  <RoomNameMenu
      bind:name={room.name}
      bind:isRenaming={renaming}
      slug={room.slug}
      hasUnreadMessages={room.hasUnread}
  />
-->
<script lang="ts">
    import type {ComponentProps} from 'svelte';
    import {ConfirmDialog, DropdownMenuItem, DropdownMenuSeparator} from '@hawk-hhg/hawki-svelte-components';
    import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';
    import {oldUiMessageHistory} from '$lib/legacy/OldUiMessageHistory.svelte.js';
    import Settings05Icon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/Settings05Icon.svelte';
    import ViewIcon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/ViewIcon.svelte';
    import Logout02Icon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/Logout02Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import ChatNameMenu from '$plugins/core/modules/chat/components/nameMenu/ChatNameMenu.svelte';

    const {__} = useTranslator();

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
        {#if oldUiMessageHistory.canAdministrate}
            <DropdownMenuItem
                variant="destructive"
                onclick={() => deleteConfirmOpen = true}>
                {__('chat.nameMenu.deleteAction')}
            </DropdownMenuItem>
        {/if}
    {/if}
</ChatNameMenu>
