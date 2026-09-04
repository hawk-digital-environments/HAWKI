import type { z } from 'zod';
import { globModuleLoader } from '$lib/utils/globModuleLoader.js';
import type { HawkiUserSettingsSchemas } from '$lib/kernel/extendableTypes.js';

/**
 * Registrar builder for the {@link UserSettingsExtension}.
 *
 * The user-settings layer follows the kernel's extension+registrar pattern:
 * the extension owns the schema `Map` and hands a registrar that populates
 * it via {@link addFromModules} (eager glob of
 * `$lib/app/schemas/user-settings/*.schema.{ts,js}`). Each schema validates
 * one namespace resource — the resource id *is* the namespace, and the
 * schema shape mirrors the JSON:API attributes (public keys → typed values,
 * e.g. `{core: {locale, theme, timezone}}`).
 *
 * App-owned namespaces register via the eager glob (the extension calls
 * `addFromModules` on `$lib/app/schemas/user-settings/*.schema.{ts,js}`);
 * plugins register through the `settingSchemas()` lifecycle hook, which
 * `UserSettingsExtension.init()` runs via
 * `PluginBootstrapper.runSettingSchemas(registrar)` — mirroring the config
 * layer's `configSchemas()` / `runConfigSchemas(registrar)`.
 *
 * `add` is the manual, type-safe entry — pass a `keyof HawkiUserSettingsSchemas`
 * and the value is checked against the augmented interface. `addFromModules`
 * eagerly loads every matching file, derives the namespace from the filename
 * (`<namespace>.schema.ts`), reads the `default` / `schema` export, and
 * validates each module exposes a Zod `parse` function.
 */
export function createUserSettingsSchemaRegistrar(registry: Map<string, z.ZodTypeAny>) {
    function add<R extends keyof HawkiUserSettingsSchemas>(
        namespace: R,
        schema: z.ZodType<HawkiUserSettingsSchemas[R]>
    ): void;
    function add(namespace: string, schema: z.ZodTypeAny): void;
    function add(namespace: string, schema: z.ZodTypeAny): void {
        registry.set(namespace, schema);
    }

    function addFromModules(modules: Record<string, z.ZodTypeAny>) {
        const schemas = globModuleLoader(modules, {
            keyResolver: (filename: string) => {
                const match = filename.match(/\/([\w-]+)\.schema\.(ts|js)$/);
                if (!match) {
                    throw new Error(
                        `Invalid user-settings schema filename: ${filename}, expected format: <namespace>.schema.ts or <namespace>.schema.js`
                    );
                }
                return match[1];
            },
            valueKey: ['default', 'schema'],
            validate: (module: Partial<z.ZodTypeAny> | undefined) => {
                return !!module && 'parse' in module;
            }
        });

        for (const [namespace, schema] of Object.entries(schemas)) {
            add(namespace, schema);
        }
    }

    return {
        add,
        addFromModules
    };
}

export type UserSettingsSchemaRegistrar = ReturnType<typeof createUserSettingsSchemaRegistrar>;
