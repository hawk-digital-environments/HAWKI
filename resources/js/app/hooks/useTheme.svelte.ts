import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import type {AppTheme, ThemePreference} from '$lib/kernel/theme/ThemeExtension.svelte.js';

/** Re-exported so consumers of the theme have a single import point. */
export type {AppTheme, ThemePreference} from '$lib/kernel/theme/ThemeExtension.svelte.js';

/**
 * Hook that gives components access to the UI theme — a thin wrapper around
 * `app.theme` (see {@link ThemeExtension}), in line with {@link useConfig} or
 * {@link useUserSettings}: components never touch the app surface directly,
 * they read it through this hook.
 *
 * Returns a plain object (safe to destructure without losing reactivity —
 * `theme` is a getter over runes-backed state):
 *
 * - `theme` — the currently **active** scheme (`'dark'` | `'light'`), i.e.
 *   what the `darkMode` / `lightMode` classes on `<html>` say. This is the
 *   leading value: with the `auto` preference (the default) it follows the
 *   browser's `prefers-color-scheme` live, an explicit choice pins it. Use it
 *   to branch on the current colour scheme.
 * - `setTheme(preference)` — sets the user's preference (`'auto'`, `'light'`
 *   or `'dark'`) and persists it server-side as the `theme` user setting
 *   (debounced, failures surface as an error toast).
 *
 * The initial `<html>` classes come from the server render, so the first
 * paint is always correct; `auto` keeps following the browser scheme live
 * afterwards.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useTheme} from '$lib/app/hooks/useTheme.svelte.js';
 *
 *     const {theme, setTheme} = useTheme();
 *     const isDark = $derived(theme === 'dark');
 * </script>
 *
 * <button onclick={() => setTheme(isDark ? 'light' : 'dark')}>Toggle</button>
 * ```
 */
export function useTheme(): {
    theme: AppTheme;
    setTheme: (preference: ThemePreference) => void;
} {
    const app = useApp();

    return {
        get theme() {
            return app.theme.current;
        },
        setTheme: (preference) => app.theme.setTheme(preference)
    };
}
