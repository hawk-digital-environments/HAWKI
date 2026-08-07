import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppAspects} from '$lib/kernel/extendableTypes.js';

export type HawkiAppAspect = {
    provideProperties(): Record<string, any>;
    init?(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void | Promise<void>;
    ready?(app: HawkiApp, bootstrapper: Bootstrapper): void | Promise<void>;
};

export type WithoutAppAspectInternals<T extends HawkiAppAspect> = Omit<T, keyof HawkiAppAspect>;

interface StaticHawkiApp {
    addAspect(aspect: HawkiAppAspect): Promise<void>;
}

export interface HawkiApp extends HawkiAppAspects, StaticHawkiApp {
}

export type UnfinishedHawkiApp = StaticHawkiApp & Partial<HawkiAppAspects> & {
    /**
     * Convenience method to get an aspect from the app, throwing an error if it is not registered.
     * This is useful for accessing aspects on the unfinished app during the init phase;
     * without having to check for their existence first.
     */
    getOrFail<K extends keyof HawkiAppAspects>(aspectName: K): HawkiAppAspects[K];
};

export async function createApp(
    bootstrapper: Bootstrapper,
    aspects: HawkiAppAspect[]
) {
    let isReady = false;
    let isNestedAddAspectCall = false;

    aspects = [...aspects];

    async function addAspect(aspect: HawkiAppAspect) {
        // When an aspect starts to add more aspects while we are not yet ready, we just push it to the queue and return. It will be processed later.
        // This is mainly required for the PluginAspect, which can add more aspects during its init phase.
        if (isNestedAddAspectCall && !isReady) {
            aspects.push(aspect);
            return;
        }

        try {
            isNestedAddAspectCall = true;
            await aspect.init?.(app, bootstrapper);
        } finally {
            isNestedAddAspectCall = false;
        }

        const properties = aspect.provideProperties();

        // Throw a warning if we override any existing properties on the app
        for (const key of Object.keys(properties)) {
            if (key in app) {
                console.warn(`Overriding existing property '${key}' on HawkiApp with aspect '${aspect.constructor.name}'.`);
            }
        }

        Object.defineProperties(app, Object.getOwnPropertyDescriptors(properties));

        if (isReady) {
            await aspect.ready?.(app as HawkiApp, bootstrapper);
        }
    }

    const app = {
        addAspect,
        getOrFail<K extends keyof HawkiAppAspects>(aspectName: K): HawkiAppAspects[K] {
            if (!(aspectName in this)) {
                throw new Error(`Aspect '${String(aspectName)}' is not registered on the app.`);
            }
            return this[aspectName] as HawkiAppAspects[K];
        }
    } as UnfinishedHawkiApp;

    for (const aspect of aspects) {
        await addAspect(aspect);
    }

    isReady = true;

    for (const aspect of aspects) {
        await aspect.ready?.(app as HawkiApp, bootstrapper);
    }

    return app as HawkiApp;
}
