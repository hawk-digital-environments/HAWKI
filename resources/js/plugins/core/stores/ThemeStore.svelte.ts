import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {z} from 'zod';

export type AppTheme = 'dark' | 'light';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'theme': ThemeStore;
    }
}

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
 * import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 * const themeStore = useStore('theme');
 * // Read
 * const isDark = $derived(themeStore.theme === 'dark');
 * // Write
 * themeStore.theme = 'light';
 */
export class ThemeStore implements DataStore {
    public readonly name = 'theme';

    private _theme = $state(detectAppTheme());
    private app: HawkiApp | null = null;

    public async loadData(app: HawkiApp): Promise<void> {
        this.app = app;

        const observer = new MutationObserver(() => (this._theme = detectAppTheme()));
        observer.observe(document.documentElement, {attributes: true, attributeFilter: ['class']});

        // The server-rendered class cannot know a browser-only preference.
        const persisted = themeSchema.safeParse(app.localStorage.getItem('theme')).data;
        if (persisted && persisted !== this._theme) {
            this.theme = persisted;
        }
    }

    /** The currently active theme. Reactive — reading it inside a `$derived` or
     *  component template tracks it automatically. */
    public get theme(): AppTheme {
        return this._theme;
    }

    public get isDark(): boolean {
        return this._theme === 'dark';
    }

    public get isLight(): boolean {
        return this._theme === 'light';
    }

    /** Sets the active theme by toggling `darkMode` / `lightMode` on `<html>`,
     *  persisting the preference and updating the reactive value in one step. */
    public set theme(value: AppTheme) {
        const className = value === 'dark' ? 'darkMode' : 'lightMode';
        document.documentElement.classList.add(className);
        document.documentElement.classList.remove(value === 'dark' ? 'lightMode' : 'darkMode');
        this.app?.localStorage.setItem('theme', value);
        this._theme = value;
    }
}

const themeSchema = z.union([z.literal('dark'), z.literal('light')]);

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
