import {z} from 'zod';

/**
 * Central registry that connects resource type names (strings like `'connections'`)
 * to their TypeScript types and Zod validation schemas.
 *
 * **How to add a new resource:**
 * 1. In your resource module, augment this interface with your type:
 *    ```ts
 *    declare module '$lib/data/resources/registry.js' {
 *        interface ResourceSchemaRegistry {
 *            connections: Connection;
 *        }
 *    }
 *    ```
 *
 * After that, `getResourceFromApi('connections', id)` will be fully typed and
 * automatically validated — no extra type assertions needed at the call site.
 */
export interface ResourceSchemaRegistry {
    // Populated by other modules via declaration merging (see above).
}

const resourceRegistry: Record<string, z.ZodTypeAny> = {};

/**
 * Looks up the Zod schema for a resource type. Returns `undefined` if no
 * schema was registered — used by the API helpers to decide whether to
 * validate or pass through the raw response.
 */
export function getResourceSchema(resourceType: string): z.ZodTypeAny | undefined {
    return resourceRegistry[resourceType];
}

/**
 * Registers all available resource schemas by globbing the current directory for `*.schema.{ts,js}` files.
 *
 * Each schema file must export a default Zod schema and be named like `{resourceType}.schema.ts`
 * (e.g. `connections.schema.ts`) so the registry can infer the resource type from the filename.
 *
 * This should be called once during startup to populate the registry before any API calls are made.
 */
export function autoRegisterResourceSchemas(): void {
    const glob = import.meta.glob('../../schemas/resources/*.schema.{ts,js}', {eager: true});
    const inferResourceTypeFromFilename = (filename: string) => {
        const match = filename.match(/\/([\w-]+)\.schema\.(ts|js)$/);
        if (match) {
            return match[1];
        }
        throw new Error(`Invalid schema filename: ${filename}`);
    };

    for (const [filename, module] of Object.entries(glob)) {
        const resourceType = inferResourceTypeFromFilename(filename);
        const schema = (module as any).default;
        if (!schema) {
            console.warn(`Schema file ${filename} does not export a default schema, skipping.`);
            continue;
        }
        resourceRegistry[resourceType as string] = schema;
    }
}
