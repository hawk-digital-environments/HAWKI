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

    import type {McpEntryGroup, ToolMenuEntry, ToolMenuGroupedEntries} from '$plugins/core/modules/chat/components/composer/ToolMenu.svelte';
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
        /** Expands matching MCP groups while search or `/` command filtering is active. */
        expandMcpGroups?: boolean;
    }

    const {entries, onOpenDetail, expandMcpGroups = false}: Props = $props();

    let expandedGroups = $state<Record<string, boolean>>({});

    function isExpanded(id: string): boolean {
        return expandMcpGroups || expandedGroups[id] === true;
    }

    function setExpanded(id: string, expanded: boolean): void {
        expandedGroups[id] = expanded;
    }
</script>

{#snippet mcpGroup(serverGroup: McpEntryGroup, showSectionTitle: boolean)}
    {@const grouped = serverGroup.toggleEntries.length > 1}
    {@const expanded = grouped && isExpanded(serverGroup.id)}
    {#if grouped}
        {#if showSectionTitle}
            <ToolMenuGroupHeader
                id={`${serverGroup.id}-section`}
                label={serverGroup.label}
                description={serverGroup.description}/>
        {/if}
        <div class="t-acc tool-menu-mcp-group" data-open={expanded}>
            <ToolMenuGroupHeader
                id={serverGroup.id}
                label={serverGroup.label}
                description={serverGroup.description}
                entries={serverGroup.toggleEntries}
                onToggle={serverGroup.onToggle}
                {expanded}
                disclosureLocked={expandMcpGroups}
                onExpandedChange={(value) => setExpanded(serverGroup.id, value)}/>
            <div
                id={`tool-menu-mcp-panel-${serverGroup.id}`}
                class="t-acc-panel"
                aria-hidden={!expanded}
                inert={!expanded}>
                <div class="t-acc-panel-inner">
                    {#each serverGroup.entries as entry (entry.tool.name)}
                        <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail} inset/>
                    {/each}
                </div>
            </div>
        </div>
    {:else}
        <ToolMenuGroupHeader
            id={serverGroup.id}
            label={serverGroup.label}
            description={serverGroup.description}/>
        {#each serverGroup.entries as entry (entry.tool.name)}
            <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
        {/each}
    {/if}
{/snippet}

{#each Object.keys(entries) as groupKey (groupKey)}
    {#if groupKey === 'pinned' && (entries.pinned.length > 0 || entries.pinnedMcpTools.length > 0)}
        <DropdownMenuLabel>{__('chat.composer.favorite.label')}</DropdownMenuLabel>
        {#each entries.pinned as entry (entry.tool.name)}
            <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
        {/each}
        {#each entries.pinnedMcpTools as serverGroup (serverGroup.id)}
            {@render mcpGroup(serverGroup, false)}
        {/each}
    {:else if groupKey === 'pinnedMcpTools'}
        <!-- Rendered together with pinned tools under the single Favorites heading above. -->
    {:else if groupKey === 'capabilities' && entries.capabilities.length > 0}
        {#if entries.pinned.length > 0 || entries.pinnedMcpTools.length > 0}
            <DropdownMenuSeparator/>
        {/if}
        <DropdownMenuLabel>{__('chat.composer.toolMenu.capabilitiesLabel')}</DropdownMenuLabel>
        {#each entries.capabilities as entry (entry.tool.name)}
            <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
        {/each}
    {:else if groupKey === 'functionTools' && entries.functionTools.length > 0}
        {#if entries.pinned.length > 0 || entries.pinnedMcpTools.length > 0 || entries.capabilities.length > 0}
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
        {#if entries.functionTools.length > 0 || entries.capabilities.length > 0 || entries.pinned.length > 0 || entries.pinnedMcpTools.length > 0}
            <DropdownMenuSeparator/>
        {/if}
        {#each entries.mcpTools as serverGroup (serverGroup.id)}
            {@render mcpGroup(serverGroup, true)}
        {/each}
    {/if}
{/each}

<style>
    :global(:root) {
        --acc-expand: 250ms;
        --acc-collapse: 250ms;
        --acc-chevron: 250ms;
        --acc-ease: cubic-bezier(0.22, 1, 0.36, 1);
    }

    /* Keep the shared sliding highlight aligned with the nested MCP rows. */
    :global(.tool-menu-list) {
        --list-inset: var(--space-2);
    }

    /* grid-template-rows 0fr → 1fr gives a clean height animation
       with no JS measurement; the inner element clips overflow. */
    .t-acc-panel {
        display: grid;
        grid-template-rows: 0fr;
        transition: grid-template-rows var(--acc-collapse) var(--acc-ease);
    }
    .t-acc[data-open="true"] .t-acc-panel {
        grid-template-rows: 1fr;
        transition: grid-template-rows var(--acc-expand) var(--acc-ease);
    }
    .t-acc-panel-inner {
        overflow: hidden;
        min-height: 0;
        padding-left: var(--space-2);
        opacity: 0;
        filter: blur(2px);
        transition:
            opacity var(--acc-collapse) var(--acc-ease),
            filter var(--acc-collapse) var(--acc-ease);
    }
    .t-acc[data-open="true"] .t-acc-panel-inner {
        opacity: 1;
        filter: blur(0);
        transition:
            opacity var(--acc-expand) var(--acc-ease),
            filter var(--acc-expand) var(--acc-ease);
    }
    /* A collapsed disclosure points right, then rotates down as it opens. */
    :global(.t-acc-chevron) {
        display: inline-flex;
        transform: rotate(-90deg);
        transform-origin: center;
        transition: transform var(--acc-chevron) var(--acc-ease);
    }
    .t-acc[data-open="true"] :global(.t-acc-chevron) {
        transform: rotate(0deg);
    }

    @media (prefers-reduced-motion: reduce) {
        .t-acc-panel, .t-acc-panel-inner, :global(.t-acc-chevron) {
            transition: none !important;
        }
    }
</style>
