<script lang="ts">
    import Markdown from "$lib/components/util/markdown/Markdown.svelte";

    let {text, streaming = false}: { text: string; streaming?: boolean } = $props();
</script>

<div class="text-part">
    <Markdown message={text} isStreaming={streaming} />
</div>

<style>
    .text-part {
        font-size: var(--font-size-sm);
        line-height: 1.5;
        color: var(--color-text);
        word-break: break-word;
    }
</style>
