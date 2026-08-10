import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {createContext} from 'svelte';
import {getHawkiApp} from '$lib/legacy/legacy.js';

const [get, set] = createContext<HawkiApp>();

export function provideApp(app: HawkiApp) {
    set(app);
}

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
