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
    import {MarkdownRender, setDefaultI18nMap, setKaTeXWorker, setMermaidWorker} from 'markstream-svelte';
    import ExtendedLinkNode from './extension/ExtendedLinkNode.svelte';
    import 'katex/dist/katex.min.css';
    import 'monaco-editor/min/vs/editor/editor.main.css';
    import 'markstream-svelte/index.css';
    import {useColorScheme} from '../../lib/color-scheme/ColorSchemeContext.js';
    import {useTranslator} from '../../lib/i18n/TranslatorContext.js';

    const colorSchemeContext = useColorScheme();
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
    setDefaultI18nMap(getTranslationsFlat('components.markdown.markstream'));
</script>

<MarkdownRender
    content={message}
    isDark={colorSchemeContext.colorScheme === 'dark'}
    final={!!isStreaming}
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
