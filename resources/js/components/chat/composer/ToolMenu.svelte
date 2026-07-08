<!--
  @component Popover that lists available AI tools the user can toggle.
  Shows a check indicator for active tools and labels unsupported tools without blocking selection.
-->
<script module lang="ts">
    import type {AiToolOrCapability} from '$lib/stores/aiToolStoreData.js';
    import type {AiToolOrCapabilityWithState} from '$lib/components/chat/composer/contexts/aspects/toolAspectData.js';

    export interface ToolMenuEntry {
        tool: AiToolOrCapability;
        onToggle: (active: boolean) => void;
        onCapabilitySet?: (data: {
            selection: AiToolOrCapabilityWithState['toolSelection'];
            settings: AiToolOrCapabilityWithState['toolSettings'];
        }) => void;
        disabled: boolean;
        active: boolean;
        available: boolean;
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
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {aiToolStore} from '$lib/stores/AiToolStore.svelte.js';
    import DropdownMenuDetailView from '$lib/components/ui/dropdown-menu/DropdownMenuDetailView.svelte';
    import ToolMenuList from '$lib/components/chat/composer/ToolMenuList.svelte';
    import ToolMenuDetail from '$lib/components/chat/composer/ToolMenuDetail.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import {setToolMenuFocusContext} from '$lib/components/chat/composer/contexts/ToolMenuFocusContext.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {aiModelStore} from '$lib/stores/AiModelStore.svelte.js';
    import PlusSignIcon from '$lib/components/ui/icons/iconset/PlusSignIcon.svelte';

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
        const models = aiModelStore.models;

        return aiToolStore.tools
            // Filter out all tools that are not available for ANY model, since they are not usable in any context.
            .filter(tool => models.some(model => tool.isAvailableFor(model, true)))
            .map(tool => {
                const entry: ToolMenuEntry = {
                    tool,
                    onToggle(active) {
                        if (active) {
                            composerContext.tools.enable(tool);
                        } else {
                            composerContext.tools.disable(tool);
                        }
                    },
                    onCapabilitySet({selection, settings}) {
                        if (!tool.is_capability || !tool.isAvailableFor(composerContext.model.current)) {
                            return;
                        }
                        composerContext.tools.set(tool, selection, settings);
                    },
                    disabled: tool.status === 'offline',
                    // To avoid rebuilding the whole array, we only update the active/supported state in the filteredEntries derived store.
                    active: false,
                    available: false
                };
                return entry;
            });
    });
    const filteredEntries = $derived.by(() => {
        return allEntries.map(entry => {
            entry.active = composerContext.tools.isActive(entry.tool);
            entry.available = entry.tool.isAvailableFor(composerContext.model.current);
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
            if (entry.tool.is_capability) {
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
            return tools.sort((a, b) => a.tool.displayName.localeCompare(b.tool.displayName));
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
        orderedEntries.find(entry => entry.available && !entry.disabled)
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

{#if composerContext.guard.showsAiUiElements && filteredEntries.length > 0}
    <div transition:growTransition={{mode: 'horizontal'}}>
        <DropdownMenu
            disabled={composerContext.guard.disablesFeature('tools')}
            bind:open
            contentProps={{class: 'tool-menu-content'}}>
            {#snippet trigger({props})}
                <ButtonWithTooltip
                    variant="ghost"
                    iconLeft={PlusSignIcon}
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
    </div>
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

        /*
         The padding collides with the inner view container (overflow: hidden),
         so the divider would be cut off. Instead, we let the inner view handle its own padding.
         */
        padding: 0;

        :global(.view) {
            padding: var(--space-1)
        }

        :global(.dropdown-title.dropdown-title) {
            padding-inline: var(--space-3);
            padding-bottom: 0;
        }
    }

    :global(.tool-menu-item svg) {
        flex-shrink: 0;
    }
</style>
