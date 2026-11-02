<script lang="ts">
    // Own copy of the package worker: renders MathML alongside the HTML so
    // formulas are readable by screen readers (see the worker file).
    import katexWorkerUrl from '$lib/components/util/markdown/workers/katexRenderer.worker?worker&url';
    import mermaidWorkerUrl from 'markstream-svelte/workers/mermaidParser.worker?worker&url';

    import {MarkdownRender, setDefaultI18nMap, setKaTeXWorker, setMermaidWorker} from 'markstream-svelte';
    import type {CodeBlockOptions, NodeRendererCodeBlockProps} from 'markstream-svelte';
    import ExtendedLinkNode from '$lib/components/util/markdown/extension/ExtendedLinkNode.svelte';
    import HeadingNode from '$lib/components/util/markdown/extension/HeadingNode.svelte';
    import TableNode from '$lib/components/util/markdown/extension/TableNode.svelte';
    import {provideMarkdownHeadingBaseLevel} from '$lib/components/util/markdown/extension/headingBaseLevel.js';
    import 'katex/dist/katex.min.css';
    import 'monaco-editor/min/vs/editor/editor.main.css';
    import 'markstream-svelte/index.css';
    import {themeStore} from '$lib/stores/ThemeStore.svelte.js';
    import {getTranslationsFlat} from '$lib/utils/translator.js';
    import {loadWorker} from "$lib/components/util/markdown/workers/workerLoader";

    interface Props {
        message: string;
        isStreaming?: boolean;
        /**
         * Heading level a top-level `#` in the markdown renders as (deeper
         * headings follow, clamped to h6). Defaults to `1`, i.e. unchanged.
         */
        headingBaseLevel?: number;
    }

    let {
        message,
        isStreaming = false,
        headingBaseLevel = 1
    }: Props = $props();

    provideMarkdownHeadingBaseLevel(() => headingBaseLevel);

    setKaTeXWorker(loadWorker(katexWorkerUrl));
    setMermaidWorker(loadWorker(mermaidWorkerUrl));
    setDefaultI18nMap(getTranslationsFlat('markdown.markstream'));

    // An even inset (--space-3) above and below the code.
    const codeBlockOptions: CodeBlockOptions = {
        padding: 12
    };

    // Copy, expand and collapse are enough; the font-size stepper is noise.
    const codeBlockProps: NodeRendererCodeBlockProps = {showFontSizeButtons: false};
</script>

<MarkdownRender
    content={message}
    isDark={themeStore.theme === 'dark'}
    final={!isStreaming}
    showTooltips={false}
    customComponents={{link: ExtendedLinkNode, heading: HeadingNode, table: TableNode}}
    codeBlockOptions={codeBlockOptions}
    codeBlockProps={codeBlockProps}
    typewriter={!!isStreaming}
/>

<style>
    :global(.markstream-svelte) {
        :global([data-node-type="table"]) {
            overflow-x: auto;

            :global(table td) {
                min-width: 200px;
            }
        }

        /* markstream-svelte sizes headings by tag. With `headingBaseLevel`
           a `#` may render as h4 (and `##` as h5 with the browser default
           size), so size by the markdown level instead. Values mirror the
           package's h1–h4 rules; deeper levels keep their tag's default. */
        :global(.heading-node.heading-1) {
            font-size: clamp(2rem, 4vw, 2.65rem);
        }

        :global(.heading-node.heading-2) {
            font-size: clamp(1.55rem, 3vw, 2rem);
        }

        :global(.heading-node.heading-3) {
            font-size: 1.35rem;
        }

        :global(.heading-node.heading-4) {
            font-size: 1.15rem;
        }

        /* mermaid-preview has default overflow:clip, which clips the edge of the diagram.
        This element is the one that moves when dragging the diagram.*/
        :global(.mermaid-preview) { overflow: visible; }
    }

    :global(.darkMode) {
        :global(.markstream-svelte) {
            :global(table th) {
                background-color: var(--color-surface-light);
            }

            :global(table td) {
                background-color: var(--color-surface);
                border-color: var(--color-border);
            }

            :global(code) {
                background-color: var(--color-surface-light);
            }
        }
    }
</style>
