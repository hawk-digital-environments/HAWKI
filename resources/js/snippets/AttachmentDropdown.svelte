<script lang="ts">
    import {Ellipsis} from '@lucide/svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import {__} from '$lib/utils/translator.js';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
</script>
<DropdownMenu>
    {#snippet trigger({props})}
        <button class="burger-btn btn-xs" {...props}>
            <Ellipsis size="12"/>
        </button>
    {/snippet}
    <DropdownMenuItem>
        {__("FilePreview")}
    </DropdownMenuItem>
    <DropdownMenuItem>
        {__("FileDownload")}
    </DropdownMenuItem>
    <DropdownMenuSeparator/>
    <DropdownMenuItem variant="destructive">
        {__("FileRemove")}
    </DropdownMenuItem>
</DropdownMenu>
<!-- <div id="attachment-menu" class="burger-dropdown" style="display: none">
    <div class="burger-expandable">
        <button class="burger-item" id="open-btn">{{ __("FilePreview") }}</button>
        <button class="burger-item" id="download-btn">{{ __("FileDownload") }}</button>
        <button class="burger-item red-text" id="remove-btn">{{ __("FileRemove")}}</button>
    </div>
</div> -->

<style>

</style>
