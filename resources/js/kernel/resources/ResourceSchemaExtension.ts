import z from 'zod';
import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiResourceSchemas} from '$lib/kernel/extendableTypes.js';
import {createResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.resourceSchemas` (see {@link HawkiAppExtension} / `createApp()` in
 * `kernel/HawkiApp.ts`). The `registrar` member is omitted from the public
 * surface — registration only happens during bootstrap, never at runtime.
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly resourceSchemas: Omit<WithoutAppExtensionInternals<ResourceSchemaExtension>, 'registrar'>;
    }
}

/**
 * App extension that owns the registry of Zod schemas for the JSON:API
 * resources the app talks to.
 *
 * The `RestApi` validates every resource response against a schema looked up
 * here by name (the resource type string, e.g. `'ai-models'`, `'migrations'`).
 * Plugins register their own resource schemas during the bootstrapper's
 * `resourceSchemas` stage (driven by `PluginBootstrapper`), augmenting the
 * {@link HawkiResourceSchemas} interface via declaration merging so
 * `app.restApi.getResourceCollection('ai-models')` returns a typed
 * `AiModel[]`. The extension itself eager-globs `$lib/app/schemas/resources/*.schema.{ts,js}`
 * on `init` to register foundation schemas; `has`/`get` are the lookup helpers
 * the `RestApi` uses (overloaded to preserve the typed surface when the caller
 * passes a `keyof HawkiResourceSchemas`). Foundation schemas are discovered
 * from `$lib/app/schemas/resources`; feature schemas belong to their plugin's
 * `resourceSchemas()` hook.
 */
export class ResourceSchemaExtension implements HawkiAppExtension {
    private readonly registry = new Map<string, z.ZodTypeAny>();
    public readonly registrar = createResourceSchemaRegistrar(this.registry);

    public get names(): string[] {
        return Array.from(this.registry.keys());
    }

    public has<R extends keyof HawkiResourceSchemas>(name: R): boolean;
    public has(name: string): boolean;
    public has(name: string): boolean {
        return this.registry.has(name);
    }

    public get<R extends keyof HawkiResourceSchemas>(name: R): z.ZodType<HawkiResourceSchemas[R]>;
    public get(name: string): z.ZodTypeAny | undefined;
    public get(name: string): z.ZodTypeAny | undefined {
        return this.registry.get(name);
    }

    public async init() {
        this.registrar.addFromModules(import.meta.glob('$lib/app/schemas/resources/*.schema.{ts,js}', {eager: true}));
    }

    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get resourceSchemas() {
                return extension;
            }
        };
    }
}
