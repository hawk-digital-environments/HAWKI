import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {createContext} from 'svelte';
import {getHawkiApp} from '$lib/legacy/legacy.js';

const [get, set] = createContext<HawkiApp>();

/**
 * Provides the {@link HawkiApp} instance to a component subtree via Svelte
 * context. Call this once, near the root of a Svelte app/island, right after
 * the app has finished bootstrapping (see `app.ts`).
 *
 * You normally do not need to call this yourself — it exists so that every
 * Svelte root mounted on the page (there can be several, since HAWKI is
 * mid-migration to a single-page app) can make the same `HawkiApp` instance
 * available to its own component tree via context, without relying on the
 * legacy global lookup.
 *
 * @param app The fully-booted `HawkiApp` instance to expose to descendants.
 */
export function provideApp(app: HawkiApp) {
    set(app);
}

/**
 * Hook that gives any component access to the {@link HawkiApp} instance —
 * the root object that all other extension surfaces hang off of
 * (`app.config`, `app.stores`, `app.restApi`, `app.connection`, ...).
 *
 * Prefer one of the more specific hooks (`useConfig`, `useStore`,
 * `useConnection`, `useApi`, `useTranslator`) when they cover your need —
 * reach for `useApp()` directly only when you need an extension surface that
 * has no dedicated hook yet, or need the app object itself (e.g. to pass it
 * into a non-component helper function).
 *
 * Resolution order: first tries the Svelte context set up by `provideApp()`;
 * if no context is available (e.g. inside legacy, non-context-aware code)
 * it falls back to the legacy global registry (`getHawkiApp()`). The
 * fallback is a temporary bridge and will be removed once all legacy code is
 * refactored to use Svelte context.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useApp} from '$lib/app/hooks/useApp.svelte.js';
 *
 *     const app = useApp();
 * </script>
 *
 * <img src={app.uriBuilder.linkPreviewFaviconUri(href)} alt="" />
 * ```
 */
export function useApp() {
    let app;
    try {
        app = get();
    } catch (error) {
    }

    if (!app) {
        return getHawkiApp(); // @todo remove this fallback once all legacy code is refactored
        // throw new Error('HawkiApp context is not provided. Make sure to call provideApp() in a parent component.');
    }
    return app;
}
