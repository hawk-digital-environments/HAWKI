<!--
  @component markstream-svelte custom component for `heading` nodes. Same
  output as the built-in HeadingNode, but the level is shifted by the
  `headingBaseLevel` the surrounding `Markdown` component provides through
  context: `#` renders as `h{base}`, `##` as `h{base + 1}`, … clamped to h6.
  Keeps model-written headings below the page's own outline (chat title,
  message history, per-message author headings) instead of producing stray
  h1/h2 elements in the middle of the log.
-->
<script lang="ts">
    import type {SvelteRenderableNode, SvelteRenderContext} from 'markstream-svelte';
    import {RenderChildren} from 'markstream-svelte';
    import {useMarkdownHeadingBaseLevel} from '$lib/components/util/markdown/extension/headingBaseLevel.js';

    interface Props {
        /** The parsed heading node (level, children). */
        node: SvelteRenderableNode;
        /** Render context forwarded to child node renderers. */
        context?: SvelteRenderContext;
        /** Position key used to build stable child prefixes. */
        indexKey?: string | number;
    }

    const {node, context, indexKey}: Props = $props();
    const baseLevel = useMarkdownHeadingBaseLevel();

    const level = $derived.by(() => {
        const own = Number((node as any)?.level);
        const source = Number.isFinite(own) ? Math.min(Math.max(Math.trunc(own), 1), 6) : 1;
        return Math.min(baseLevel() - 1 + source, 6);
    });
    const children = $derived.by(() => {
        const value = (node as any)?.children;
        return Array.isArray(value) ? value : [];
    });
</script>

<svelte:element this={`h${level}`} class="heading-node heading-{level}">
    <RenderChildren nodes={children} {context} prefix={String(indexKey ?? 'heading') + '-heading'} />
</svelte:element>
