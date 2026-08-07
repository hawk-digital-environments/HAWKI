import type {HawkiApp, HawkiAppAspect, UnfinishedHawkiApp, WithoutAppAspectInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiPlugin, HawkiPluginContext, HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {PluginBootstrapper} from '$lib/kernel/plugins/PluginBootstrapper.js';
import type {HawkiPlugins} from '$lib/kernel/extendableTypes.js';
import {ResourceSchemaAspect} from '$lib/kernel/resources/ResourceSchemaAspect.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppAspects {
        plugins: WithoutAppAspectInternals<PluginAspect>;
    }
}

export class PluginAspect implements HawkiAppAspect {
    private readonly pluginNames = new Set<string>();
    private readonly plugins = new Set<HawkiPluginWithMetadata>();
    private _bootstrapper: PluginBootstrapper | null = null;

    public get names(): string[] {
        return Array.from(this.pluginNames);
    }

    public get all(): HawkiPluginWithMetadata[] {
        return Array.from(this.plugins.values());
    }

    public get bootstrapper(): PluginBootstrapper {
        if (!this._bootstrapper) {
            throw new Error('PluginAspect bootstrapper is not initialized yet. Call init() first.');
        }
        return this._bootstrapper;
    }

    public has(name: string): boolean {
        return this.pluginNames.has(name);
    }

    public get(name: string): HawkiPluginWithMetadata;
    public get<N extends keyof HawkiPlugins>(name: N): HawkiPlugins[N];
    public get(name: string): HawkiPluginWithMetadata {
        if (!this.has(name)) {
            throw new Error(`Plugin with name '${name}' is not registered.`);
        }
        return Array.from(this.plugins.values()).find(plugin => plugin.name === name)!;
    }

    private autoRegisterBuiltInPlugins() {
        const glob = import.meta.glob('$lib/plugins/**/*.plugin.ts', {eager: true});
        for (const [path, module] of Object.entries(glob)) {
            const pluginClass = (module as any).default;
            if (typeof pluginClass !== 'function') {
                console.warn(`Plugin file ${path} does not export a class as default, skipping.`);
                continue;
            }

            const pluginInstance: HawkiPlugin = new pluginClass();
            if (typeof pluginInstance.name !== 'string' || pluginInstance.name.trim() === '') {
                console.warn(`Plugin file ${path} does not have a valid 'name' property, skipping.`);
                continue;
            }

            if (this.has(pluginInstance.name)) {
                console.warn(`Plugin with name '${pluginInstance.name}' is already registered, skipping duplicate from ${path}.`);
                continue;
            }

            this.plugins.add(assignPluginMetadata(pluginInstance, true));
            this.pluginNames.add(pluginInstance.name);
        }
    }

    private autoRegisterInstalledPlugins() {
        // @todo When we know how the backend registers plugins, we can implement this method to auto-register third-party plugins.
    }

    public async init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): Promise<void> {
        this.autoRegisterBuiltInPlugins();
        this.autoRegisterInstalledPlugins();

        const context: HawkiPluginContext = {
            client: app.getOrFail('client'),
            bootstrapper: bootstrapper,
            plugins: this
        };

        this._bootstrapper = new PluginBootstrapper(Array.from(this.plugins.values()), context);
        await this.bootstrapper.runInit();
        await this.bootstrapper.runAspects(app);
        await this.bootstrapper.runResourceSchemas((app.getOrFail('resourceSchemas') as ResourceSchemaAspect).registrar);
    }

    public async ready(app: HawkiApp, bootstrapper: Bootstrapper): Promise<void> {
        bootstrapper.onStagePassed('preparation', () => this.bootstrapper.runBoot(app));
        bootstrapper.onStageReached('finalization', () => this.bootstrapper.runReady(app));
    }

    public provideProperties(): Record<string, any> {
        const aspect = this;
        return {
            get plugins() {
                return aspect;
            }
        };
    }
}

function assignPluginMetadata(plugin: HawkiPlugin, isCorePlugin: boolean): HawkiPluginWithMetadata {
    Object.defineProperty(plugin, 'isCorePlugin', {
        value: isCorePlugin,
        writable: false,
        enumerable: true,
        configurable: false
    });
    return plugin as HawkiPluginWithMetadata;
}
