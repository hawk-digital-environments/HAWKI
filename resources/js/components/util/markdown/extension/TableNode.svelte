<!--
  @component markstream-svelte custom component for `table` nodes. Renders the
  same HTML as the built-in TableNode (which has no component-level hooks for
  cells) and marks every header cell with `scope="col"`, so screen readers can
  relate body cells to their column headers. Markdown tables only ever have a
  single header row, so every `<th>` the renderer emits is a column header.
-->
<script lang="ts">
    import type {SvelteRenderableNode, SvelteRenderContext} from 'markstream-svelte';
    import {renderMarkdownNodeToHtml} from 'markstream-svelte';

    interface Props {
        /** The parsed table node. */
        node: SvelteRenderableNode;
        /** Render context; supplies the HTML policy the built-in renderer uses. */
        context?: SvelteRenderContext;
    }

    const {node, context}: Props = $props();

    const html = $derived(
        renderMarkdownNodeToHtml(node, {
            cacheKey: context?.customId ? `markstream-svelte-${context.customId}` : 'markstream-svelte-node',
            customHtmlTags: context?.customHtmlTags,
            allowHtml: context?.allowHtml !== false,
            htmlPolicy: context?.htmlPolicy ?? 'safe'
        }).replace(/<th(?=[\s>])/g, '<th scope="col"')
    );
</script>

{@html html}
