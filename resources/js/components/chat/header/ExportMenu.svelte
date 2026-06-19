<script lang="ts">

    import {Upload} from '@lucide/svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {oldUiBridge, type OldUiExportType} from '$lib/oldUi/OldUiBridge.svelte.js';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';

    import {__} from '$lib/utils/translator.js';

    function handleExport(format: OldUiExportType) {
        oldUiBridge.triggerExport(format);
    }

</script>
<DropdownMenu title={__('chat.export.title')} align="end">
    {#snippet trigger({props})}
        <ButtonWithTooltip
            variant="stroke"
            size="xs"
            iconLeft={Upload}
            tooltip={__('chat.export.tooltip')}
            highlight={props['data-state']}
            {...props}>
            <Breakpoint>
                {#snippet bpSmAndBigger()}
                    {__('chat.export.title')}
                {/snippet}
            </Breakpoint>
        </ButtonWithTooltip>
    {/snippet}
    <DropdownMenuItem onclick={() => handleExport('print')}>
        {__('chat.export.print')}
    </DropdownMenuItem>
    <DropdownMenuItem onclick={() => handleExport('pdf')}>
        {__('chat.export.pdf')}
    </DropdownMenuItem>
    <DropdownMenuItem onclick={() => handleExport('word')}>
        {__('chat.export.word')}
    </DropdownMenuItem>
    <DropdownMenuItem onclick={() => handleExport('csv')}>
        {__('chat.export.csv')}
    </DropdownMenuItem>
    <DropdownMenuItem onclick={() => handleExport('json')}>
        {__('chat.export.json')}
    </DropdownMenuItem>
</DropdownMenu>
