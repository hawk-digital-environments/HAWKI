<script lang="ts">

    import {useStore} from "$lib/app/hooks/useStore.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";
    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import ToolsList from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolsList.svelte";
    import McpServerSelector from "$plugins/assistants/modules/builder/components/aiToolComponents/McpServerSelector.svelte";
    import CapabilitiesList from "$plugins/assistants/modules/builder/components/aiToolComponents/CapabilitiesList.svelte";
    import ToolGroupCard from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolGroupCard.svelte";
    import AiMagicIcon from "$lib/components/ui/icons/iconset/AiMagicIcon.svelte";
    import ToolsIcon from "$lib/components/ui/icons/iconset/ToolsIcon.svelte";
    import {createToolOrCapabilityWithState, createToolOrCapabilityWithStateFromTransferString} from "$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js";
    import {KNOWLEDGE_BASE_CAPABILITY, type AiToolOrCapability} from "$plugins/core/stores/aiToolStoreData.js";
    import type {McpServer} from "$plugins/core/schemas/resources/mcp-servers.schema.js";

    const builder = useBuilderContext();
    const toolStore = useStore('ai-tools');
    const modelStore = useStore('ai-models');
    const {__} = useTranslator();

    const currentModel = $derived(modelStore.getOneById(builder.draft.model));

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
    // MCP-backed tools together) — split it the way the chat's ToolMenu does:
    // capabilities, "HAWKI tools" (nothing runs through an MCP server), then
    // one group per MCP server. Knowledge-database tools are excluded: they
    // live on the builder's Knowledge page, which attaches them through the
    // same `aiTools` relationship.
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

    // `capabilities` holds capability transfer strings
    // (`capability:<key>:<native|auto|<tool-name>>`); parse them back into a
    // capability id → mode map for the UI. Strings whose capability no longer
    // exists are kept untouched (they survive registry changes — same
    // no-pruning semantics as `aiTools` on model switches).
    const selectedCapabilities = $derived.by(() => {
        const map = new Map<string, string>();
        for (const transfer of builder.draft.capabilities ?? []) {
            const parts = transfer.split(':');
            if (parts[0] !== 'capability' || !parts[1]) continue;
            map.set(parts[1], parts[2] ?? 'auto');
        }
        return map;
    });

    // Session memory of every capability the user selected at some point —
    // populated from the loaded draft and every later enable. Deselecting or
    // the prune effect below can shrink `draft.capabilities`, but a
    // once-selected capability keeps its row (disabled) instead of
    // disappearing, until the builder session ends.
    let everSelectedCapabilities = $state<Set<string>>(new Set());

    $effect(() => {
        const selected = [...selectedCapabilities.keys()];
        if (selected.every(id => everSelectedCapabilities.has(id))) return;
        everSelectedCapabilities = new Set([...everSelectedCapabilities, ...selected]);
    });

    // Selections the current model can't fulfil (mode-aware: `native` needs
    // the model's native capability, `auto` needs native or an assigned tool,
    // a concrete tool needs that tool) never reach the save: they're pruned
    // from the draft here, and `builder.set` schedules the autosave with the
    // pruned list. Unparseable/stale strings can't be evaluated and are kept;
    // with no model selected nothing is pruned.
    $effect(() => {
        const model = currentModel;
        if (!model) return;

        const current = builder.draft.capabilities ?? [];
        const kept = current.filter(transfer => {
            const state = createToolOrCapabilityWithStateFromTransferString(transfer, toolStore);
            return state === null || state.isAvailableFor(model);
        });

        if (kept.length !== current.length) {
            builder.set('capabilities', kept);
        }
    });

    // Capabilities (knowledge base excluded — its tools are managed on the
    // Knowledge page), grouped like the chat's ToolMenu: the chat's any-model
    // availability filter as the base, plus the builder's own rule that a
    // capability the currently selected model can't fulfil is hidden — unless
    // the user had selected it earlier: those stay visible (disabled, with a
    // warning dot) so they don't silently vanish under the user's cursor.
    const capabilityEntries = $derived(toolStore.tools
        .filter(t =>
            t.is_capability
            && t.id !== KNOWLEDGE_BASE_CAPABILITY
            && availableOnSomeModel(t)
            && (everSelectedCapabilities.has(t.id) || !currentModel || t.isAvailableFor(currentModel))
        )
        .sort(sortByDisplayName)
    );

    function capabilitiesWithout(capabilityId: string): string[] {
        return (builder.draft.capabilities ?? [])
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

    function onCapabilityChange(capability: AiToolOrCapability, active: boolean) {
        const without = capabilitiesWithout(capability.id);
        const transfer = active ? transferStringFor(capability, 'auto') : null;
        builder.set('capabilities', transfer ? [...without, transfer] : without);
    }

    function onCapabilityModeChange(capability: AiToolOrCapability, mode: string) {
        const transfer = transferStringFor(capability, mode);
        if (transfer === null) return;
        builder.set('capabilities', [...capabilitiesWithout(capability.id), transfer]);
    }

    function onchange(tool: AiToolOrCapability, active: boolean) {
        const current = builder.draft.aiTools ?? [];
        const next = active
            ? (current.some(t => t.id === tool.id) ? current : [...current, tool])
            : current.filter(t => t.id !== tool.id);
        builder.set("aiTools", next);
    }

    // Header badges: selected entries of each group vs. the group's total.
    const selectedCapabilityCount = $derived(
        capabilityEntries.filter(c => selectedCapabilities.has(c.id)).length
    );
    const selectedNonMcpToolCount = $derived(
        nonMcpTools.filter(t => selectedIds.has(t.id)).length
    );

</script>


<div class="tool-selector">
    {#if capabilityEntries.length > 0}
        <ToolGroupCard
            label={__('chat.composer.toolMenu.capabilitiesLabel')}
            icon={AiMagicIcon}
            selectedCount={selectedCapabilityCount}
            totalCount={capabilityEntries.length}
        >
            <CapabilitiesList
                capabilities={capabilityEntries}
                selected={selectedCapabilities}
                model={currentModel}
                onchange={onCapabilityChange}
                onModeChange={onCapabilityModeChange}
            />
        </ToolGroupCard>
    {/if}

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
        gap: .5rem;
    }
</style>
