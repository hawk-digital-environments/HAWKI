<script lang="ts">

    import {useStore} from "$lib/app/hooks/useStore.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import ToolsList from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolsList.svelte";
    import McpServerSelector from "$plugins/assistants/modules/builder/components/aiToolComponents/McpServerSelector.svelte";
    import ProviderToolsList from "$plugins/assistants/modules/builder/components/aiToolComponents/ProviderToolsList.svelte";
    import {createToolOrCapabilityWithState} from "$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js";
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

    // The store returns a flat list (built-in tools, capabilities, and
    // MCP-backed tools together) — split it the way the UI wants it: plain
    // top-level entries (nothing runs through an MCP server) vs. entries
    // grouped under the server that backs them. `McpServer` itself carries no
    // tool list, so the grouping happens here rather than on the store.
    // Knowledge-database tools are excluded: they live on the builder's
    // Knowledge page, which attaches them through the same `aiTools`
    // relationship.
    const nonMcpTools = $derived(toolStore.tools.filter(t =>
        !t.server && !t.is_capability && t.capability_key !== KNOWLEDGE_BASE_CAPABILITY
    ));
    const mcpGroups = $derived.by(() => {
        const groups = new Map<string, {server: McpServer; tools: AiToolOrCapability[]}>();
        for (const tool of toolStore.tools) {
            if (!tool.server || tool.is_capability || tool.capability_key === KNOWLEDGE_BASE_CAPABILITY) continue;
            const group = groups.get(tool.server.id) ?? {server: tool.server, tools: []};
            group.tools.push(tool);
            groups.set(tool.server.id, group);
        }
        return [...groups.values()];
    });

    // Provider tools are the capability wrappers (knowledge base excluded —
    // its tools are managed on the Knowledge page). Capabilities that no model
    // can fulfill (neither natively nor through a mapped tool) are dead weight
    // and hidden.
    const providerToolCapabilities = $derived(toolStore.tools.filter(t =>
        t.is_capability
        && t.id !== KNOWLEDGE_BASE_CAPABILITY
        && (modelStore.models.some(m => t.hasNativeCapabilityFor(m)) || t.getTools().length > 0)
    ));

    // `providerTools` holds capability transfer strings
    // (`capability:<key>:<native|auto|<tool-name>>`); parse them back into a
    // capability id → mode map for the UI. Strings whose capability no longer
    // exists are kept untouched (they survive registry changes — same
    // no-pruning semantics as `aiTools` on model switches).
    const selectedProviderTools = $derived.by(() => {
        const map = new Map<string, string>();
        for (const transfer of builder.draft.providerTools ?? []) {
            const parts = transfer.split(':');
            if (parts[0] !== 'capability' || !parts[1]) continue;
            map.set(parts[1], parts[2] ?? 'auto');
        }
        return map;
    });

    const currentModel = $derived(modelStore.getOneById(builder.draft.model));

    function providerToolsWithout(capabilityId: string): string[] {
        return (builder.draft.providerTools ?? [])
            .filter(t => !t.startsWith(`capability:${capabilityId}:`));
    }

    // Builds the transfer string for a capability selection through the
    // composer's serializer, so the wire format has one source of truth.
    function transferStringFor(capability: AiToolOrCapability, mode: string): string | null {
        if (!capability.is_capability) return null;
        if (mode === 'auto' || mode === 'native') {
            return createToolOrCapabilityWithState(capability, mode).toTransferString();
        }
        const tool = capability.getTools().find(t => t.name === mode);
        return tool ? createToolOrCapabilityWithState(capability, tool).toTransferString() : null;
    }

    function onProviderToolChange(capability: AiToolOrCapability, active: boolean) {
        const without = providerToolsWithout(capability.id);
        const transfer = active ? transferStringFor(capability, 'auto') : null;
        builder.set('providerTools', transfer ? [...without, transfer] : without);
    }

    function onProviderToolModeChange(capability: AiToolOrCapability, mode: string) {
        const transfer = transferStringFor(capability, mode);
        if (transfer === null) return;
        builder.set('providerTools', [...providerToolsWithout(capability.id), transfer]);
    }

    function onchange(tool: AiToolOrCapability, active: boolean) {
        const current = builder.draft.aiTools ?? [];
        const next = active
            ? (current.some(t => t.id === tool.id) ? current : [...current, tool])
            : current.filter(t => t.id !== tool.id);
        builder.set("aiTools", next);
    }

</script>


<div class="tool-selector">
    {#if providerToolCapabilities.length > 0}
        <div class="provider-tools-section">
            <p class="section-title">{__('assistants.builder.tools.providerTools.title')}</p>
            <p class="section-description">{__('assistants.builder.tools.providerTools.description')}</p>
            <ProviderToolsList
                capabilities={providerToolCapabilities}
                selected={selectedProviderTools}
                model={currentModel}
                onchange={onProviderToolChange}
                onModeChange={onProviderToolModeChange}
            />
        </div>
    {/if}

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

    .provider-tools-section{
        display: flex;
        flex-direction: column;
        gap: .25rem;
    }

    .section-title{
        margin: 0;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }

    .section-description{
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
</style>
