import z from 'zod';
import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiResourceSchemas} from '$lib/kernel/extendableTypes.js';
import {createResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly resourceSchemas: Omit<WithoutAppExtensionInternals<ResourceSchemaExtension>, 'registrar'>;
    }
}

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
