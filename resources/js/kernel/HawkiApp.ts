import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';

/**
 * Contract implemented by every extension that assembles the `HawkiApp`.
 *
 * An extension is a self-contained subsystem (config, client, stores,
 * routing, plugins, ...) that plugs into the app during {@link createApp}.
 * Its `provideProperties()` return value is written onto the app object via
 * `Object.defineProperties`, so whatever keys it returns become real
 * properties on `app` (e.g. `app.stores`, `app.plugins`). To make those
 * properties known to TypeScript, the extension augments the
 * {@link HawkiAppExtensions} interface via declaration merging:
 *
 * @example
 * declare module '$lib/kernel/extendableTypes.js' {
 *     interface HawkiAppExtensions {
 *         stores: WithoutAppExtensionInternals<StoreExtension>;
 *     }
 * }
 *
 * export class StoreExtension implements HawkiAppExtension {
 *     public init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper) { ... }
 *     public provideProperties() {
 *         const extension = this;
 *         return {get stores() { return extension; }};
 *     }
 * }
 *
 * Lifecycle: `init()` runs once, in registration order, while the app is
 * still being assembled (extensions registered later are not yet available
 * as properties, but can be fetched via `app.getOrFail(...)` if they were
 * registered earlier, or awaited via the `Bootstrapper` stages). `ready()`
 * runs once every extension has been added and the app is considered
 * complete; use it to wire up cross-extension behavior that needs the full
 * app surface. Both hooks are optional — an extension that only contributes
 * static properties (no setup) can omit them.
 */
export type HawkiAppExtension = {
    /** Returns the property descriptors to merge onto the app object. Called once, right after `init()`. */
    provideProperties(): Record<string, any>;
    /** Runs while the app is still being assembled; may register more extensions or bootstrapper hooks. */
    init?(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void | Promise<void>;
    /** Runs once every extension has been added and the app is fully assembled. */
    ready?(app: HawkiApp, bootstrapper: Bootstrapper): void | Promise<void>;
};

/**
 * Strips the `HawkiAppExtension` lifecycle members (`init`, `ready`,
 * `provideProperties`) from an extension's type, leaving only its public
 * surface. Used when augmenting {@link HawkiAppExtensions} so that e.g.
 * `app.stores` exposes `StoreExtension`'s public API without also exposing
 * its internal lifecycle hooks.
 */
export type WithoutAppExtensionInternals<T extends HawkiAppExtension> = Omit<T, keyof HawkiAppExtension>;

interface StaticHawkiApp {
    addExtension(extension: HawkiAppExtension): Promise<void>;
}

/**
 * The fully assembled application object. Its properties are entirely
 * contributed by extensions (via declaration merging into
 * {@link HawkiAppExtensions}) — this interface has no members of its own
 * beyond `addExtension`. Obtain the instance via `createApp()` at the entry
 * point (see `resources/js/app.ts`), or via `getHawkiApp()` from legacy code.
 */
export interface HawkiApp extends HawkiAppExtensions, StaticHawkiApp {
}

/**
 * The app object as it exists *during* assembly, before every extension has
 * registered its properties. Extension properties are all optional here
 * because not every extension may have run yet; use {@link getOrFail} to
 * access an extension that is required to already be registered (e.g.
 * because it is earlier in the `extensions` array passed to {@link createApp}).
 */
export type UnfinishedHawkiApp = StaticHawkiApp & Partial<HawkiAppExtensions> & {
    /**
     * Convenience method to get an extension from the app, throwing an error if it is not registered.
     * This is useful for accessing extensions on the unfinished app during the init phase;
     * without having to check for their existence first.
     */
    getOrFail<K extends keyof HawkiAppExtensions>(extensionName: K): HawkiAppExtensions[K];
};

/**
 * Assembles the `HawkiApp` singleton from a list of extensions.
 *
 * Extensions are added in array order: for each one, `init()` runs first
 * (with the still-`UnfinishedHawkiApp`), then its `provideProperties()` are
 * merged onto the app object. Once every extension in the initial list has
 * been added this way, `isReady` flips to `true` and `ready()` is called on
 * every extension (in the same order).
 *
 * Extensions may register *further* extensions from within their own
 * `init()` (this is how `PluginExtension` lets plugins contribute app
 * extensions) — such nested registrations are queued and processed after the
 * current batch, before `isReady` is set, so they still go through the full
 * `init` → `provideProperties` cycle before `ready()` runs on anyone.
 *
 * Called once at the entry point:
 * @example
 * const bootstrapper = new Bootstrapper();
 * const app = await createApp(bootstrapper, [
 *     new ResourceSchemaExtension(),
 *     new ClientExtension(),
 *     new PluginExtension(),
 *     // ...
 * ]);
 */
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
