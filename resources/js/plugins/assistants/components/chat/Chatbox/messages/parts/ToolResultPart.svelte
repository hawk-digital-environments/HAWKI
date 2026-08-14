<script lang="ts">

    import CheckmarkCircle02Icon from '$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte';
    let {toolName, output}: { toolName: string; output: unknown } = $props();

    let text = $derived(typeof output === "string" ? output : JSON.stringify(output));
</script>

<div class="tool-result">
    <span class="icon"><CheckmarkCircle02Icon size="1em" /></span>
    <span class="name">{toolName}</span>
    <code class="output">{text}</code>
</div>

<style>
    .tool-result {
        display: flex;
        align-items: flex-start;
        gap: .35rem;
        flex-wrap: wrap;
        font-size: var(--font-xs);
        padding: .15rem 0 .15rem 1.25rem;
        color: var(--text-faded-color);
    }
    .icon {
        font-size: var(--font-md);
        color: #16a34a;
    }
    .name {
        font-weight: 600;
        color: var(--text-color);
    }
    .output {
        font-family: "Fira Mono", ui-monospace, monospace;
        background: var(--background-secondary);
        padding: .1rem .35rem;
        border-radius: var(--border-radius-tight);
        word-break: break-word;
        white-space: pre-wrap;
    }
</style>
