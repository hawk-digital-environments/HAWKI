<script lang="ts">
    import {Download, Ellipsis, Eye} from '@lucide/svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import {oldUiBridge, type OldUiFileData} from '$lib/oldUi/OldUiBridge.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';

    interface Props {
        fileData: OldUiFileData;
    }

    const {
        fileData
    }: Props = $props();

    let deleteConfirm = $state(false);
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
            <Ellipsis size="12"/>
        </button>
    {/snippet}
    <DropdownMenuItem
        icon={Eye}
        onclick={() => oldUiBridge.triggerPreviewAttachment(fileData)}>
        {__('chat.attachmentDropdown.preview')}
    </DropdownMenuItem>
    <DropdownMenuItem
        icon={Download}
        onclick={() => oldUiBridge.triggerDownloadAttachment(fileData)}>
        {__('chat.attachmentDropdown.download')}
    </DropdownMenuItem>
    <DropdownMenuSeparator/>
    <DropdownMenuItem
        onclick={() => deleteConfirm = true}
        variant="destructive">
        {__('chat.attachmentDropdown.delete')}
    </DropdownMenuItem>
</DropdownMenu>


<style>

</style>
