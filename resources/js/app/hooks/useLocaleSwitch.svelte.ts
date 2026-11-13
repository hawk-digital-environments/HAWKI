import {useApp} from './useApp.svelte.js';

export function useLocaleSwitch(onError: () => void) {
    const app = useApp();
    let value = $state(app.localization.locale.lang);
    let saving = $state(false);
    return {
        get value() { return value; },
        set value(lang: string) { if (!saving) value = lang; },
        get saving() { return saving; },
        async change(lang: string): Promise<void> {
            if (saving || !lang || lang === app.localization.locale.lang) return;
            saving = true;
            try {
                await app.restApi.postToResourceAction('users', 'actions/locale', {locale: lang});
                await app.localization.setLocale(lang);
                app.connection.locale = lang;
            } catch (error) {
                console.error('Failed to change the locale', error);
                value = app.localization.locale.lang;
                onError();
            } finally {
                saving = false;
            }
        }
    };
}
