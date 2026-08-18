<!--
  @component Dropdown for exporting the current conversation in various formats.

  Renders a `ButtonWithTooltip` trigger (label hidden on small screens via
  `Breakpoint`) inside a `DropdownMenu`, with one item per supported export
  format (print, pdf, word, csv, json). Selecting an item forwards the format
  to the legacy UI via `oldUiBridge.triggerExport` — the actual export work
  still lives in the old UI; this component is just the new-Svelte entry point.

  @example
  <ExportMenu />
-->
<script lang="ts">

    import {oldUiBridge, type OldUiExportType} from '$lib/legacy/OldUiBridge.svelte.js';
    import {Breakpoint, ButtonWithTooltip, DropdownMenu, DropdownMenuItem} from '@hawk-hhg/hawki-svelte-components';
    import FileExportIcon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/FileExportIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();
    
    function handleExport(format: OldUiExportType) {
        oldUiBridge.triggerExport(format);
    }

</script>
<DropdownMenu title={__('chat.export.title')} align="end">
    {#snippet trigger({props})}
        <ButtonWithTooltip
            variant="stroke"
            size="xs"
            iconLeft={FileExportIcon}
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
