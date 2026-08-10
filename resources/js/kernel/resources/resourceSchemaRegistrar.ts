import type z from 'zod';
import {globModuleLoader} from '$lib/utils/globModuleLoader.js';
import type {HawkiResourceSchemas} from '$lib/kernel/extendableTypes.js';

/**
 * Registrar builder for the {@link ResourceSchemaExtension}.
 *
 * Follows the kernel's extension+registrar pattern: the extension owns the
 * schema `Map` and hands this registrar into plugin `resourceSchemas()` hooks
 * so plugins can register their own Zod schemas under the JSON:API resource
 * type string (e.g. `'ai-models'`, `'user-keychain-values'`). Each schema
 * validates one resource's response shape, and the augmented
 * {@link HawkiResourceSchemas} interface (populated by declaration merging on
 * each schema file) makes the `RestApi` typed-resource accessors type-safe.
 *
 * `add` throws on duplicate names — a plugin re-registering a resource type is
 * almost always a bug. `addFromModules` is the glob-powered helper: it loads a
 * `Record<path, unknown>`, derives the resource name from the filename
 * (`<name>.schema.ts`), reads the `default` / `schema` export, and validates
 * each module exposes a Zod `parse` function before registering it.
 */
export function createResourceSchemaRegistrar(
    registry: Map<string, z.ZodTypeAny>
) {
    function add<R extends keyof HawkiResourceSchemas>(name: R, schema: z.ZodType<HawkiResourceSchemas[R]>): void;
    function add(name: string, schema: z.ZodTypeAny): void;
    function add(name: string, schema: z.ZodTypeAny): void {
        if (registry.has(name)) {
            throw new Error(`Resource schema for '${name}' is already registered.`);
        }
        registry.set(name, schema);
    }

    function addFromModules(
        modules: Record<string, unknown>
    ) {
        const schemas = globModuleLoader<unknown, z.ZodTypeAny>(
            modules,
            {
                keyResolver: (filename: string) => {
                    const match = filename.match(/\/([\w-]+)\.schema\.(ts|js)$/);
                    if (!match) {
                        throw new Error(`Invalid resourc schema filename: ${filename}, expected format: <namespace>.schema.ts or <namespace>.schema.js`);
                    }
                    return match[1];
                },
                valueKey: ['default', 'schema'],
                validate: (module) => {
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

export type ResourceSchemaRegistrar = ReturnType<typeof createResourceSchemaRegistrar>;
