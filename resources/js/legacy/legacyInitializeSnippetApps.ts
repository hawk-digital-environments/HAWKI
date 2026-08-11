import {HTMLSvelteSnippetElement} from '$lib/legacy/svelteSnippetLoader.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {Component} from 'svelte';

/**
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

            // This promise ensures that the
            resolve();
        });
    });
}
