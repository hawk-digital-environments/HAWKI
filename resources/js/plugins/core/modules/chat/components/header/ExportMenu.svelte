<!--
  @component Dropdown for exporting the current conversation in various formats.

  Renders a `ButtonWithTooltip` trigger (label hidden on small screens via
  `Breakpoint`) inside a `DropdownMenu`, with one item per supported export
  format (print, pdf, word, csv, json). Selecting an item calls `onExport`
  with the chosen format; the caller decides how the export is produced.

  @example
  <ExportMenu onExport={format => exportConversation(format)} />
-->
<script lang="ts">

    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import type {ConversationExportFormat} from '$plugins/core/modules/chat/utils/exportConversation.js';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
    import FileExportIcon from '$lib/components/ui/icons/iconset/FileExportIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';

    const {__} = useTranslator();
    const toast = useToastContext();
    
    interface Props {
        /** Called with the chosen export format. */
        onExport: (format: ConversationExportFormat) => void | Promise<void>;
    }

    const {onExport}: Props = $props();

    async function handleExport(format: ConversationExportFormat) {
        try {
            await onExport(format);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : __('chat.export.error'));
        }
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
