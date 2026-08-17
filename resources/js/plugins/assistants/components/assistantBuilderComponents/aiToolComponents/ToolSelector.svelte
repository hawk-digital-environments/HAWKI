<script lang="ts">

    import {aiToolsStore} from "$lib/stores/AiToolsStore.svelte.js";
    import {assistantBuilderStore} from "$lib/stores/assistants/AssistantBuilderStore.svelte.js";
    import ToolsList from "$lib/components/assistant/assistantBuilderComponents/aiToolComponents/ToolsList.svelte";
    import McpServerSelector from "$lib/components/assistant/assistantBuilderComponents/aiToolComponents/McpServerSelector.svelte";
    import type {AiTool} from "$lib/types/aiTools/AiTool";

    // Ids of the tools currently selected on the draft — drives initial toggle state.
    const selectedIds = $derived(
        new Set((assistantBuilderStore.draft.aiTools ?? []).map(t => t.id))
    );

    function onchange(tool: AiTool, active: boolean) {
        const current = assistantBuilderStore.draft.aiTools ?? [];
        const next = active
            ? (current.some(t => t.id === tool.id) ? current : [...current, tool])
            : current.filter(t => t.id !== tool.id);
        assistantBuilderStore.set("aiTools", next);
    }

</script>


<div class="tool-selector">
    {#if aiToolsStore.nonMcpTools.length > 0}
        <ToolsList
            tools={aiToolsStore.nonMcpTools}
            {selectedIds}
            {onchange}
        />
    {/if}

    {#each aiToolsStore.mcpServers as server}
        <McpServerSelector
            {server}
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
