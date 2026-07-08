<script lang="ts">

    import type {ToolMenuEntry, ToolMenuGroupedEntries} from '$lib/components/chat/composer/ToolMenu.svelte';
    import ToolMenuListItem from '$lib/components/chat/composer/ToolMenuListItem.svelte';
    import ToolMenuGroupHeader from '$lib/components/chat/composer/ToolMenuGroupHeader.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        entries: ToolMenuGroupedEntries;
        onOpenDetail?: (entry: ToolMenuEntry) => void;
    }

    const {entries, onOpenDetail}: Props = $props();
</script>

{#each Object.keys(entries) as groupKey (groupKey)}
    {#if groupKey === 'capabilities' && entries.capabilities.length > 0}
        <DropdownMenuLabel>{__('chat.composer.toolMenu.capabilitiesLabel')}</DropdownMenuLabel>
        {#each entries.capabilities as entry (entry.tool.name)}
            <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
        {/each}
    {:else if groupKey === 'functionTools' && entries.functionTools.length > 0}
        {#if entries.capabilities.length > 0}
            <DropdownMenuSeparator/>
        {/if}
        <ToolMenuGroupHeader
            id="function-tools"
            label={__('chat.composer.toolMenu.hawkiToolsLabel')}
            description={__('chat.composer.toolMenu.hawkiToolsDescription')}/>
        {#each entries.functionTools as entry (entry.tool.name)}
            <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
        {/each}
    {:else if groupKey === 'mcpTools' && entries.mcpTools.length > 0}
        {#if entries.functionTools.length > 0 || (entries.capabilities.length > 0 && entries.functionTools.length === 0)}
            <DropdownMenuSeparator/>
        {/if}
        {#each entries.mcpTools as serverGroup (serverGroup.id)}
            <ToolMenuGroupHeader
                id={serverGroup.id}
                label={serverGroup.label}
                description={serverGroup.description}/>
            {#each serverGroup.entries as entry (entry.tool.name)}
                <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
            {/each}
        {/each}
    {/if}
{/each}
