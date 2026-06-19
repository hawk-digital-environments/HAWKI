export type AppTheme = 'dark' | 'light';

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

    public get theme(): AppTheme {
        return this._theme;
    }

    public set theme(value: AppTheme) {
        const className = value === 'dark' ? 'darkMode' : 'lightMode';
        document.documentElement.classList.add(className);
        document.documentElement.classList.remove(value === 'dark' ? 'lightMode' : 'darkMode');
        this._theme = value;
    }
}

export const themeStore = new ThemeStore(detectAppTheme());
