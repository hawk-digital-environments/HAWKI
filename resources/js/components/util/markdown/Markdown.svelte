<script lang="ts">
    // Own copy of the package worker: renders MathML alongside the HTML so
    // formulas are readable by screen readers (see the worker file).
    import katexWorkerUrl from '$lib/components/util/markdown/workers/katexRenderer.worker?worker&url';
    import mermaidWorkerUrl from 'markstream-svelte/workers/mermaidParser.worker?worker&url';

    import {MarkdownRender, setDefaultI18nMap, setKaTeXWorker, setMermaidWorker} from 'markstream-svelte';
    import type {CodeBlockOptions, NodeRendererCodeBlockProps} from 'markstream-svelte';
    import ExtendedLinkNode from '$lib/components/util/markdown/extension/ExtendedLinkNode.svelte';
    import HeadingNode from '$lib/components/util/markdown/extension/HeadingNode.svelte';
    import MathNode from '$lib/components/util/markdown/extension/MathNode.svelte';
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
    customComponents={{link: ExtendedLinkNode, heading: HeadingNode, math_block: MathNode, table: TableNode}}
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

        /* Top-level tables scroll via their node-slot wrapper above. Nested
           tables (e.g. inside a blockquote) render directly inside their
           parent with no slot wrapper, so wide ones overflow the message
           column and get clipped by the chat panel (overflow-x: hidden).
           Make the blockquote their scroll container, mirroring the
           top-level behavior. */
        :global(blockquote) {
            overflow-x: auto;

            :global(table td) {
                min-width: 200px;
            }
        }

        /* Task-list checkboxes are native controls; the app reset's `font:
           inherit` (via svelte-snippet) scales them up against the text.
           Render them GitHub-style: native ~13px size (0.8em at the 16px
           message font), middle-aligned on the line, tinted with the theme
           accent instead of the UA gray. The explicit minimums guard against
           page-wide input rules (style.css gives every input min-width:10rem
           / min-height:2.5rem — min-* clamp width/height regardless of
           importance, which once rendered these at 160×40px). */
        :global(input[type='checkbox'].checkbox-node) {
            width: 0.8em;
            height: 0.8em;
            min-width: 0.8em;
            min-height: 0.8em;
            margin: 0 0.35em 0 0;
            vertical-align: middle;
            accent-color: var(--accent-color);
        }

        /* Task items carry both their list marker and the checkbox — a
           double indicator (worst at deep nesting where markers shrink to
           squares). GitHub-style: drop the marker, the checkbox IS the
           marker. The direct-child chain keeps ancestor items of nested
           task lists unaffected. */
        :global(li:has(> p > .checkbox-node)) {
            list-style: none;
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

        /* The HTML/SVG preview frame ships viewport-sized (80vw × 70vh,
           max-width 960px) but renders inline below the code block, so it
           overflows the message column. Fit it to the container instead;
           the iframe scrolls its own content. Drop this once the upstream
           fix is released and the dependency bumped (see MARKSTREAM-SVELTE.md). */
        :global(.html-preview-frame) {
            width: 100%;
            max-width: 100%;
            height: auto;
            aspect-ratio: 16 / 10;
            min-height: 16rem;
            max-height: 70vh;
            margin-top: 0.5rem;
        }

        /* The component emits `is-dark` but the package CSS targets `--dark`,
           so dark rules never apply: the frame stays white while its text
           inherits the light chat color. Restyle until fixed upstream. */
        :global(.html-preview-frame.is-dark) {
            background-color: var(--color-surface);
            color: var(--color-text);
            box-shadow: none;
        }

        :global(.html-preview-frame.is-dark .html-preview-frame__header) {
            border-bottom-color: var(--color-border);
        }
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
