<script lang="ts">

    import Wrench01Icon from '$lib/components/ui/icons/iconset/Wrench01Icon.svelte';
    let {toolName, input}: { toolName: string; input: unknown } = $props();

    let hasArgs = $derived(
        input !== undefined && input !== null && typeof input === "object" && Object.keys(input as object).length > 0,
    );
</script>

<div class="tool-call">
    <span class="icon"><Wrench01Icon size="1em" /></span>
    <span class="name">{toolName}</span>
    {#if hasArgs}
        <code class="args">{JSON.stringify(input)}</code>
    {/if}
</div>

<style>
    .tool-call {
        display: flex;
        align-items: center;
        gap: .35rem;
        flex-wrap: wrap;
        font-size: var(--font-xs);
        padding: .15rem 0;
        color: var(--accent-color);
    }
    .icon {
        font-size: var(--font-md);
    }
    .name {
        font-weight: 600;
    }
    .args {
        font-family: "Fira Mono", ui-monospace, monospace;
        background: var(--background-secondary);
        padding: .1rem .35rem;
        border-radius: var(--border-radius-tight);
    }
</style>
