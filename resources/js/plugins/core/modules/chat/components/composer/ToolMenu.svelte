<!--
  @component Trigger button + popover for the composer's AI-tool picker.

  Reads every tool/capability from the `ai-tools` store, filters out anything not
  available for *any* model, and derives per-entry `active`/`available` flags from
  `composerContext.tools` (active) and the currently selected `composerContext.model.current`
  (available). Rows are grouped into capabilities, HAWKI function tools, and MCP-server
  groups (rendered by `ToolMenuList`); clicking a row toggles the tool via
  `composerContext.tools.enable/disable`, and its info icon opens `ToolMenuDetail` in place
  of the list (a two-panel `DropdownMenuDetailView`) for capability configuration.

  Renders nothing when `composerContext.guard.showsAiUiElements` is false (e.g. room chat
  without an `@handle` yet) or when there are no eligible tools at all.

  ## Usage
  Rendered once by `ChatComposer.svelte` in the bottom-left control row, next to `FilePicker`
  and `ToolChips`. `open` is bound so the tool-chip overflow badge ("+N") can open the same
  popover externally:
  ```svelte
  let toolPickerOpen = $state(false);
  <ToolMenu bind:open={toolPickerOpen}/>
  <ToolChips onShowMore={() => (toolPickerOpen = true)}/>
  ```

  The `ToolMenuEntry`/`ToolMenuGroupedEntries` types declared below (module script) are the
  shared contract consumed by `ToolMenuList`, `ToolMenuListItem`, `ToolMenuDetail`, and
  `ToolMenuConfig` — they all operate on entries built here, never on the raw store tool.
-->
<script module lang="ts">
    import type {AiToolOrCapabilityWithState} from '$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
    import type {AiToolOrCapability} from '$plugins/core/stores/aiToolStoreData.js';

    /** A single tool/capability row, pre-wired with the callbacks and derived flags
     *  the menu/detail/config components need — built once per tool in `ToolMenu`'s
     *  `allEntries`/`filteredEntries` derivations. */
    export interface ToolMenuEntry {
        /** The underlying tool or capability (from the `ai-tools` store) this entry wraps. */
        tool: AiToolOrCapability;
        /** Enables/disables the tool on `composerContext.tools`. Wired to the row's checkbox. */
        onToggle: (active: boolean) => void;
        /** For capabilities only: commits a tool-selection/settings choice made in
         *  `ToolMenuConfig` back to `composerContext.tools.set()`. Undefined for plain tools. */
        onCapabilitySet?: (data: {
            selection: AiToolOrCapabilityWithState['toolSelection'];
            settings: AiToolOrCapabilityWithState['toolSettings'];
        }) => void;
        /** `true` when the tool is offline server-side; the row is shown but not toggleable. */
        disabled: boolean;
        /** `true` when the tool is currently enabled in `composerContext.tools`. */
        active: boolean;
        /** `true` when `tool.isAvailableFor(composerContext.model.current)` — i.e. the
         *  currently selected model supports this tool. `false` shows a warning indicator
         *  without preventing selection. */
        available: boolean;
    }

    /** One MCP server's tools, grouped for display under a shared `ToolMenuGroupHeader`. */
    interface McpEntryGroup {
        /** The MCP server's id, used as the group's React/Svelte key and header id. */
        id: string;
        /** The MCP server's display label (`server.server_label`). */
        label: string;
        /** The MCP server's description, shown via an `InfoPopover` next to the label; `null` if none. */
        description: string | null;
        /** This server's tools, alphabetically sorted by display name. */
        entries: ToolMenuEntry[];
    }

    /** Entries partitioned into the three sections `ToolMenuList` renders, in display order. */
    export interface ToolMenuGroupedEntries {
        /** Capability entries (e.g. web search, image generation) shown first, ungrouped. */
        capabilities: ToolMenuEntry[];
        /** Plain HAWKI function tools (not backed by an MCP server), shown under one shared header. */
        functionTools: ToolMenuEntry[];
        /** Tools backed by an MCP server, one `McpEntryGroup` per server, sorted by server label. */
        mcpTools: McpEntryGroup[];
    }
</script>
<script lang="ts">
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import DropdownMenuDetailView from '$lib/components/ui/dropdown-menu/DropdownMenuDetailView.svelte';
    import ToolMenuList from '$plugins/core/modules/chat/components/composer/ToolMenuList.svelte';
    import ToolMenuDetail from '$plugins/core/modules/chat/components/composer/ToolMenuDetail.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import {setToolMenuFocusContext} from '$plugins/core/modules/chat/components/composer/contexts/ToolMenuFocusContext.svelte.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import PlusSignIcon from '$lib/components/ui/icons/iconset/PlusSignIcon.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const focusContext = setToolMenuFocusContext();
    const aiToolStore = useStore('ai-tools');
    const aiModelStore = useStore('ai-models');
    const {__} = useTranslator();

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
