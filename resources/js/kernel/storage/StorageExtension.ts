import type {
    HawkiAppExtension,
    WithoutAppExtensionInternals
} from '$lib/kernel/HawkiApp.js';

export interface ClientStorage {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
}

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly localStorage: WithoutAppExtensionInternals<StorageExtension>;
    }
}

/**
 * Safe, injectable access to browser-local persistence.
 *
 * Browser storage can throw when it is disabled, full, or unavailable. Keeping
 * that behaviour behind an app extension gives stores one consistent fallback
 * and lets tests inject an in-memory implementation.
 */
export class StorageExtension implements HawkiAppExtension, ClientStorage {
    public constructor(
        private readonly storage: ClientStorage | null = resolveBrowserStorage()
    ) {
    }

    public getItem(key: string): string | null {
        try {
            return this.storage?.getItem(key) ?? null;
        } catch {
            return null;
        }
    }

    public setItem(key: string, value: string): void {
        try {
            this.storage?.setItem(key, value);
        } catch {
            // The in-memory state remains authoritative for this page.
        }
    }

    public removeItem(key: string): void {
        try {
            this.storage?.removeItem(key);
        } catch {
            // Nothing else is required when persistence is unavailable.
        }
    }

    public provideProperties(): Record<string, unknown> {
        return {localStorage: this};
    }
}

function resolveBrowserStorage(): ClientStorage | null {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
}
