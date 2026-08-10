import type {AppExtensionRegistrar, HawkiCorePlugin, HawkiPluginContext, HawkiPluginContextWithConfig, HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {HawkiApp, UnfinishedHawkiApp} from '$lib/kernel/HawkiApp.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';
import type {RouteRegistrar} from '$lib/kernel/routing/RouteRegistrar.js';
import type {ResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';
import type {ConfigSchemaRegistrar} from '$lib/kernel/config/configSchemaRegistrar.js';
import type {ModuleRegistrar} from '$lib/kernel/modules/moduleRegistrar.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';

export class PluginBootstrapper {
    private _contextWithConfig: HawkiPluginContextWithConfig | null = null;
    private _resourceSchemaRegistrar: ResourceSchemaRegistrar | null = null;

    constructor(
        private readonly plugins: HawkiPluginWithMetadata[],
        private readonly context: HawkiPluginContext
    ) {
    }

    private get contextWithConfig(): HawkiPluginContextWithConfig {
        if (!this._contextWithConfig) {
            throw new Error('PluginBootstrapper: contextWithConfig is not set. Call setConfig() before using this property.');
        }
        return this._contextWithConfig;
    }

    public setConfig(config: HawkiAppExtensions['config']) {
        this._contextWithConfig = {
            ...this.context,
            config
        };
    }

    public runInit() {
        return this.runForEach(plugin => plugin.init?.(this.context));
    }

    public runExtensions(app: UnfinishedHawkiApp) {
        const registrar: AppExtensionRegistrar = {
            addExtension: app.addExtension.bind(app)
        };

        return this.runForEach(plugin => plugin.extensions?.(registrar, this.context));
    }

    public runConfigSchemas(registrar: ConfigSchemaRegistrar) {
        return this.runForEach(plugin => plugin.configSchemas?.(registrar, this.context));
    }

    public runResourceSchemas(registrar: ResourceSchemaRegistrar) {
        return this.runForEach(plugin => plugin.resourceSchemas?.(registrar, this.context));
    }

    public runModules(
        registrarFactory: (plugin: HawkiPluginWithMetadata) => ModuleRegistrar
    ) {
        return this.runForEach(plugin => plugin.modules?.(registrarFactory(plugin), this.contextWithConfig));
    }

    public runMigrations(registrar: MigrationRegistrar) {
        return this.runForEach(plugin => {
            if (!plugin.isCorePlugin) {
                return;
            }
            return (plugin as HawkiCorePlugin).migrations?.(registrar, this.contextWithConfig);
        });
    }

    public runStores(registrar: StoreRegistrar) {
        return this.runForEach(plugin => plugin.stores?.(registrar, this.contextWithConfig));
    }

    public runRoutes(registrar: RouteRegistrar) {
        return this.runForEach(plugin => plugin.routes?.(registrar, this.contextWithConfig));
    }

    public runBoot(app: HawkiApp) {
        return this.runForEach(plugin => plugin.boot?.(app, this.contextWithConfig));
    }

    public runReady(app: HawkiApp) {
        return this.runForEach(plugin => plugin.ready?.(app, this.contextWithConfig));
    }

    private async runForEach(
        callback: (plugin: HawkiPluginWithMetadata) => Promise<void> | void
    ): Promise<void> {
        for (const plugin of this.plugins) {
            try {
                await callback(plugin);
            } catch (error) {
                console.error(`Error while running plugin ${plugin.name}:`, error);
            }
        }
    }
}
