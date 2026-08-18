<script lang="ts">

    import {useStore} from "$lib/app/hooks/useStore.svelte.js";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import ToolsList from "$lib/plugins/assistants/components/assistantBuilderComponents/aiToolComponents/ToolsList.svelte";
    import McpServerSelector from "$lib/plugins/assistants/components/assistantBuilderComponents/aiToolComponents/McpServerSelector.svelte";
    import type {AiToolOrCapability} from "$plugins/core/stores/aiToolStoreData.js";
    import type {McpServer} from "$plugins/core/schemas/resources/mcp-servers.schema.js";

    const builder = useBuilderContext();
    const toolStore = useStore('ai-tools');

    // Ids of the tools currently selected on the draft — drives initial toggle state.
    const selectedIds = $derived(
        new Set((builder.draft.aiTools ?? []).map(t => t.id))
    );

    // The store returns a flat list (built-in tools, capabilities, and
    // MCP-backed tools together) — split it the way the UI wants it: plain
    // top-level entries (nothing runs through an MCP server) vs. entries
    // grouped under the server that backs them. `McpServer` itself carries no
    // tool list, so the grouping happens here rather than on the store.
    // Capabilities (`is_capability`) are excluded: they're a synthetic
    // grouping over several real tools, not a concrete `ai-tools` record, so
    // their `id` isn't something the assistant's `aiTools` relationship can
    // reference — only the individual tools underneath them can.
    const nonMcpTools = $derived(toolStore.tools.filter(t => !t.server && !t.is_capability));
    const mcpGroups = $derived.by(() => {
        const groups = new Map<string, {server: McpServer; tools: AiToolOrCapability[]}>();
        for (const tool of toolStore.tools) {
            if (!tool.server || tool.is_capability) continue;
            const group = groups.get(tool.server.id) ?? {server: tool.server, tools: []};
            group.tools.push(tool);
            groups.set(tool.server.id, group);
        }
        return [...groups.values()];
    });

    function onchange(tool: AiToolOrCapability, active: boolean) {
        const current = builder.draft.aiTools ?? [];
        const next = active
            ? (current.some(t => t.id === tool.id) ? current : [...current, tool])
            : current.filter(t => t.id !== tool.id);
        builder.set("aiTools", next);
    }

</script>


<div class="tool-selector">
    {#if nonMcpTools.length > 0}
        <ToolsList
            tools={nonMcpTools}
            {selectedIds}
            {onchange}
        />
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
        gap: .5rem;
    }
</style>
