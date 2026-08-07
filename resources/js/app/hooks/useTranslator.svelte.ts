import {useApp} from '$lib/app/hooks/useApp.svelte.js';

export function useTranslator() {
    const app = useApp();
    return app.localization.translator;
}
