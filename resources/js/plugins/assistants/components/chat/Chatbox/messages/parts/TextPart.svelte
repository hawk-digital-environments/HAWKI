<script lang="ts">
    import {renderMarkdown} from "../../renderMarkdown";

    let {text} = $props<{ text: string }>();

    let html = $state("");

    $effect(() => {
        let cancelled = false;
        renderMarkdown(text).then((out) => {
            if (!cancelled) html = out;
        });
        return () => {
            cancelled = true;
        };
    });
</script>

<div class="text-part">{@html html}</div>

<style>
    .text-part {
        font-size: var(--font-sm);
        line-height: 1.5;
        color: var(--text-color);
        word-break: break-word;
    }

    .text-part :global(p) {
        margin: .25rem 0;
    }

    .text-part :global(pre) {
        padding: .75rem 1rem;
        margin: .5rem 0;
        border-radius: var(--border-radius-normal);
        overflow-x: auto;
    }

    .text-part :global(code) {
        font-family: "Fira Mono", ui-monospace, monospace;
        font-size: var(--font-xs);
    }

    .text-part :global(ul),
    .text-part :global(ol) {
        margin: .25rem 0;
        padding-left: 1.25rem;
    }
</style>
