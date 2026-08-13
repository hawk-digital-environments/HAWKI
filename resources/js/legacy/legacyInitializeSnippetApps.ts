import {HTMLSvelteSnippetElement} from '$lib/legacy/svelteSnippetLoader.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {Component} from 'svelte';

/**
 * Bootstraps the `<svelte-snippet>` system for pages that are still plain
 * Blade + vanilla-JS (no `#hawki-app` mount point). Called once by
 * {@link ShellExtension.ready} (`kernel/shell/ShellExtension.svelte.ts`) as
 * the fallback path when no SPA shell was mounted.
 *
 * Globs every `.svelte` file under `plugins/core/snippets/`, lazily imports
 * and registers each one into `app.snippets` under its file base name, then
 * (once the DOM is ready) defines the `svelte-snippet` custom element and
 * injects a `LegacySharedContent` snippet as the first child of `<body>` —
 * the one-per-page host for shared UI like the `Toaster`, see
 * `LegacySharedContent.svelte`.
 *
 * @deprecated This is part of the snippet system that is being phased out.
 */
export async function legacyInitializeSnippetApps(
    app: HawkiApp,
    runWhenDomReady: (callback: () => void) => void
) {
    const glob = import.meta.glob('$lib/plugins/core/snippets/**/*.svelte', {eager: false});
    for (const [path, loader] of Object.entries(glob)) {
        const snippetName = path.split('/').pop()?.replace('.svelte', '');
        if (!snippetName) {
            console.warn(`Failed to register snippet from path '${path}': Could not determine snippet name.`);
            continue;
        }
        const module = await loader();
        app.snippets.register(snippetName, (module as { default: Component }).default);
    }

    return new Promise<void>(resolve => {
        runWhenDomReady(() => {
            // Register the custom element for Svelte snippets if it hasn't been registered yet
            customElements.define('svelte-snippet', HTMLSvelteSnippetElement);

            // Inject the "LegacySharedContent" snippet into the page (as first child of the body)
            const legacySharedContentSnippet = document.createElement('svelte-snippet');
            legacySharedContentSnippet.setAttribute('type', 'LegacySharedContent');
            document.body.insertBefore(legacySharedContentSnippet, document.body.firstChild);

            resolve();
        });
    });
}
