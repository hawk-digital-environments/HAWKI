import type {z} from 'zod';
import {globModuleLoader} from '$lib/utils/globModuleLoader.js';
import type {HawkiConfigSchemas} from '$lib/kernel/extendableTypes.js';

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
