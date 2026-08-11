import type {z} from 'zod';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiConfigSchemas} from '$lib/kernel/extendableTypes.js';
import {createConfigSchemaRegistrar} from '$lib/kernel/config/configSchemaRegistrar.js';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.config` (see {@link HawkiAppExtension} / `createApp()` in
 * `kernel/HawkiApp.ts` — the keys returned by {@link ConfigurationExtension.provideProperties}
 * become real properties on the app).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly config: WithoutAppExtensionInternals<ConfigurationExtension>;
    }
}

// @todo I am not 100% sure if this will be migrated to become part of the client itself. However the config extension will remain; may loose some methods, tho!

/**
 * App extension that fetches, validates, and exposes the server's runtime
 * configuration as a set of namespaced, Zod-typed objects.
 *
 * HAWKI ships a single `configs` JSON:API resource whose top-level keys are
 * namespaces (`hawki-core`, `locale`, …), each validated by a Zod schema
 * registered through the {@link ConfigSchemaRegistrar}. Plugins register their
 * own schemas during the bootstrapper's `configSchemas` stage (augmenting
 * {@link HawkiConfigSchemas} via declaration merging); the extension then fetches
 * the raw config on the bootstrapper's `preparation` stage, parses each
 * namespace lazily on first read, and caches the parsed result so repeated
 * `app.config.get('namespace')` calls never re-parse.
 *
 * `get()` defaults to the `'hawki-core'` namespace, so the common case reads as
 * `app.config.get()`. Throws when no schema is registered for the requested
 * namespace.
 */
export class ConfigurationExtension implements HawkiAppExtension {
    private schemaRegistry = new Map<string, z.ZodTypeAny>();
    private currentConfig: Record<string, Record<string, any>> | null = null;
    private parsedCache: Partial<Record<keyof HawkiConfigSchemas, any>> = {};

    public get namespaces(): (keyof HawkiConfigSchemas)[] {
        return Array.from(this.schemaRegistry.keys()) as (keyof HawkiConfigSchemas)[];
    }

    public get(): z.infer<HawkiConfigSchemas['hawki-core']>;
    public get<N extends keyof HawkiConfigSchemas>(namespace: N): z.infer<HawkiConfigSchemas[N]>;
    public get<N extends keyof HawkiConfigSchemas>(namespace?: N): z.infer<HawkiConfigSchemas[N]> {
        const ns = (namespace ?? 'hawki-core') as N;

        if (this.parsedCache[ns]) {
            return this.parsedCache[ns] as z.infer<HawkiConfigSchemas[N]>;
        }

        const schema = this.schemaRegistry.get(ns as string);
        if (!schema) {
            throw new Error(`No config schema registered for namespace: ${ns}`);
        }

        const data = this.currentConfig?.[ns as string] ?? {};
        this.parsedCache[ns] = schema.parse(data);
        return this.parsedCache[ns] as z.infer<HawkiConfigSchemas[N]>;
    }

    public async init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper) {
        app.getOrFail('plugins').bootstrapper.setConfig(this);

        const registrar = createConfigSchemaRegistrar(this.schemaRegistry);

        registrar.addFromModules(import.meta.glob('$lib/app/schemas/config/*.schema.{ts,js}', {eager: true}));
        await app.getOrFail('plugins').bootstrapper.runConfigSchemas(registrar);

        bootstrapper.onPreparationStage(async () => {
            this.currentConfig = (await app.getOrFail('restApi').getResource<any>('configs', 'public')).list ?? null;
            this.parsedCache = {};
        });
    }

    public provideProperties(): Record<string, any> {
        return {
            config: this
        };
    }
}
