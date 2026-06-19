<!--
  @component Popover that lists available AI tools the user can toggle.
  Shows a check indicator for active tools and labels unsupported tools without blocking selection.
-->
<script module lang="ts">
    import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';

    export interface ToolMenuEntry {
        name: string;
        isCapability: boolean;
        description: string | null;
        iconPath?: string;
        tool: AiTool;
        onChange: (active: boolean) => void;
        disabled: boolean;
        active: boolean;
        supported: boolean;
    }

    interface McpEntryGroup {
        id: string;
        label: string;
        description: string | null;
        entries: ToolMenuEntry[];
    }

    export interface ToolMenuGroupedEntries {
        capabilities: ToolMenuEntry[];
        functionTools: ToolMenuEntry[];
        mcpTools: McpEntryGroup[];
    }
</script>
<script lang="ts">
    import {Plus} from '@lucide/svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {aiToolStore} from '$lib/stores/AiToolStore.svelte.js';
    import {toolDisplayDescription, toolDisplayName} from '$lib/utils/aiToolUtils.js';
    import DropdownMenuDetailView from '$lib/components/ui/dropdown-menu/DropdownMenuDetailView.svelte';
    import ToolMenuList from '$lib/components/chat/composer/ToolMenuList.svelte';
    import ToolMenuDetail from '$lib/components/chat/composer/ToolMenuDetail.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import {setToolMenuFocusContext} from '$lib/components/chat/composer/contexts/ToolMenuFocusContext.svelte.js';
    import {__} from '$lib/utils/translator.js';

    const composerContext = useComposerContext();
    const focusContext = setToolMenuFocusContext();

    interface Props {
        /** Whether the tool picker is open. Supports bind:open. */
        open?: boolean;
    }

    // Drives both the desktop dropdown and the mobile bottom sheet so the
    // picker can be opened externally (e.g. from the tool-chip overflow badge).
    let {open = $bindable(false)}: Props = $props();

    // When set, the picker shows the detail view for this tool instead of the list.
    let detailToolName = $state<string | null>(null);

    const allEntries = $derived.by(() => {
        return aiToolStore.tools.map(tool => {
            const entry: ToolMenuEntry = {
                isCapability: aiToolStore.getCapabilityForTool(tool) !== null,
                name: toolDisplayName(tool),
                description: toolDisplayDescription(tool),
                tool,
                onChange: (active) => {
                    if (active) {
                        composerContext.tools.add(tool);
                    } else {
                        composerContext.tools.remove(tool);
                    }
                },
                disabled: tool.status === 'offline',
                // To avoid rebuilding the whole array, we only update the active/supported state in the filteredEntries derived store.
                active: false,
                supported: false
            };
            return entry;
        });
    });
    const filteredEntries = $derived.by(() => {
        return allEntries.map(entry => {
            entry.active = composerContext.tools.active.some(activeTool => activeTool.name === entry.tool.name);
            entry.supported = composerContext.tools.canUse(entry.tool);
            return entry;
        });
    });

    // The live entry shown in the detail view, kept in sync with filteredEntries
    // so its active/supported state updates while the detail view is open.
    const detailEntry = $derived.by(() => {
        const e = detailToolName ? filteredEntries.find(entry => entry.tool.name === detailToolName) ?? null : null;
        if (!e) {
            return e;
        }
        return {...e};
    });

    const groupedEntries = $derived.by(() => {
        // First capabilities
        // Next all "function tools" -> Not on a mcp server
        // Then all "mcp tools" -> Only on mcp server
        const capabilities: ToolMenuEntry[] = [];
        const functionTools: ToolMenuEntry[] = [];
        const mcpTools: Record<string, ToolMenuEntry[]> = {};

        for (const entry of filteredEntries) {
            if (entry.isCapability) {
                capabilities.push(entry);
            } else if (!entry.tool.server) {
                functionTools.push(entry);
            } else if (entry.tool.server) {
                const serverId = entry.tool.server.id + '';
                if (!mcpTools[serverId]) {
                    mcpTools[serverId] = [];
                }
                mcpTools[serverId].push(entry);
            }
        }

        const sortEntriesAlphabetically = (tools: ToolMenuEntry[]) => {
            return tools.sort((a, b) => a.name.localeCompare(b.name));
        };

        const mcpToolsSorted: Array<McpEntryGroup> =
            Object.entries(mcpTools).map(function (
                [serverId, entries]
            ) {
                const serverName = entries[0].tool.server!.server_label;
                const serverDescription = entries[0].tool.server!.description || null;
                return {
                    id: serverId,
                    label: serverName,
                    description: serverDescription,
                    entries: sortEntriesAlphabetically(entries)
                };
            }).sort(
                (a, b) => a.label.localeCompare(b.label)
            )
        ;

        return {
            capabilities: sortEntriesAlphabetically(capabilities),
            functionTools: sortEntriesAlphabetically(functionTools),
            mcpTools: mcpToolsSorted
        };
    });

    // Entries flattened in render order, and the first usable one to land focus
    // on when the menu opens (prefer a supported, online tool; fall back so we
    // always have a sensible target).
    const orderedEntries = $derived([
        ...groupedEntries.capabilities,
        ...groupedEntries.functionTools,
        ...groupedEntries.mcpTools.flatMap(group => group.entries)
    ]);
    const firstAvailableEntry = $derived(
        orderedEntries.find(entry => entry.supported && !entry.disabled)
        ?? orderedEntries.find(entry => !entry.disabled)
        ?? orderedEntries[0]
        ?? null
    );

    function closeToolDetail() {
        const name = detailToolName;
        detailToolName = null;
        if (!name) {
            return;
        }
        // Return focus to the row that opened the detail once the list re-renders.
        requestAnimationFrame(() => focusContext.focusByKey(name));
    }

    function openToolDetail(entry: ToolMenuEntry) {
        detailToolName = entry.tool.name;
    }

    // When closing the detail view, we keep it open for a short delay to avoid flickering.
    $effect(() => {
        if (!open && detailToolName) {
            const t = setTimeout(() => {
                detailToolName = null;
            }, 200);
            return () => clearTimeout(t);
        }
    });

    // On open, land focus on the first available tool so keyboard users start on
    // a usable row rather than bits-ui's first candidate.
    let wasOpen = false;
    $effect(() => {
        const isOpen = open;
        if (isOpen && !wasOpen) {
            requestAnimationFrame(() => {
                if (!open || detailToolName) {
                    return;
                }
                const name = firstAvailableEntry?.tool.name;
                if (name) {
                    focusContext.focusByKey(name);
                }
            });
        }
        wasOpen = isOpen;
    });
</script>

{#if filteredEntries.length > 0}
    <DropdownMenu
        title={detailEntry ? undefined : __('chat.composer.toolMenu.manageTools')}
        disabled={composerContext.guard.disablesFeature('tools')}
        bind:open
        contentProps={{class: 'tool-menu-content'}}>
        {#snippet trigger({props})}
            <ButtonWithTooltip
                variant="ghost"
                iconLeft={Plus}
                tooltip={__('chat.composer.toolMenu.manageTools')}
                highlight={props['data-state']}
                {...props}/>
        {/snippet}
        <DropdownMenuDetailView
            open={!!detailEntry}
        >
            {#snippet details()}
                {#if detailEntry}
                    <ToolMenuDetail entry={detailEntry} onCloseDetail={closeToolDetail}/>
                {/if}
            {/snippet}
            <ToolMenuList entries={groupedEntries} onOpenDetail={openToolDetail}/>
        </DropdownMenuDetailView>
    </DropdownMenu>
{/if}

<style>
    /*
      Fix the popover to a single width and keep scrolling inside the animated
      picker viewport, so the dropdown chrome/title stays anchored.
    */
    :global(.dropdown-content.dropdown-content--dropdown.tool-menu-content) {
        width: calc(0.25rem * 72);
        max-width: calc(100vw - var(--space-8, calc(0.25rem * 8)));
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    :global(.tool-menu-item svg) {
        flex-shrink: 0;
    }
</style>
