<!--
  @component Renders the tool picker's list panel: pinned tools first (ungrouped, in pin
  order), then capabilities (ungrouped), then HAWKI
  function tools under one shared header, then one group per MCP server — each group
  separated by a `DropdownMenuSeparator` when both neighbouring sections are non-empty.
  This is the `children` (default/list) panel of `ToolMenu`'s `DropdownMenuDetailView`;
  clicking a row's info icon swaps to `ToolMenuDetail` instead of navigating within this list.

  ## Usage
  Rendered by `ToolMenu` with the entries it derived from the `ai-tools` store and
  `composerContext.tools`/`composerContext.model`:
  ```svelte
  <DropdownMenuDetailView open={!!detailEntry}>
      {#snippet details()}...{/snippet}
      <ToolMenuList entries={groupedEntries} onOpenDetail={openToolDetail}/>
  </DropdownMenuDetailView>
  ```
-->
<script lang="ts">

    import type {ToolMenuEntry, ToolMenuGroupedEntries} from '$plugins/core/modules/chat/components/composer/ToolMenu.svelte';
    import ToolMenuListItem from '$plugins/core/modules/chat/components/composer/ToolMenuListItem.svelte';
    import ToolMenuGroupHeader from '$plugins/core/modules/chat/components/composer/ToolMenuGroupHeader.svelte';
    import DropdownMenuLabel from '$lib/components/ui/dropdown-menu/DropdownMenuLabel.svelte';
    import DropdownMenuSeparator from '$lib/components/ui/dropdown-menu/DropdownMenuSeparator.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** Tool entries pre-grouped by `ToolMenu` into capabilities / function tools / MCP-server groups. */
        entries: ToolMenuGroupedEntries;
        /** Forwarded to every `ToolMenuListItem`; called when a row's info icon (or ArrowRight)
         *  requests the detail view for that entry. */
        onOpenDetail?: (entry: ToolMenuEntry) => void;
    }

    const {entries, onOpenDetail}: Props = $props();
</script>

{#each Object.keys(entries) as groupKey (groupKey)}
    {#if groupKey === 'pinned' && entries.pinned.length > 0}
        <DropdownMenuLabel>{__('chat.composer.pin.pinnedLabel')}</DropdownMenuLabel>
        {#each entries.pinned as entry (entry.tool.name)}
            <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
        {/each}
    {:else if groupKey === 'capabilities' && entries.capabilities.length > 0}
        {#if entries.pinned.length > 0}
            <DropdownMenuSeparator/>
        {/if}
        <DropdownMenuLabel>{__('chat.composer.toolMenu.capabilitiesLabel')}</DropdownMenuLabel>
        {#each entries.capabilities as entry (entry.tool.name)}
            <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
        {/each}
    {:else if groupKey === 'functionTools' && entries.functionTools.length > 0}
        {#if entries.pinned.length > 0 || entries.capabilities.length > 0}
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
        {#if entries.functionTools.length > 0 || entries.capabilities.length > 0 || entries.pinned.length > 0}
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
