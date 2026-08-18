import {createContext} from 'svelte';

/**
 * Union of colour schemes this package's components adapt to. Package-owned —
 * consumers with their own theme alias (e.g. HAWKI's app-side `AppTheme`)
 * should conform to this union rather than the package importing theirs.
 */
export type ColorScheme = 'light' | 'dark';

/**
 * Context value published by {@link provideColorScheme}. Exposes the active
 * colour scheme as a getter (not a plain field) so the value stays reactive
 * across the context indirection — reading `.colorScheme` inside a
 * `$derived` or a component template tracks the host's live value rather
 * than a one-time snapshot taken when the context was created.
 */
export interface ColorSchemeContextValue {
    readonly colorScheme: ColorScheme;
}

const [get, set] = createContext<ColorSchemeContextValue>();

/**
 * Returned by {@link useColorScheme} when no ancestor called
 * {@link provideColorScheme} — keeps every component in this package
 * rendering correctly standalone (e.g. outside the host app, in a
 * story/playground).
 */
const standaloneColorScheme: ColorSchemeContextValue = {
    get colorScheme(): ColorScheme {
        return 'light';
    }
};

/**
 * Publishes the active colour scheme to the component subtree. Call once,
 * during component initialization (a host app's root layout / every Svelte
 * mount root), passing a getter backed by the host's own reactive theme
 * source so this stays live rather than a frozen snapshot.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {provideColorScheme} from '@hawk-hhg/hawki-svelte-components';
 *     import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 *
 *     const themeStore = useStore('theme');
 *     provideColorScheme(() => themeStore.theme);
 * </script>
 * ```
 */
export function provideColorScheme(getter: () => ColorScheme): void {
    set({
        get colorScheme(): ColorScheme {
            return getter();
        }
    });
}

/**
 * Reads the colour scheme published by an ancestor {@link provideColorScheme}
 * call. Falls back to `'light'` when no host provided one, so components
 * using this hook (`BorderBeam`, `Markdown`) stay renderable in isolation.
 */
export function useColorScheme(): ColorSchemeContextValue {
    try {
        return get();
    } catch {
        return standaloneColorScheme;
    }
}
