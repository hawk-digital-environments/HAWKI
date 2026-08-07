import type z from 'zod';
import {globModuleLoader} from '$lib/utils/globModuleLoader.js';
import type {HawkiResourceSchemas} from '$lib/kernel/extendableTypes.js';

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
