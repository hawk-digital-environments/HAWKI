<script lang="ts">
    import katexWorkerUrl from 'markstream-svelte/workers/katexRenderer.worker?worker&url';
    import mermaidWorkerUrl from 'markstream-svelte/workers/mermaidParser.worker?worker&url';

    import {MarkdownRender, setDefaultI18nMap, setKaTeXWorker, setMermaidWorker} from 'markstream-svelte';
    import ExtendedLinkNode from '$lib/components/util/markdown/extension/ExtendedLinkNode.svelte';
    import 'katex/dist/katex.min.css';
    import 'monaco-editor/min/vs/editor/editor.main.css';
    import 'markstream-svelte/index.css';
    import {themeStore} from '$lib/stores/ThemeStore.svelte.js';
    import {getTranslationsFlat} from '$lib/utils/translator.js';

    interface Props {
        message: string;
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
    setDefaultI18nMap(getTranslationsFlat('markdown.markstream'));
</script>

<MarkdownRender
    content={message}
    isDark={themeStore.theme === 'dark'}
    final={!!isStreaming}
    showTooltips={false}
    customComponents={{link: ExtendedLinkNode}}
    typewriter={!!isStreaming}
/>

<style>
    :global(.darkMode .markstream-svelte) {
        :global(table) {
            :global(th) {
                background-color: var(--color-surface-light);
            }

            :global(td) {
                background-color: var(--color-surface);
                border-color: var(--color-border);
            }
        }

        :global(code) {
            background-color: var(--color-surface-light);
        }
    }
</style>
