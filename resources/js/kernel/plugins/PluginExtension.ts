import type {HawkiApp, HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiPlugin, HawkiPluginContext, HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {PluginBootstrapper} from '$lib/kernel/plugins/PluginBootstrapper.js';
import type {HawkiPlugins} from '$lib/kernel/extendableTypes.js';
import {ResourceSchemaExtension} from '$lib/kernel/resources/ResourceSchemaExtension.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        plugins: WithoutAppExtensionInternals<PluginExtension>;
    }
}

/**
 * App extension that discovers, registers, and drives the lifecycle of all
 * `HawkiPlugin`s (see `$lib/kernel/plugins/types.js`).
 *
 * Plugins are the primary way features are added to HAWKI: instead of every
 * subsystem (stores, routes, resource schemas, config schemas, migrations,
 * modules, boot logic) reaching into the app directly, a plugin implements
 * the relevant lifecycle methods and this extension calls them at the right
 * time, in the right order, relative to the rest of the app's bootstrap.
 *
 * Built-in plugins are auto-discovered from `$lib/plugins/**\/*.plugin.ts`
 * (each file's `default` export must be a class implementing `HawkiPlugin`
 * with a non-empty `name`); see `resources/js/plugins/core/core.plugin.ts`
 * for the reference implementation. Access this extension at runtime via
 * `app.plugins` (exposed through {@link provideProperties}).
 *
 * The actual per-stage dispatching (init, extensions, resourceSchemas, ...)
 * is delegated to {@link PluginBootstrapper}, available here as `.bootstrapper`
 * once `init()` has run.
 */
export class PluginExtension implements HawkiAppExtension {
    private readonly pluginNames = new Set<string>();
    private readonly plugins = new Set<HawkiPluginWithMetadata>();
    private _bootstrapper: PluginBootstrapper | null = null;

    /** Names of all registered plugins (built-in and, eventually, installed). */
    public get names(): string[] {
        return Array.from(this.pluginNames);
    }

    /** All registered plugin instances, each carrying its `isCorePlugin` metadata flag. */
    public get all(): HawkiPluginWithMetadata[] {
        return Array.from(this.plugins.values());
    }

    /** The {@link PluginBootstrapper} that drives plugin lifecycle calls. Throws if accessed before `init()` has run. */
    public get bootstrapper(): PluginBootstrapper {
        if (!this._bootstrapper) {
            throw new Error('PluginExtension bootstrapper is not initialized yet. Call init() first.');
        }
        return this._bootstrapper;
    }

    /** Whether a plugin with the given name is registered. */
    public has(name: string): boolean {
        return this.pluginNames.has(name);
    }

    /**
     * Looks up a registered plugin by name, throwing if it isn't registered.
     * Pass a name from {@link HawkiPlugins} (e.g. `'core'`) to get the
     * concrete, typed plugin class instead of the generic
     * `HawkiPluginWithMetadata`:
     * @example
     * const core = app.plugins.get('core'); // typed as CorePlugin
     */
    public get(name: string): HawkiPluginWithMetadata;
    public get<N extends keyof HawkiPlugins>(name: N): HawkiPlugins[N];
    public get(name: string): HawkiPluginWithMetadata {
        if (!this.has(name)) {
            throw new Error(`Plugin with name '${name}' is not registered.`);
        }
        return Array.from(this.plugins.values()).find(plugin => plugin.name === name)!;
    }

    /** Discovers every `$lib/plugins/**\/*.plugin.ts` file and registers its default-exported class as a core plugin. */
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

    /** Placeholder for auto-registering third-party (non-core) plugins once the backend can announce them; currently a no-op. */
    private autoRegisterInstalledPlugins() {
        // @todo When we know how the backend registers plugins, we can implement this method to auto-register third-party plugins.
    }

    /**
     * Discovers all plugins, then runs the earliest plugin lifecycle steps
     * synchronously with the rest of app assembly (before `client`'s
     * `ClientExtension` requires it — hence `client` must already be
     * registered on `app` by this point): `init()`, `extensions()` (which lets
     * plugins register further `HawkiAppExtension`s via `app.addExtension`),
     * and `resourceSchemas()`. Later lifecycle steps (config schemas, stores,
     * routes, modules, migrations, boot, ready) are triggered by the
     * respective owning extensions calling into `this.bootstrapper` directly.
     */
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
        await this.bootstrapper.runExtensions(app);
        await this.bootstrapper.runResourceSchemas((app.getOrFail('resourceSchemas') as ResourceSchemaExtension).registrar);
    }

    /**
     * Schedules the remaining plugin-driven boot work against the
     * `Bootstrapper` stages: `plugin.boot()` runs right after the
     * `preparation` stage passes (config/connection data is available by
     * then), and `plugin.ready()` runs as soon as the `finalization` stage is
     * reached (just before the Svelte app mounts).
     */
    public async ready(app: HawkiApp, bootstrapper: Bootstrapper): Promise<void> {
        bootstrapper.onStagePassed('preparation', () => this.bootstrapper.runBoot(app));
        bootstrapper.onStageReached('finalization', () => this.bootstrapper.runReady(app));
    }

    /** Exposes this extension as `app.plugins`. */
    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get plugins() {
                return extension;
            }
        };
    }
}

/** Attaches the readonly `isCorePlugin` metadata flag to a plugin instance, turning a plain `HawkiPlugin` into a `HawkiPluginWithMetadata`. */
function assignPluginMetadata(plugin: HawkiPlugin, isCorePlugin: boolean): HawkiPluginWithMetadata {
    Object.defineProperty(plugin, 'isCorePlugin', {
        value: isCorePlugin,
        writable: false,
        enumerable: true,
        configurable: false
    });
    return plugin as HawkiPluginWithMetadata;
}
