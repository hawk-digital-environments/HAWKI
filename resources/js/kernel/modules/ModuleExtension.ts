import type {HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiModule, HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';
import {createModuleRegistrarFactory} from '$lib/kernel/modules/moduleRegistrar.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        modules: WithoutAppExtensionInternals<ModuleExtension>;
    }
}

export class ModuleExtension implements HawkiAppExtension {
    private readonly modules = new Map<string, HawkiModuleWithPlugin>();

    public get names(): string[] {
        return Array.from(this.modules.keys());
    }

    public get all(): HawkiModule[] {
        return Array.from(this.modules.values());
    }

    public has(name: string): boolean {
        return this.modules.has(name);
    }

    public get(name: string): HawkiModule {
        const module = this.modules.get(name);
        if (!module) {
            throw new Error(`Module with name "${name}" is not registered.`);
        }
        return module;
    }

    public async init(app: UnfinishedHawkiApp) {
        await app.plugins!.bootstrapper.runModules(createModuleRegistrarFactory(this.modules));
    }

    public provideProperties(): Record<string, any> {
        return {
            modules: this
        };
    }
}
