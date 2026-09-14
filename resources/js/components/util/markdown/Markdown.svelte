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
</script>

<MarkdownRender
    content={message}
    isDark={themeStore.theme === 'dark'}
    final={!isStreaming}
    showTooltips={false}
    customComponents={{link: ExtendedLinkNode, heading: HeadingNode, table: TableNode}}
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

        :global(.paragraph-node) {
            margin-top: 0;
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
