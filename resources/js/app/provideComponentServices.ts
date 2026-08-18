import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {provideColorScheme, provideLinkServices, provideTranslator} from '@hawk-hhg/hawki-svelte-components';

/**
 * Publishes every service the `@hawk-hhg/hawki-svelte-components` package
 * needs from the host app (colour scheme, link services, ...) into Svelte
 * context, for the current component subtree.
 *
 * WHY this exists as a single function: the package never reaches into the
 * app directly — everything it needs is injected as an optional service via
 * context (see `resources/js/components/README.md`, "Dependency-injection
 * design"). The app mounts Svelte at more than one root (`Shell.svelte`, and
 * every `<svelte-snippet>` mounted independently by
 * `legacy/svelteSnippetLoader.ts`), and a context set at one root is
 * invisible at the others. Routing every service through this one function —
 * called once per mount root, during that root's component initialization —
 * keeps both roots in sync and gives every new service a single place to be
 * wired in.
 *
 * Call this from a `<script>` top level (component initialization), not from
 * an event handler or `$effect` — `setContext` has the same requirement.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {provideComponentServices} from '$lib/app/provideComponentServices.js';
 *     const {app}: {app: HawkiApp} = $props();
 *     provideComponentServices(app);
 * </script>
 * ```
 */
export function provideComponentServices(app: HawkiApp): void {
    const themeStore = app.stores.get('theme');
    provideColorScheme(() => themeStore.theme);

    provideLinkServices({
        faviconUrl: (url) => app.uriBuilder.linkPreviewFaviconUri(url),
        fetchPreview: (url) => app.linkPreviewApi.getLinkPreviewMetadata(url)
    });

    provideTranslator(() => app.localization.translator);
}
