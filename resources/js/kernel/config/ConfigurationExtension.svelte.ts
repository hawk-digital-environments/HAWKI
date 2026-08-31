import type {z} from 'zod';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {
    HawkiAppExtension,
    UnfinishedHawkiApp,
    WithoutAppExtensionInternals
} from '$lib/kernel/HawkiApp.js';
import type {HawkiConfigSchemas} from '$lib/kernel/extendableTypes.js';
import {createConfigSchemaRegistrar} from '$lib/kernel/config/configSchemaRegistrar.js';
import {updateObject} from '$lib/utils/objects.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly config: WithoutAppExtensionInternals<ConfigurationExtension>;
    }
}

/** Fetches, validates, and reactively exposes namespaced runtime config. */
export class ConfigurationExtension implements HawkiAppExtension {
    private schemaRegistry = new Map<string, z.ZodTypeAny>();
    private currentConfig = $state<Record<string, Record<string, unknown>> | null>(null);
    private parsedCache = $state<Partial<Record<keyof HawkiConfigSchemas, unknown>>>({});
    private app: UnfinishedHawkiApp | null = null;

    public get namespaces(): (keyof HawkiConfigSchemas)[] {
        return Array.from(this.schemaRegistry.keys()) as (keyof HawkiConfigSchemas)[];
    }

    public get(): z.infer<HawkiConfigSchemas['hawki-core']>;
    public get<N extends keyof HawkiConfigSchemas>(namespace: N): z.infer<HawkiConfigSchemas[N]>;
    public get<N extends keyof HawkiConfigSchemas>(namespace?: N): z.infer<HawkiConfigSchemas[N]> {
        const ns = (namespace ?? 'hawki-core') as N;
        if (ns in this.parsedCache) {
            const cached = this.parsedCache[ns];
            return cached as z.infer<HawkiConfigSchemas[N]>;
        }

        const schema = this.schemaRegistry.get(ns as string);
        if (!schema) {
            throw new Error(`No config schema registered for namespace: ${String(ns)}`);
        }

        const parsed = schema.parse(this.currentConfig?.[ns as string] ?? {});
        this.parsedCache[ns] = parsed;
        return this.parsedCache[ns] as z.infer<HawkiConfigSchemas[N]>;
    }

    public async refresh(): Promise<void> {
        if (!this.app) {
            throw new Error('Configuration has not been initialised.');
        }

        const response: {list?: Record<string, Record<string, unknown>>} =
            await this.app.getOrFail('restApi').getResource('configs', 'public');
        this.currentConfig = response.list ?? null;

        // Preserve the identity of already-returned namespace objects. They are
        // deep state proxies, so components holding `const config = useConfig()`
        // observe refreshed values after an authentication transition.
        for (const namespace of Object.keys(this.parsedCache) as (keyof HawkiConfigSchemas)[]) {
            const schema = this.schemaRegistry.get(namespace as string);
            if (!schema) continue;

            const current = this.parsedCache[namespace];
            const next = schema.parse(this.currentConfig?.[namespace as string] ?? {});
            if (isRecord(current) && isRecord(next)) {
                updateObject(current, next);
            } else {
                this.parsedCache[namespace] = next;
            }
        }
    }

    public async init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): Promise<void> {
        this.app = app;
        app.getOrFail('plugins').bootstrapper.setConfig(this);

        const registrar = createConfigSchemaRegistrar(this.schemaRegistry);
        registrar.addFromModules(import.meta.glob('$lib/app/schemas/config/*.schema.{ts,js}', {eager: true}));
        await app.getOrFail('plugins').bootstrapper.runConfigSchemas(registrar);

        bootstrapper.onPreparationStage(() => this.refresh());
    }

    public provideProperties(): Record<string, unknown> {
        return {config: this};
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}
