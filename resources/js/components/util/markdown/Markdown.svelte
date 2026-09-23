<!--
  @component Renders a markdown string to HTML via `markstream-svelte`
  (`MarkdownRender`), wired up with HAWKI's KaTeX/Mermaid workers, theme
  awareness (dark mode follows the `theme` store), and custom node renderers:
  `ExtendedLinkNode` (citation handling, favicons, hash-scrolling instead of
  plain `<a>` tags), `HeadingNode` (levels shifted below the page outline via
  `headingBaseLevel`) and `TableNode` (`scope="col"` on header cells).

  Use this instead of `MarkdownRender` directly whenever you need to render
  chat/AI-generated markdown in HAWKI — it is the app's single markdown entry
  point and keeps the worker/theme/link wiring in one place.

    <Markdown message={someMarkdownString} />

  While a message is still streaming in (e.g. token-by-token from an LLM),
  pass `isStreaming` so content is typewriter-animated and treated as
  not-yet-final (this also disables some finalization-only rendering, e.g.
  hover tooltips):

    <Markdown message={partialMessage} isStreaming={true} />

  Inside a page that already has its own headings, pass `headingBaseLevel` so
  a `#` in the markdown does not become a second h1:

    <Markdown message={reply} headingBaseLevel={4} />

  For citation support (numbered reference chips that scroll to source
  tiles), pre-process the raw message with `injectCitationsIntoMarkdown`
  before passing it as `message` — see `MessageBody.svelte` for the full
  pattern with `CitationRoot`/`CitationList`.
-->
<script lang="ts">
    // Own copy of the package worker: renders MathML alongside the HTML so
    // formulas are readable by screen readers (see the worker file).
    import katexWorkerUrl from '$lib/components/util/markdown/workers/katexRenderer.worker?worker&url';
    import mermaidWorkerUrl from 'markstream-svelte/workers/mermaidParser.worker?worker&url';
    import {MarkdownRender, setDefaultI18nMap, setKaTeXWorker, setMermaidWorker} from 'markstream-svelte';
    import type {CodeBlockMonacoOptions, NodeRendererCodeBlockProps} from 'markstream-svelte';
    import ExtendedLinkNode from '$lib/components/util/markdown/extension/ExtendedLinkNode.svelte';
    import HeadingNode from '$lib/components/util/markdown/extension/HeadingNode.svelte';
    import TableNode from '$lib/components/util/markdown/extension/TableNode.svelte';
    import {provideMarkdownHeadingBaseLevel} from '$lib/components/util/markdown/extension/headingBaseLevel.js';
    import 'katex/dist/katex.min.css';
    import 'monaco-editor/min/vs/editor/editor.main.css';
    import 'markstream-svelte/index.css';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const themeStore = useStore('theme');
    const {getTranslationsFlat} = useTranslator();

    interface Props {
        /**
         * The markdown source to render. For citation-enabled messages, run
         * it through `injectCitationsIntoMarkdown()` first.
         */
        message: string;
        /**
         * Set while the message is still being received (e.g. streamed
         * token-by-token). Enables `MarkdownRender`'s typewriter animation
         * and marks the content as not `final` yet. Defaults to `false`.
         */
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

    // @see https://github.com/vitejs/vite/issues/13680
    function loadWorker(url: string) {
        const blob = new Blob(
            [`import ${JSON.stringify(new URL(url, import.meta.url))}`],
            {type: 'application/javascript'}
        );
        const objURL = URL.createObjectURL(blob);
        const worker = new Worker(objURL, {type: 'module'});
        worker.addEventListener('error', () => URL.revokeObjectURL(objURL));
        return worker;
    }

    setKaTeXWorker(loadWorker(katexWorkerUrl));
    setMermaidWorker(loadWorker(mermaidWorkerUrl));
    setDefaultI18nMap(getTranslationsFlat('markdown.markstream'));

    // Snippets are read-only: no current-line highlight or cursor mark in the
    // overview ruler, and an even inset (--space-3) above and below the code.
    const codeBlockMonacoOptions: CodeBlockMonacoOptions = {
        renderLineHighlight: 'none',
        hideCursorInOverviewRuler: true,
        overviewRulerBorder: false,
        padding: {top: 12, bottom: 12}
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
    codeBlockMonacoOptions={codeBlockMonacoOptions}
    codeBlockProps={codeBlockProps}
    typewriter={!!isStreaming}
/>

<style>
    /* markstream ships its own look (IBM Plex, slate colours, shadowed cards).
       These rules put it on the app's tokens. The root is matched on both of
       its classes so they outrank markstream's selectors in any load order.
       Note that markstream redefines `--border` on this root, so outlines use
       `--divider` (same value) instead. */
    :global(.markstream-svelte.markdown-renderer) {
        /* Read in the surrounding text's family, size and colour. */
        font: inherit;
        color: inherit;

        /* Chrome of markstream's diagram blocks. */
        --diagram-bg: var(--color-bg);
        --diagram-header-bg: var(--color-bg);
        --diagram-border: var(--color-border);
        --code-action-fg: var(--color-text-muted);
        --code-action-hover-bg: transparent;
        --code-action-hover-fg: var(--color-text);
        --ms-shadow-subtle: none;

        /* ── Rhythm ───────────────────────────────────────────────────── */

        :global(:is(p, ul, ol, dl, blockquote, table, details)),
        :global(div:where(.code-block-container, .mermaid-block, .markstream-svelte-enhanced-block)) {
            margin: 0 0 var(--space-4);
        }

        /* Headings step down the app's type ramp and never land on the body
           size. The two largest carry the medium weight of the app's titles. */
        :global(:is(h1, h2, h3, h4, h5, h6)) {
            margin: var(--space-6) 0 var(--space-3);
            font-weight: var(--font-weight-semibold);
            line-height: var(--line-height-tight);
            letter-spacing: normal;
        }

        :global(:is(h1, h2)) {
            font-weight: var(--font-weight-medium);
        }

        /* A heading right after another keeps only the gap below the first,
           so stacked headings read as one group rather than separate sections. */
        :global([data-node-type="heading"] + [data-node-type="heading"] > .node-content > :first-child) {
            margin-top: 0;
        }

        :global(h1) { font-size: var(--font-size-xl); }
        :global(h2) { font-size: var(--font-size-lg); }
        :global(:is(h3, h4)) { font-size: var(--font-size-base); }
        :global(:is(h5, h6)) { font-size: var(--font-size-xs); }

        :global(hr) {
            margin: var(--space-6) 0;
            border: none;
            border-top: var(--divider);
        }

        /* Every block sits in its own .node-slot, so the outer margins are
           trimmed there: the text hugs the reasoning above and the actions below. */
        :global(.node-slot:first-of-type > .node-content > :first-child) { margin-top: 0; }
        :global(.node-slot:last-of-type > .node-content > :last-child) { margin-bottom: 0; }
        :global(:is(blockquote, details) > :first-child) { margin-top: 0; }
        :global(:is(li, blockquote, details, th, td) > :last-child) { margin-bottom: 0; }

        /* ── Inline ───────────────────────────────────────────────────── */

        :global(:is(strong, b)) { font-weight: var(--font-weight-semibold); }

        :global(a:not(.citation-reference)) { color: var(--color-accent-text); }

        :global(:not(pre) > code) {
            font: inherit;
            font-family: var(--font-family-mono);
            font-size: 0.875em;
            background: var(--color-surface);
        }

        :global(mark) {
            background: var(--color-active-surface);
            color: inherit;
        }

        /* ── Lists ────────────────────────────────────────────────────── */

        :global(:is(ul, ol)) { padding-left: var(--space-6); }
        :global(li::marker) { color: var(--color-text-muted); }
        :global(li + li) { margin-top: var(--space-1); }
        :global(li > *) { margin-block: 0; }
        :global(li > * + *) { margin-top: var(--space-2); }
        :global(li > :is(ul, ol)) { margin-top: var(--space-1); }

        /* Task items: the checkbox takes the bullet's place in the gutter, so
           the text lines up with plain items. Checked, it fills like a Switch. */
        :global(li:has(> p:first-child > input[type="checkbox"]:first-child)) { list-style: none; }

        :global(input[type="checkbox"]) {
            appearance: none;
            display: inline-grid;
            place-content: center;
            width: 1em;
            height: 1em;
            margin: 0 var(--space-1) 0 0;
            border: var(--divider-width) solid var(--color-border-strong);
            border-radius: calc(var(--corner-xs) / 2);
            font: inherit;
            vertical-align: -0.125em;
        }

        :global(li > p:first-child > input[type="checkbox"]:first-child) {
            margin-left: calc(-1 * var(--space-6));
        }

        :global(input[type="checkbox"]:checked) {
            border-color: var(--color-interactive);
            background: var(--color-interactive);
        }

        :global(input[type="checkbox"]:checked::before) {
            content: '';
            width: 0.625em;
            height: 0.625em;
            background: var(--color-on-interactive);
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0, 43% 62%);
        }

        /* ── Quotes and disclosures ───────────────────────────────────── */

        /* The same rail as a message thread. */
        :global(blockquote) {
            padding: 0 0 0 var(--space-4);
            border-left: 2px solid var(--color-border);
            color: var(--color-text-muted);
        }

        :global(details) {
            padding: var(--space-3) var(--space-4);
            border: none;
            border-radius: var(--corner-md);
            background: var(--color-surface-light);
        }

        :global(summary) { font-weight: var(--font-weight-medium); }
        :global(summary::marker) { color: var(--color-text-muted); }
        :global(details[open] > summary) { margin-bottom: var(--space-2); }

        /* ── Tables: flat rows split by hairlines ─────────────────────── */

        /* The scroller contains the table's margins, so it carries the gap
           itself and can still collapse with its neighbours. */
        :global([data-node-type="table"]) {
            overflow-x: auto;
            margin-bottom: var(--space-4);

            :global(table) {
                margin: 0;
            }

            :global(table td) {
                min-width: 200px;
            }
        }

        :global([data-node-type="table"]:last-of-type) { margin-bottom: 0; }

        :global(table) {
            border-radius: 0;
            box-shadow: none;
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

        :global(:is(th, td)) {
            padding: var(--space-2) var(--space-3);
            border: none;
            border-bottom: var(--divider);
            background: none;
        }

        :global(:is(th, td):first-child) { padding-left: 0; }
        :global(:is(th, td):last-child) { padding-right: 0; }
        :global(tbody tr:last-child td) { border-bottom: none; }
        :global(th) { font-weight: var(--font-weight-semibold); }

        /* ── Code and diagrams ────────────────────────────────────────── */

        /* Outlined on the page background, which is also the background of
           Monaco's vitesse themes, so header and code read as one surface. */
        :global(:is(div.code-block-container, div.mermaid-block, div.markstream-svelte-enhanced-block)) {
            --markstream-code-fallback-bg: var(--color-bg);
            --markstream-code-fallback-fg: var(--color-text);
            border: var(--divider);
            border-radius: var(--corner-md);
            background: var(--color-bg);
            box-shadow: none;
        }

        :global(.code-block-header) {
            padding: var(--space-2) var(--space-2) var(--space-2) var(--space-4);
            border-bottom: var(--divider);
        }

        /* Collapsed, the header would stack its divider on the outline. */
        :global(.code-block-header:last-child) { border-bottom: none; }

        :global(:is(.code-block-header__label, .mermaid-title__text)) {
            font: var(--font-weight-medium) var(--font-size-xs) / var(--line-height-tight) var(--font-family-base);
            color: var(--color-text-muted);
        }

        :global(span.code-block-language-icon) { color: var(--color-text-muted); }

        /* Sized and coloured like the app's iconGhost buttons. */
        :global(.code-action-btn) {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: var(--corner-full);
            color: var(--color-text-muted);
        }

        :global(.code-action-btn:hover) {
            background: transparent;
            color: var(--color-text);
        }

        :global(.code-action-btn svg) {
            width: 14px;
            height: 14px;
        }
    }

    /* markstream's own tooltip for the code block buttons is mounted on
       <body>; dress it like the app's Tooltip. */
    :global(html .ms-tooltip[data-dark]) {
        padding: var(--space-1) var(--space-3);
        border: var(--border);
        border-radius: var(--corner-sm);
        background-color: var(--color-surface-raised);
        color: var(--color-text);
        font-size: var(--font-size-xxs);
        line-height: var(--line-height-normal);
        box-shadow: var(--elevation-1);
    }
</style>
