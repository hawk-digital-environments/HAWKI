<script lang="ts">

    import {useStore} from "$lib/app/hooks/useStore.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import ToolsList from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolsList.svelte";
    import McpServerSelector from "$plugins/assistants/modules/builder/components/aiToolComponents/McpServerSelector.svelte";
    import ToolGroupCard from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolGroupCard.svelte";
    import ToolsIcon from "$lib/components/ui/icons/iconset/ToolsIcon.svelte";
    import {KNOWLEDGE_BASE_CAPABILITY, type AiToolOrCapability} from "$plugins/core/stores/aiToolStoreData.js";
    import type {McpServer} from "$plugins/core/schemas/resources/mcp-servers.schema.js";

    const builder = useBuilderContext();
    const toolStore = useStore('ai-tools');
    const modelStore = useStore('ai-models');
    const {__} = useTranslator();

    // Ids of the tools currently selected on the draft — drives initial toggle state.
    const selectedIds = $derived(
        new Set((builder.draft.aiTools ?? []).map(t => t.id))
    );

    // Chat-parity base filter (mirrors `ToolMenu`'s `filteredEntries`): a row
    // only exists when some model can use it — offline tools included, since
    // their server may come back.
    function availableOnSomeModel(tool: AiToolOrCapability): boolean {
        return modelStore.models.some(m => tool.isAvailableFor(m, true));
    }

    // Chat-parity ordering (`ToolMenu.groupedEntries`): rows alphabetically
    // by display name, MCP groups by server label.
    function sortByDisplayName(a: AiToolOrCapability, b: AiToolOrCapability): number {
        return a.displayName.localeCompare(b.displayName);
    }

    // The store returns a flat list (built-in tools, capabilities, and
    // MCP-backed tools together) — this page offers concrete tools only,
    // attached through the `aiTools` relationship: "HAWKI tools" (nothing
    // runs through an MCP server), then one group per MCP server. Capability
    // rows are chat-composer concerns, and knowledge-database tools are
    // excluded: the knowledge tool is granted ambiently by the RAG module
    // once files are attached.
    const nonMcpTools = $derived(toolStore.tools
        .filter(t =>
            !t.server && !t.is_capability && t.capability_key !== KNOWLEDGE_BASE_CAPABILITY
            && availableOnSomeModel(t)
        )
        .sort(sortByDisplayName)
    );
    const mcpGroups = $derived.by(() => {
        const groups = new Map<string, {server: McpServer; tools: AiToolOrCapability[]}>();
        for (const tool of toolStore.tools) {
            if (!tool.server || tool.is_capability || tool.capability_key === KNOWLEDGE_BASE_CAPABILITY) continue;
            if (!availableOnSomeModel(tool)) continue;
            const group = groups.get(tool.server.id) ?? {server: tool.server, tools: []};
            group.tools.push(tool);
            groups.set(tool.server.id, group);
        }
        return [...groups.values()]
            .map(group => ({...group, tools: [...group.tools].sort(sortByDisplayName)}))
            .sort((a, b) => a.server.server_label.localeCompare(b.server.server_label));
    });

    function onchange(tool: AiToolOrCapability, active: boolean) {
        const current = builder.draft.aiTools ?? [];
        const next = active
            ? (current.some(t => t.id === tool.id) ? current : [...current, tool])
            : current.filter(t => t.id !== tool.id);
        builder.set("aiTools", next);
    }

    // Header badges: selected entries of each group vs. the group's total.
    const selectedNonMcpToolCount = $derived(
        nonMcpTools.filter(t => selectedIds.has(t.id)).length
    );

</script>


<div class="tool-selector">
    {#if nonMcpTools.length > 0}
        <ToolGroupCard
            label={__('chat.composer.toolMenu.hawkiToolsLabel')}
            description={__('chat.composer.toolMenu.hawkiToolsDescription')}
            icon={ToolsIcon}
            selectedCount={selectedNonMcpToolCount}
            totalCount={nonMcpTools.length}
        >
            <ToolsList
                tools={nonMcpTools}
                borderless={true}
                {selectedIds}
                {onchange}
            />
        </ToolGroupCard>
    {/if}

    {#each mcpGroups as group (group.server.id)}
        <McpServerSelector
            server={group.server}
            tools={group.tools}
            {selectedIds}
            {onchange}
        />
    {/each}
</div>

<style>
    .tool-selector{
        display: flex;
        flex-direction: column;
    }
</style>
