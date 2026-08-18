<script lang="ts">
    import type {AiToolOrCapability} from "$plugins/core/stores/aiToolStoreData.js";
    import RadioSwitch from "$lib/plugins/assistants/components/radioSwitch/RadioSwitch.svelte";
    import RadioOption from "$lib/plugins/assistants/components/radioSwitch/RadioOption.svelte";

    let {
        tools,
        onchange,
        borderless = false,
        selectedIds = new Set<string>(),
    } = $props<{
        tools: AiToolOrCapability[];
        onchange: (aiTool: AiToolOrCapability, active: boolean) => void;
        /** Borderless rows (nested inside an MCP card) vs. standalone cards. */
        borderless?: boolean;
        /** Ids of tools already selected on the assistant, for initial state. */
        selectedIds?: Set<string>;
    }>();

    // RadioSwitch works with string values; map ids <-> strings.
    const byId = $derived(new Map(tools.map((t: AiToolOrCapability) => [t.id, t])));
    const selected = $derived(
        tools.filter((t: AiToolOrCapability) => selectedIds.has(t.id)).map((t: AiToolOrCapability) => t.id)
    );

    function handleChange(value: string, active: boolean) {
        const tool = byId.get(value);
        if (tool) onchange(tool, active);
    }
</script>

<div class="tool-list">
    <RadioSwitch multiple value={selected} onchange={handleChange}>
        {#each tools as tool}
            <RadioOption
                value={tool.id}
                label={tool.displayName}
                description={tool.description}
            />
        {/each}
    </RadioSwitch>
</div>

<style>
    .tool-list{
        display: flex;
        flex-direction: column;
    }
</style>
