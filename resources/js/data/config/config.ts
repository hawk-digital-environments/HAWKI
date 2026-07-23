import {getResourceFromApi} from '$lib/data/api/api.js';
import {z} from 'zod';

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
 * The namespace is inferred from the filename when using {@link autoRegisterConfigSchemas}, so the schema file for the `'ai'` namespace would be `ai.schema.ts`.
 * The schema file must export a default Zod schema, and the namespace must be registered in {@link ConfigSchemaRegistry} via declaration merging (see below).
 *
 * Augment this interface in your module:
 *    ```ts
 *    declare module '$lib/data/config/config.js' {
 *        interface ConfigSchemaRegistry {
 *            'my-feature': z.ZodObject<{ enabled: z.ZodBoolean }>;
 *        }
 *    }
 *    ```
 */
export interface ConfigSchemaRegistry {
    // Populated by other modules via declaration merging (see above).
}

let currentConfig: Record<string, Record<string, any>> | null = null;
let parsedConfigCache: Partial<Record<keyof ConfigSchemaRegistry, any>> = {};

/**
 * Fetches all public config from the API and stores it in memory.
 *
 * Must be called once during startup — register it with `runBeforeReady` so
 * it completes before any component tries to read config via {@link getConfig}.
 * Calling this again (e.g. after a settings change) clears the parse cache so
 * subsequent `getConfig` calls re-parse with the fresh data.
 */
export async function loadConfig(): Promise<void> {
    currentConfig = (await getResourceFromApi<any>('configs', 'public')).list ?? null;
    parsedConfigCache = {};
}

const configRegistry: Record<string, z.ZodTypeAny> = {};

/**
 * Registers config schemas by auto-importing all `*.schema.{ts,js}` files in this directory.
 *
 * Each schema file must export a default Zod schema, and the namespace is
 * inferred from the filename (e.g. `ai.schema.ts` → `'ai'` namespace).
 */
export function autoRegisterConfigSchemas(): void {
    const glob = import.meta.glob('../../schemas/config/*.schema.{ts,js}', {eager: true});
    const inferConfigNamespaceFromFilename = (filename: string): string => {
        const match = filename.match(/\/([\w-]+)\.schema\.(ts|js)$/);
        if (!match) {
            throw new Error(`Invalid config schema filename: ${filename}`);
        }
        return match[1];
    };

    for (const [filename, module] of Object.entries(glob)) {
        const namespace = inferConfigNamespaceFromFilename(filename);
        const schema = (module as any).default;
        if (!schema || !('parse' in schema)) {
            console.warn(`Schema file ${filename} does not export a default schema, skipping.`);
            continue;
        }
        configRegistry[namespace] = schema;
    }
}

/**
 * Returns the parsed, validated config for a namespace.
 *
 * The result is cached after the first call, so repeated access is cheap.
 * Throws if the namespace has no registered schema — this is always a programming error, not a runtime condition.
 *
 * {@link loadConfig} must have been called before this is used; otherwise
 * `currentConfig` is `null` and all fields will fall back to their Zod defaults.
 *
 * Calling without arguments returns the `'hawki-core'` config.
 *
 * @example
 * const { locale } = getConfig();               // hawki-core (default)
 * const ai = getConfig('my-feature').something; // specific namespace
 */
export function getConfig(): z.infer<ConfigSchemaRegistry['hawki-core']>;
export function getConfig<N extends keyof ConfigSchemaRegistry>(namespace: N): z.infer<ConfigSchemaRegistry[N]>;
export function getConfig<N extends keyof ConfigSchemaRegistry>(namespace?: N): z.infer<ConfigSchemaRegistry[N]> {
    const ns = (namespace ?? 'hawki-core') as N;

    if (parsedConfigCache[ns]) {
        return parsedConfigCache[ns] as z.infer<ConfigSchemaRegistry[N]>;
    }

    const schema = configRegistry[ns as string];
    if (!schema) {
        throw new Error(`No config schema registered for namespace: ${ns}`);
    }

    const data = currentConfig?.[ns as string] ?? {};
    parsedConfigCache[ns] = schema.parse(data);
    return parsedConfigCache[ns] as z.infer<ConfigSchemaRegistry[N]>;
}
