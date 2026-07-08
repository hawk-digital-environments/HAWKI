<script lang="ts">
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import {oldUiBridge, type OldUiFileData} from '$lib/oldUi/OldUiBridge.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import {oldUiMessageHistory} from '$lib/oldUi/OldUiMessageHistory.svelte.js';
    import {getAuthenticatedConnection} from '$lib/data/connection/connection.js';
    import FileViewIcon from '$lib/components/ui/icons/iconset/FileViewIcon.svelte';
    import Download01Icon from '$lib/components/ui/icons/iconset/Download01Icon.svelte';
    import EllipsisIcon from '$lib/components/ui/icons/iconset/EllipsisIcon.svelte';

    interface Props {
        fileData: OldUiFileData;
    }

    const {
        fileData
    }: Props = $props();

    let deleteConfirm = $state(false);

    const canPreview = $derived.by(() => {
        return fileData.type === 'image'
            || fileData.mime.includes('msword')
            || fileData.mime.includes('wordprocessingml')
            || fileData.mime.includes('pdf')
            ;
    });

    const canDelete = $derived.by(() => {
        if (fileData.category === 'private') {
            return true;
        }

        const message = oldUiMessageHistory.findMessageByAttachmentUuid(fileData.uuid);
        if (!message) {
            return false;
        }

        const currentUsername = getAuthenticatedConnection().userinfo.username;
        return message.author.username === currentUsername;
    });
</script>

<ConfirmDialog
    bind:open={deleteConfirm}
    title={__('chat.attachmentDropdown.deleteTitle')}
    description={__('chat.attachmentDropdown.deleteDescription', {name: fileData.name})}
    onConfirm={() => oldUiBridge.triggerDeleteAttachment(fileData)}
/>

<DropdownMenu>
    {#snippet trigger({props})}
        <button class="burger-btn btn-xs" {...props}>
            <EllipsisIcon size="12"/>
        </button>
    {/snippet}
    {#if canPreview}
        <DropdownMenuItem
            icon={FileViewIcon}
            onclick={() => oldUiBridge.triggerPreviewAttachment(fileData)}>
            {__('chat.attachmentDropdown.preview')}
        </DropdownMenuItem>
    {/if}
    <DropdownMenuItem
        icon={Download01Icon}
        onclick={() => oldUiBridge.triggerDownloadAttachment(fileData)}>
        {__('chat.attachmentDropdown.download')}
    </DropdownMenuItem>
    {#if canDelete}
        <DropdownMenuSeparator/>
        <DropdownMenuItem
            onclick={() => deleteConfirm = true}
            variant="destructive">
            {__('chat.attachmentDropdown.delete')}
        </DropdownMenuItem>
    {/if}
</DropdownMenu>


<style>

</style>
