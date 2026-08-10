import type {z} from 'zod';
import {globModuleLoader} from '$lib/utils/globModuleLoader.js';
import type {HawkiConfigSchemas} from '$lib/kernel/extendableTypes.js';

/**
 * Registrar builder for the {@link ConfigurationExtension}.
 *
 * The config layer follows the kernel's extension+registrar pattern: the
 * extension owns the schema `Map` and hands a registrar into plugin
 * `configSchemas()` lifecycle hooks so plugins can register their own
 * namespaced Zod schemas. Each schema validates one slice of the server-provided
 * `configs` resource under a string namespace (e.g. `'hawki-core'`, `'locale'`).
 *
 * `add` is the manual, type-safe entry — pass a `keyof HawkiConfigSchemas` and the
 * value is checked against the augmented interface (schemas augment
 * `HawkiConfigSchemas` via declaration merging, see `kernel/extendableTypes.ts`).
 * `addFromModules` is the glob-powered helper used by the extension itself: it
 * eager-loads `$lib/app/schemas/config/*.schema.{ts,js}`, derives the namespace
 * from the filename (`<namespace>.schema.ts`), reads the `default` / `schema`
 * export, and validates each module exposes a Zod `parse` function before
 * registering it.
 */
export function createConfigSchemaRegistrar(
    registry: Map<string, z.ZodTypeAny>
) {
    function add<R extends keyof HawkiConfigSchemas>(namespace: R, schema: z.ZodType<HawkiConfigSchemas[R]>): void;
    function add(namespace: string, schema: z.ZodTypeAny): void;
    function add(namespace: string, schema: z.ZodTypeAny): void {
        registry.set(namespace, schema);
    }

    function addFromModules(modules: Record<string, z.ZodTypeAny>) {
        const schemas = globModuleLoader(
            modules,
            {
                keyResolver: (filename: string) => {
                    const match = filename.match(/\/([\w-]+)\.schema\.(ts|js)$/);
                    if (!match) {
                        throw new Error(`Invalid config schema filename: ${filename}, expected format: <namespace>.schema.ts or <namespace>.schema.js`);
                    }
                    return match[1];
                },
                valueKey: ['default', 'schema'],
                validate: (module: Partial<z.ZodTypeAny> | undefined) => {
                    return !!module && 'parse' in module;
                }
            }
        );

        for (const [namespace, schema] of Object.entries(schemas)) {
            add(namespace, schema);
        }
    }

    return {
        add,
        addFromModules
    };
}

export type ConfigSchemaRegistrar = ReturnType<typeof createConfigSchemaRegistrar>;
