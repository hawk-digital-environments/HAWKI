<!--
@component Burger-menu dropdown attached to a message attachment (preview/download/delete), as a
`<svelte-snippet>` entry point registered by `core.plugin.ts`. Preview is only offered for
images/Word docs/PDFs; delete is only offered for private attachments or ones the current user
authored, and always asks for confirmation first. All actions are dispatched through
`oldUiBridge`, since attachment rendering itself is still owned by the legacy chat JS.

Usage (embedded in the legacy attachment template — see
`resources/views/partials/home/templates/attachment-template.blade.php`):
```blade
<x-svelte type="AttachmentDropdown" :props="['fileData' => $fileDataAsArray]" />
```
-->
<script lang="ts">
    import {ConfirmDialog, DropdownMenu, DropdownMenuItem, DropdownMenuSeparator} from '@hawk-hhg/hawki-svelte-components';
    import FileViewIcon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/FileViewIcon.svelte';
    import Download01Icon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/Download01Icon.svelte';
    import EllipsisIcon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/EllipsisIcon.svelte';
    import {oldUiBridge, type OldUiFileData} from '$lib/legacy/OldUiBridge.svelte.js';
    import {oldUiMessageHistory} from '$lib/legacy/OldUiMessageHistory.svelte.js';
    import {useAuthenticatedConnection} from '$lib/app/hooks/useConnection.svelte.js';
    import {__} from '$lib/kernel/localization/helpers.js';

    interface Props {
        /** The attachment this menu operates on, e.g. `{category: 'private', mime: 'image/png', name: 'photo.png', type: 'image', url: '...', uuid: '...'}`. Comes from the legacy `OldUiFileData` shape used throughout the old chat JS. */
        fileData: OldUiFileData;
    }

    const connection = useAuthenticatedConnection();

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

        return message.author.username === connection?.userinfo.username;
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
