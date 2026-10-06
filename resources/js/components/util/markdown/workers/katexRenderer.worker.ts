/**
 * KaTeX render worker for `markstream-svelte`, registered via `setKaTeXWorker`
 * in `Markdown.svelte`. Mirrors the package's own
 * `workers/katexRenderer.worker.js` (same message protocol), with one change:
 * formulas are rendered with `output: 'htmlAndMathml'` so every formula
 * carries a MathML tree that screen readers can announce. The package worker
 * renders `output: 'html'` only, which leaves nothing but aria-hidden visuals.
 * @todo Remove this copy once markstream-svelte accepts render options for its KaTeX worker; until then, keep its message protocol in sync on upgrades.
 */
import katex from 'katex';
// Registers the \ce and \pu macros on this worker's KaTeX instance.
// Use the public export rather than the untyped dist-internal path.
import 'katex/contrib/mhchem';

interface RenderRequest {
    type?: 'init' | 'render';
    debug?: boolean;
    id?: string;
    content?: string;
    displayMode?: boolean;
}

let debugWorker = false;

globalThis.addEventListener('message', (event: MessageEvent<RenderRequest>) => {
    const data = event.data ?? {};
    if (data.type === 'init') {
        debugWorker = Boolean(data.debug);
        return;
    }

    const id = data.id ?? '';
    const content = data.content ?? '';
    const displayMode = data.displayMode ?? true;
    try {
        if (debugWorker) {
            console.debug('[hawki:katexRenderer.worker] render start', {id, displayMode, content});
        }
        const html = katex.renderToString(content, {
            throwOnError: true,
            displayMode,
            output: 'htmlAndMathml',
            strict: 'ignore'
        });
        globalThis.postMessage({id, html, content, displayMode});
    } catch (error) {
        globalThis.postMessage({
            id,
            error: String(error instanceof Error ? error.message : error),
            content,
            displayMode
        });
    }
});

globalThis.addEventListener('error', (event: ErrorEvent) => {
    try {
        globalThis.postMessage({
            id: '__worker_uncaught__',
            error: String(event.message ?? event.error),
            content: '',
            displayMode: true
        });
    } catch {
        // Ignore postMessage failures while surfacing uncaught worker errors.
    }
});
