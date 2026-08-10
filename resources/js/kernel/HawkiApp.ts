import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';

export type HawkiAppExtension = {
    provideProperties(): Record<string, any>;
    init?(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void | Promise<void>;
    ready?(app: HawkiApp, bootstrapper: Bootstrapper): void | Promise<void>;
};

export type WithoutAppExtensionInternals<T extends HawkiAppExtension> = Omit<T, keyof HawkiAppExtension>;

interface StaticHawkiApp {
    addExtension(extension: HawkiAppExtension): Promise<void>;
}

export interface HawkiApp extends HawkiAppExtensions, StaticHawkiApp {
}

export type UnfinishedHawkiApp = StaticHawkiApp & Partial<HawkiAppExtensions> & {
    /**
     * Convenience method to get an extension from the app, throwing an error if it is not registered.
     * This is useful for accessing extensions on the unfinished app during the init phase;
     * without having to check for their existence first.
     */
    getOrFail<K extends keyof HawkiAppExtensions>(extensionName: K): HawkiAppExtensions[K];
};

export async function createApp(
    bootstrapper: Bootstrapper,
    extensions: HawkiAppExtension[]
) {
    let isReady = false;
    let isNestedAddExtensionCall = false;

    extensions = [...extensions];

    async function addExtension(extension: HawkiAppExtension) {
        // When an extension starts to add more extensions while we are not yet ready, we just push it to the queue and return. It will be processed later.
        // This is mainly required for the PluginExtension, which can add more extensions during its init phase.
        if (isNestedAddExtensionCall && !isReady) {
            extensions.push(extension);
            return;
        }

        try {
            isNestedAddExtensionCall = true;
            await extension.init?.(app, bootstrapper);
        } finally {
            isNestedAddExtensionCall = false;
        }

        const properties = extension.provideProperties();

        // Throw a warning if we override any existing properties on the app
        for (const key of Object.keys(properties)) {
            if (key in app) {
                console.warn(`Overriding existing property '${key}' on HawkiApp with extension '${extension.constructor.name}'.`);
            }
        }

        Object.defineProperties(app, Object.getOwnPropertyDescriptors(properties));

        if (isReady) {
            await extension.ready?.(app as HawkiApp, bootstrapper);
        }
    }

    const app = {
        addExtension,
        getOrFail<K extends keyof HawkiAppExtensions>(extensionName: K): HawkiAppExtensions[K] {
            if (!(extensionName in this)) {
                throw new Error(`Extension '${String(extensionName)}' is not registered on the app.`);
            }
            return this[extensionName] as HawkiAppExtensions[K];
        }
    } as UnfinishedHawkiApp;

    for (const extension of extensions) {
        await addExtension(extension);
    }

    isReady = true;

    for (const extension of extensions) {
        await extension.ready?.(app as HawkiApp, bootstrapper);
    }

    return app as HawkiApp;
}
