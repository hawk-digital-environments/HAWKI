/**
 * Namespaced application config loaded once from the API and cached for the
 * lifetime of the page.
 *
 * Config is split into namespaces (e.g. `'hawki-core'`, `'ai'`) so that each
 * feature module owns and validates its own slice. The raw data for all
 * namespaces arrives in a single API call; parsing is deferred until the first
 * {@link getConfig} call for that namespace.
 *
 * **How to add a new config namespace:**
 *
 * The namespace is inferred from the filename when using {@link autoRegisterSchemas}, so the schema file for the `'ai'` namespace would be `ai.schema.ts`.
 * The schema file must export a default Zod schema, and the namespace must be registered in {@link HawkiConfigSchemas} via declaration merging (see below).
 *
 * Augment this interface in your module:
 *    ```ts
 *    declare module '$lib/kernel/config/typing.js' {
 *        interface ConfigSchemas {
 *            'my-feature': z.ZodObject<{ enabled: z.ZodBoolean }>;
 *        }
 *    }
 *    ```
 */
export interface HawkiConfigSchemas {
    // Populated by other modules via declaration merging (see above).
}

/**
 * Central registry that connects resource type names (strings like `'connections'`)
 * to their TypeScript types and Zod validation schemas.
 *
 * **How to add a new resource:**
 * 1. In your resource module, augment this interface with your type:
 *    ```ts
 *    declare module '$lib/kernel/resources/typing.js' {
 *        interface ResourceSchemas {
 *            connections: Connection;
 *        }
 *    }
 *    ```
 *
 * After that, `getResourceFromApi('connections', id)` will be fully typed and
 * automatically validated — no extra type assertions needed at the call site.
 */
export interface HawkiResourceSchemas {
    // Populated by other modules via declaration merging (see above).
}

export interface HawkiPlugins {
}

export interface HawkiAppAspects {
}

export interface HawkiDataStores {

}
