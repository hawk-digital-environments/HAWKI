<!--
  @component Renders a markdown string to HTML via `markstream-svelte`
  (`MarkdownRender`), wired up with HAWKI's KaTeX/Mermaid workers, theme
  awareness (dark mode follows the `theme` store), and a custom `link` node
  renderer (`ExtendedLinkNode`) so links get citation handling, favicons, and
  hash-scrolling instead of plain `<a>` tags.

  Use this instead of `MarkdownRender` directly whenever you need to render
  chat/AI-generated markdown in HAWKI — it is the app's single markdown entry
  point and keeps the worker/theme/link wiring in one place.

    <Markdown message={someMarkdownString} />

  While a message is still streaming in (e.g. token-by-token from an LLM),
  pass `isStreaming` so content is typewriter-animated and treated as
  not-yet-final (this also disables some finalization-only rendering, e.g.
  hover tooltips):

    <Markdown message={partialMessage} isStreaming={true} />

  For citation support (numbered reference chips that scroll to source
  tiles), pre-process the raw message with `injectCitationsIntoMarkdown`
  before passing it as `message` — see `MessageBody.svelte` for the full
  pattern with `CitationRoot`/`CitationList`.
-->
<script lang="ts">
    import katexWorkerUrl from 'markstream-svelte/workers/katexRenderer.worker?worker&url';
    import mermaidWorkerUrl from 'markstream-svelte/workers/mermaidParser.worker?worker&url';
    import {createCrossOriginWorker} from '$lib/utils/crossOriginWorker.js';
    import {MarkdownRender, setDefaultI18nMap, setKaTeXWorker, setMermaidWorker} from 'markstream-svelte';
    import ExtendedLinkNode from '$lib/components/util/markdown/extension/ExtendedLinkNode.svelte';
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
    }

    let {
        message,
        isStreaming = false
    }: Props = $props();

    setKaTeXWorker(createCrossOriginWorker(katexWorkerUrl, import.meta.url));
    setMermaidWorker(createCrossOriginWorker(mermaidWorkerUrl, import.meta.url));
    setDefaultI18nMap(getTranslationsFlat('markdown.markstream'));
</script>

<MarkdownRender
    content={message}
    isDark={themeStore.theme === 'dark'}
    final={!isStreaming}
    showTooltips={false}
    customComponents={{link: ExtendedLinkNode}}
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
