import type {HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiModule, HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';
import {createModuleRegistrarFactory} from '$lib/kernel/modules/moduleRegistrar.js';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.modules` (see {@link HawkiAppExtension} / `createApp()` in
 * `kernel/HawkiApp.ts`).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        modules: WithoutAppExtensionInternals<ModuleExtension>;
    }
}

/**
 * App extension that owns the central registry of feature {@link HawkiModule}s.
 *
 * A module is HAWKI's unit of "one logical feature" — it bundles a name, an
 * optional title/description/icon, a set of routes, and an optional sidebar
 * component behind a single key. Plugins register modules during the
 * bootstrapper's `modules` stage (driven by `PluginBootstrapper.runModules`);
 * each module is stored under the globally unique key `${pluginName}:${module.name}`
 * (e.g. `core:chat`) so two plugins can't collide.
 *
 * The extension is just a `Map` plus thin lookup helpers (`has`, `get`, `names`,
 * `all`). It does **not** route anything itself — routes declared on a module
 * are prefixed with the plugin's namespace by the {@link ModuleRegistrar} at
 * registration time and then handed to the {@link RoutingExtension}.
 */
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
