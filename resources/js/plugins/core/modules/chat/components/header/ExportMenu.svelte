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

    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {oldUiBridge, type OldUiExportType} from '$lib/legacy/OldUiBridge.svelte.js';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
    import FileExportIcon from '$lib/components/ui/icons/iconset/FileExportIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();
    
    interface Props {
        onExport?: (format: OldUiExportType) => void;
    }

    const {onExport = (format) => oldUiBridge.triggerExport(format)}: Props = $props();

    function handleExport(format: OldUiExportType) {
        onExport(format);
    }

</script>
<DropdownMenu title={__('chat.export.title')} align="end">
    {#snippet trigger({props})}
        <ButtonWithTooltip
            variant="stroke"
            size="xs"
            iconLeft={FileExportIcon}
            tooltip={__('chat.export.tooltip')}
            aria-label={__('chat.export.title')}
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
