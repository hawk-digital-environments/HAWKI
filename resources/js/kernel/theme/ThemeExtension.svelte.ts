import type {HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {debounce} from '$lib/utils/debounce.js';
import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

/** A concrete, applied colour scheme. */
export type AppTheme = 'dark' | 'light';
/** The user's preference — `auto` follows the browser's `prefers-color-scheme`. */
export type ThemePreference = AppTheme | 'auto';

const SYSTEM_DARK_QUERY = '(prefers-color-scheme: dark)';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.theme` (see {@link HawkiAppExtension} / `createApp()` in
 * `kernel/HawkiApp.ts`).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly theme: WithoutAppExtensionInternals<ThemeExtension>;
    }
}

/**
 * App extension owning the UI theme state — the user's colour-scheme
 * **preference** and the **active theme** resolved from it, projected onto the
 * `darkMode` / `lightMode` classes of `<html>`.
 *
 * The state lives on the app (`app.theme`) rather than in a store or Svelte
 * context: it is app-owned UI state like `app.connection`, shared across all
 * snippets and component trees without extra wiring. Components reach it via
 * the `useTheme()` hook, which is a thin wrapper around this extension.
 *
 * The two-layer model:
 * - **Preference** (internal) — what the user chose: `'auto'` (the default,
 *   following the browser's `prefers-color-scheme`), `'light'` or `'dark'`.
 *   Persisted server-side as the `theme` user setting via a debounced
 *   `app.userSettings` save; failures surface as an error toast. The
 *   persisted setting is the source of truth for the *choice* — the extension
 *   only mirrors it internally to resolve the active theme and to avoid
 *   echoing server adoptions back.
 * - **Active theme** ({@link current}) — the resolved, currently applied
 *   scheme (`'dark'` | `'light'`). In `auto` it follows the browser's colour
 *   scheme live via a media-query listener; the `<html>` classes are a
 *   projection of the preference. This is the leading value everything
 *   consumes.
 *
 * A `MutationObserver` bridges the legacy vanilla-JS switcher, which toggles
 * the classes directly: when the class no longer matches the applied theme,
 * someone made an explicit choice, which is adopted as the preference (and
 * persisted). Changes matching the applied theme are our own projection and
 * are ignored.
 */
export class ThemeExtension implements HawkiAppExtension {
    private _theme = $state<AppTheme>(detectAppTheme());
    private _preference = $state<ThemePreference>('auto');
    private _initialized = false;
    private _persistPreference: ((partial: Record<string, unknown>) => void) | null = null;
    private app: UnfinishedHawkiApp | null = null;

    /**
     * The currently active, applied colour scheme — what the `darkMode` /
     * `lightMode` classes on `<html>` say and what components should branch
     * on. Reactive — reading it inside a `$derived` or component template
     * tracks it automatically.
     */
    public get current(): AppTheme {
        return this._theme;
    }

    /**
     * Resolves the active theme from the preference and applies it to `<html>`.
     * `auto` follows `prefers-color-scheme`; explicit values win.
     */
    private applyPreference(): void {
        this._theme = this.preference === 'auto' ? detectBrowserTheme() : this.preference;

        const className = this._theme === 'dark' ? 'darkMode' : 'lightMode';
        document.documentElement.classList.add(className);
        document.documentElement.classList.remove(this._theme === 'dark' ? 'lightMode' : 'darkMode');
    }

    /**
     * Bridge for the legacy vanilla-JS switcher, which toggles the `darkMode` /
     * `lightMode` classes directly. When the class no longer matches the applied
     * theme, someone made an explicit choice — adopt it as the preference (the
     * setter also persists it). Changes matching the applied theme are our own
     * projection and are ignored.
     */
    private adoptExternalClassChange(): void {
        const detected = detectDomTheme();

        if (detected !== null && detected !== this._theme) {
            this.setTheme(detected);
        }
    }

    /**
     * One-time initialisation: starts the legacy-switcher bridge, the
     * `prefers-color-scheme` listener, and adopts the server-backed
     * preference once the user-settings data is available (the `main`
     * stage — the settings are fetched on `preparation`).
     */
    public init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void {
        if (this._initialized) {
            return;
        }
        this._initialized = true;

        const observer = new MutationObserver(() => this.adoptExternalClassChange());
        observer.observe(document.documentElement, {attributes: true, attributeFilter: ['class']});

        // In `auto` the applied theme follows the browser's colour scheme live.
        window.matchMedia(SYSTEM_DARK_QUERY).addEventListener('change', () => {
            if (this.preference === 'auto') {
                this.applyPreference();
            }
        });

        bootstrapper.onMainStage(() => {
            // The server-backed preference is authoritative on load — adopt it
            // directly (not via setTheme) so it is not echoed back to the server.
            try {
                const serverPreference = app.getOrFail('userSettings').get('hawki-core').core.theme;
                if (serverPreference !== this.preference) {
                    this._preference = serverPreference;
                    this.applyPreference();
                }
            } catch (e) {
                // The user-settings data may not be available yet — the browser
                // scheme stays in charge until then.
                console.debug('Could not read the persisted theme preference:', e);
            }
        });
    }

    /**
     * The user's preference — `'auto'` follows the browser, `'light'` /
     * `'dark'` pin the scheme. Internal: consumers read {@link current} and
     * the persisted choice lives in the user settings.
     */
    private get preference(): ThemePreference {
        return this._preference;
    }

    /**
     * Sets the preference, applies it to `<html>` and persists it through the
     * user settings' debounced save — failures surface as an error toast, never
     * blocking the toggle.
     */
    public setTheme(preference: ThemePreference): void {
        this._preference = preference;
        this.applyPreference();
        this.persist(preference);
    }

    /**
     * Persists the preference server-side through the user settings'
     * {@link UserSettingsExtension.getDebouncedSave}. Stale saves (superseded
     * by a newer toggle within the debounce window) are skipped — this is
     * also what keeps the write-through loop-free. A failing save surfaces
     * as an error toast instead of a console warning, and never blocks the
     * toggle.
     */
    private persist(preference: ThemePreference): void {
        if (!this.app) {
            throw new Error('ThemeExtension has not been initialised.');
        }

        this._persistPreference ??= this.app
            .getOrFail('userSettings')
            .getDebouncedSave('hawki-core', 'core', 500, (error: unknown) => {
                console.error('Failed to persist theme preference:', error);
                const {__} = useTranslator();
                useToastContext().error(__('ui.settings.general.themeSaveError'));
            });

        this._persistPreference({theme: preference});
    }

    public provideProperties(): Record<string, unknown> {
        const extension = this;
        return {
            get theme() {
                return extension;
            }
        };
    }
}

/**
 * Detects the colour scheme from the `darkMode` / `lightMode` classes on `<html>`,
 * or null when neither class is present.
 */
function detectDomTheme(): AppTheme | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const classList = document.documentElement.classList;

    if (classList.contains('darkMode')) {
        return 'dark';
    }

    if (classList.contains('lightMode')) {
        return 'light';
    }

    return null;
}

/**
 * Detects the browser's colour scheme from `prefers-color-scheme`
 * (`'dark'` when no DOM/window is available).
 */
function detectBrowserTheme(): AppTheme {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return 'dark';
    }

    return window.matchMedia(SYSTEM_DARK_QUERY).matches ? 'dark' : 'light';
}

/**
 * Initial detection for the extension's first paint: the server-rendered
 * classes win, the browser scheme is the fallback.
 */
function detectAppTheme(): AppTheme {
    return detectDomTheme() ?? detectBrowserTheme();
}
