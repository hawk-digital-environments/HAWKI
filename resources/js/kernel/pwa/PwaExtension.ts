import type {HawkiAppExtension} from '$lib/kernel/HawkiApp.js';

/** Installs offline navigation support for the production Svelte frontend. */
export class PwaExtension implements HawkiAppExtension {
    public init(): void {
        if (import.meta.env.PROD) {
            void registerPwa();
        }
    }

    public provideProperties(): Record<string, never> {
        return {};
    }
}

export async function registerPwa(): Promise<void> {
    // The entry bundle also runs on legacy pages, which have no manifest.
    if (!window.isSecureContext || !('serviceWorker' in navigator) || !document.querySelector('link[rel="manifest"]')) {
        return;
    }

    try {
        await navigator.serviceWorker.register('/sw.js', {scope: '/new', updateViaCache: 'none'});
    } catch (error) {
        // Installation failure must not prevent sign-in or chat startup.
        console.warn('HAWKI offline support could not be registered.', error);
    }
}
