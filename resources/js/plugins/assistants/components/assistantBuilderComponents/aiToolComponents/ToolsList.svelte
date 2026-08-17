<script lang="ts">
    import type {AiTool} from "$lib/types/aiTools/AiTool";
    import RadioSwitch from "$lib/components/generic/radioSwitch/RadioSwitch.svelte";
    import RadioOption from "$lib/components/generic/radioSwitch/RadioOption.svelte";

    let {
        tools,
        onchange,
        borderless = false,
        selectedIds = new Set<number>(),
    } = $props<{
        tools: AiTool[];
        onchange: (aiTool: AiTool, active: boolean) => void;
        /** Borderless rows (nested inside an MCP card) vs. standalone cards. */
        borderless?: boolean;
        /** Ids of tools already selected on the assistant, for initial state. */
        selectedIds?: Set<number>;
    }>();

    // RadioSwitch works with string values; map ids <-> strings.
    const byId = $derived(new Map(tools.map((t: AiTool) => [String(t.id), t])));
    const selected = $derived(
        tools.filter((t: AiTool) => selectedIds.has(t.id)).map((t: AiTool) => String(t.id))
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
                value={String(tool.id)}
                label={tool.name}
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
