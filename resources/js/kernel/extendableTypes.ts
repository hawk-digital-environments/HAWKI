/**
 * This file is the central declaration-merging surface for the whole kernel.
 * It exports empty interfaces on purpose — every feature module (plugin,
 * extension, store, schema file) augments one of these interfaces via
 * `declare module '$lib/kernel/extendableTypes.js' { interface X { ... } }`
 * to plug its own type into the shared registry, instead of this file having
 * to know about every module in the codebase. Always import the *type*
 * (`import type {...}`) here; the module has no runtime exports.
 */

/**
 * Namespaced application config loaded once from the API and cached for the
 * lifetime of the page.
 *
 * Config is split into namespaces (e.g. `'hawki-core'`, `'ai'`) so that each
 * feature module owns and validates its own slice. The raw data for all
 * namespaces arrives in a single API call; parsing is deferred until the first
 * {@link ConfigurationExtension.get} call for that namespace.
 *
 * **How to add a new config namespace:**
 *
 * 1. Export a default Zod schema from a `<namespace>.schema.ts` file under
 *    `$lib/app/schemas/config/` (namespace is inferred from the filename by
 *    `createConfigSchemaRegistrar().addFromModules()`, e.g. `ai.schema.ts` →
 *    namespace `'ai'`).
 * 2. Augment this interface next to that schema so `getConfig('ai')` is typed:
 *    ```ts
 *    declare module '$lib/kernel/extendableTypes.js' {
 *        interface HawkiConfigSchemas {
 *            'ai': typeof AiSchema;
 *        }
 *    }
 *    ```
 *
 * See `resources/js/app/schemas/config/hawki-core.schema.ts` for a real example.
 */
export interface HawkiConfigSchemas {
    // Populated by other modules via declaration merging (see above).
}

/**
 * Namespaced per-user settings loaded from the `user-settings` JSON:API
 * resource and kept reactive across authentication transitions.
 *
 * Unlike {@link HawkiConfigSchemas} (global app config), user settings are
 * per-user (or per-session for guests) and **writable** from the frontend
 * via `app.userSettings.save(...)`. Each namespace (e.g. `'hawki-core'`)
 * validates one namespace resource — the resource id *is* the namespace, and
 * its attributes are keyed by each settings class's public key (e.g. `core` →
 * typed `{locale, theme, timezone}`). The schema therefore validates the full
 * namespace resource shape (public keys → typed values), not individual
 * properties.
 *
 * **How to add a new user-settings namespace:**
 *
 * 1. Export a default Zod schema from `<namespace>.schema.ts` under
 *    `$lib/app/schemas/user-settings/` (namespace is inferred from the
 *    filename by `createUserSettingsSchemaRegistrar().addFromModules()`,
 *    e.g. `hawki-core.schema.ts` → namespace `'hawki-core'`).
 * 2. Augment this interface next to that schema:
 *    ```ts
 *    declare module '$lib/kernel/extendableTypes.js' {
 *        interface HawkiUserSettingsSchemas {
 *            'hawki-core': typeof HawkiCoreUserSettingsSchema;
 *        }
 *    }
 *    ```
 *
 * See `resources/js/app/schemas/user-settings/hawki-core.schema.ts` for a
 * real example.
 */
export interface HawkiUserSettingsSchemas {
    // Populated by other modules via declaration merging (see above).
}

/**
 * Central registry that connects resource type names (strings like `'connections'`)
 * to their TypeScript types and Zod validation schemas.
 *
 * **How to add a new resource:**
 * 1. In your resource module, augment this interface with your type:
 *    ```ts
 *    declare module '$lib/kernel/extendableTypes.js' {
 *        interface HawkiResourceSchemas {
 *            connections: Connection;
 *        }
 *    }
 *    ```
 * 2. Export the matching Zod schema as `default` from a `<name>.schema.ts`
 *    file so `resourceSchemaRegistrar`'s `addFromModules()` can pick it up.
 *
 * After that, `getResourceFromApi('connections', id)` will be fully typed and
 * automatically validated — no extra type assertions needed at the call site.
 */
export interface HawkiResourceSchemas {
    // Populated by other modules via declaration merging (see above).
}

/**
 * Registry mapping a plugin's `name` (e.g. `'core'`) to its concrete plugin
 * class. Augmented by each plugin so that `app.plugins.get('core')` returns a
 * typed `CorePlugin` instead of the generic `HawkiPluginWithMetadata`:
 * ```ts
 * declare module '$lib/kernel/extendableTypes.js' {
 *     interface HawkiPlugins {
 *         core: CorePlugin;
 *     }
 * }
 * ```
 * See `resources/js/plugins/core/core.plugin.ts` for a real example.
 */
export interface HawkiPlugins {
}

/**
 * The public surface of `HawkiApp` (see `$lib/kernel/HawkiApp.js`). Every key
 * added here becomes an actual property on the app object at runtime, because
 * each `HawkiAppExtension.provideProperties()` result is written onto `app`
 * via `Object.defineProperties`. An extension augments this interface (using
 * {@link WithoutAppExtensionInternals} to hide its `init`/`ready`/
 * `provideProperties` lifecycle members) so consumers get `app.stores`,
 * `app.plugins`, `app.config`, etc. fully typed:
 * ```ts
 * declare module '$lib/kernel/extendableTypes.js' {
 *     interface HawkiAppExtensions {
 *         stores: WithoutAppExtensionInternals<StoreExtension>;
 *     }
 * }
 * ```
 */
export interface HawkiAppExtensions {
}

/**
 * Registry mapping a data store's `name` (e.g. `'theme'`) to its concrete
 * store class, so `app.stores.get('ai-models')` / `useStore('ai-models')` return a
 * typed `AiModelStore` instead of the generic `DataStore`. Each store augments
 * this interface next to its class definition:
 * ```ts
 * declare module '$lib/kernel/extendableTypes.js' {
 *     interface HawkiDataStores {
 *         'ai-models': AiModelStore;
 *     }
 * }
 * ```
 * See `resources/js/plugins/core/stores/AiModelStore.svelte.ts` for a real example.
 */
export interface HawkiDataStores {

}

/**
 * Events dispatched on `app.events.sync` (run inline on the caller's stack).
 * Augmented by feature modules the same way {@link HawkiAppExtensions} is:
 * ```ts
 * declare module '$lib/kernel/extendableTypes.js' {
 *     interface HawkiSyncEvents { logout: void; }
 * }
 * ```
 * Use the sync pipeline when handlers must block the caller (e.g. clearing
 * local state before a redirect); use {@link HawkiAsyncEvents} when handlers
 * do I/O and the caller should not wait.
 */
export interface HawkiSyncEvents {

}

/** Events dispatched on `app.events.async` (awaited sequentially). See {@link HawkiSyncEvents} for when to pick one over the other. */
export interface HawkiAsyncEvents {

}
