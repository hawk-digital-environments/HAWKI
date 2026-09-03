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

    /** Level as written in the markdown (`#` = 1); drives the visual size. */
    const sourceLevel = $derived.by(() => {
        const own = Number((node as any)?.level);
        return Number.isFinite(own) ? Math.min(Math.max(Math.trunc(own), 1), 6) : 1;
    });
    /** Level of the rendered element, shifted below the page outline. */
    const level = $derived(Math.min(baseLevel() - 1 + sourceLevel, 6));
    const children = $derived.by(() => {
        const value = (node as any)?.children;
        return Array.isArray(value) ? value : [];
    });
</script>

<!-- The tag carries the outline level; the `heading-{n}` class keeps the
     markdown level, like the package's own HeadingNode, so `Markdown.svelte`
     can size a shifted `#` like an h1 instead of like an h4. -->
<svelte:element this={`h${level}`} class="heading-node heading-{sourceLevel}" data-level={sourceLevel}>
    <RenderChildren nodes={children} {context} prefix={String(indexKey ?? 'heading') + '-heading'} />
</svelte:element>
