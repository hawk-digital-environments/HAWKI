<script lang="ts">
    import type {McpServer} from "$plugins/core/schemas/resources/mcp-servers.schema.js";
    import ToolsList from "$lib/plugins/assistants/components/assistantBuilderComponents/aiToolComponents/ToolsList.svelte";
    import type {AiToolOrCapability} from "$plugins/core/stores/aiToolStoreData.js";
    import ArrowRight01Icon from "$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte";
    import ServerStack01Icon from "$lib/components/ui/icons/iconset/ServerStack01Icon.svelte";

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

    let isOpen = $state(false);
</script>

<div class="mcp-server-wrapper">

    <button class="header"
            onclick={()=> isOpen = !isOpen}
    >

        <span class="icon-wrapper">
            <span class="icon"><ServerStack01Icon size="1em" /></span>
        </span>
        <span class="text-wrapper">
            <span class="label-row">
                <span class="label">{server.server_label}</span>
            </span>
            {#if server.description}
                <p class="description">{server.description}</p>
            {/if}
        </span>
        <span class="chevron" class:open={isOpen}>
            <ArrowRight01Icon size="1em" />
        </span>
    </button>


    <div class="details-wrapper"
         class:active={isOpen}
    >
        <div class="inner-wrapper">
            <div class="approvalTags">

                <ToolsList
                    tools={tools}
                    borderless={true}
                    {selectedIds}
                    {onchange}/>
            </div>


        </div>
    </div>


</div>


<style>
    .mcp-server-wrapper{
        position: relative;
        min-height: 3rem;
        border: var(--border);
        border-radius: var(--corner-md);
        padding: .75rem;
        margin-top: .5rem;
        background: var(--color-surface-raised);
    }
    .header{
        display: flex;
        flex-direction: row;
        gap: 1rem;
        align-items: center;
        width: 100%;
        cursor: pointer;
    }
    .label-row{
        display: flex;
        flex-direction: row;
        gap: .5rem;
        align-items: center;
    }

    .details-wrapper {
        display: grid;
        grid-template-rows: 0fr;
        overflow: hidden;
        width: 100%;
        margin-top: 0;
        transition: all var(--duration-medium);
    }
    .details-wrapper.active {
        grid-template-rows: 1fr;
        margin-top: .5rem;
    }

    .inner-wrapper{
        overflow: hidden;
    }


    .text-wrapper {
        flex: 1;
        text-align: left;
    }
    .chevron {
        display: inline-flex;
        align-items: center;
        transition: transform var(--duration-medium);
    }
    .chevron.open {
        transform: rotate(90deg);
    }
    .text-wrapper .label {
        margin-bottom: 0;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--color-accent-text);
        text-align: center;
    }
    .text-wrapper .description {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }


</style>
