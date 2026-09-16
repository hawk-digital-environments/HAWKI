<!--
  @component MCP-server tool group on the builder's model page: all tools
  backed by one MCP server, wrapped in the shared {@link ToolGroupCard}
  chrome (collapsible card with a ":selected of :count selected" header
  badge that auto-opens when the group holds a preselected tool).

  ## Usage
  Rendered by `ToolSelector` once per server derived from the flat
  ai-tools store:
  ```svelte
  {#each mcpGroups as group (group.server.id)}
      <McpServerSelector server={group.server} tools={group.tools} {selectedIds} {onchange}/>
  {/each}
  ```
-->
<script lang="ts">
    import type {McpServer} from "$plugins/core/schemas/resources/mcp-servers.schema.js";
    import ToolsList from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolsList.svelte";
    import ToolGroupCard from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolGroupCard.svelte";
    import type {AiToolOrCapability} from "$plugins/core/stores/aiToolStoreData.js";
    import ServerStack01Icon from '$lib/components/ui/icons/iconset/ServerStack01Icon.svelte';

    let {
        server,
        tools,
        onchange,
        selectedIds = new Set<string>(),
    } = $props <{
        server: McpServer
        /** The tools backed by this MCP server — McpServer itself carries no
         *  tool list; ToolSelector derives it from the flat ai-tools store. */
        tools: AiToolOrCapability[];
        onchange: (aiTool: AiToolOrCapability, active: boolean) => void;
        selectedIds?: Set<string>;
    }>();

    const selectedInGroup = $derived(
        tools.filter((tool: AiToolOrCapability) => selectedIds.has(tool.id)).length
    );
</script>

<ToolGroupCard
    label={server.server_label}
    description={server.description}
    icon={ServerStack01Icon}
    selectedCount={selectedInGroup}
    totalCount={tools.length}
>
    <div class="approvalTags">
        <ToolsList
            tools={tools}
            borderless={true}
            {selectedIds}
            {onchange}/>
    </div>
</ToolGroupCard>
