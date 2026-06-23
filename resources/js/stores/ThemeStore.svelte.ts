export type AppTheme = 'dark' | 'light';

/**
 * Reactive store for the active UI theme (`'dark'` | `'light'`).
 *
 * Initializes by reading the `darkMode` / `lightMode` CSS class on `<html>`,
 * falling back to the `prefers-color-scheme` media query when neither class is
 * present. A `MutationObserver` on `<html>` keeps `theme` reactive when the
 * class changes from outside (e.g. the legacy vanilla-JS theme switcher).
 *
 * Setting `theme` updates the `<html>` class list and the reactive value in
 * one step, so components that read `themeStore.theme` re-render automatically.
 *
 * Used by `BorderBeam` (to pick the correct beam colour preset when `theme="auto"`)
 * and by any component that needs to branch on the current colour scheme.
 *
 * @example
 * import {themeStore} from '$lib/stores/ThemeStore.svelte.js';
 * // Read
 * const isDark = $derived(themeStore.theme === 'dark');
 * // Write
 * themeStore.theme = 'light';
 */
function detectAppTheme(): AppTheme {
    if (typeof document === 'undefined') {
        return 'dark';
    }

    const cl = document.documentElement.classList;

    if (cl.contains('darkMode')) {
        return 'dark';
    }

    if (cl.contains('lightMode')) {
        return 'light';
    }

    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export class ThemeStore {
    constructor(initialTheme: AppTheme) {
        this._theme = $state(initialTheme);
        const observer = new MutationObserver(() => (this._theme = detectAppTheme()));
        observer.observe(document.documentElement, {attributes: true, attributeFilter: ['class']});
    }

    private _theme: AppTheme;

    /** The currently active theme. Reactive — reading it inside a `$derived` or
     *  component template tracks it automatically. */
    public get theme(): AppTheme {
        return this._theme;
    }

    /** Sets the active theme by toggling `darkMode` / `lightMode` on `<html>`
     *  and updating the reactive value in one step. */
    public set theme(value: AppTheme) {
        const className = value === 'dark' ? 'darkMode' : 'lightMode';
        document.documentElement.classList.add(className);
        document.documentElement.classList.remove(value === 'dark' ? 'lightMode' : 'darkMode');
        this._theme = value;
    }
}

export const themeStore = new ThemeStore(detectAppTheme());
