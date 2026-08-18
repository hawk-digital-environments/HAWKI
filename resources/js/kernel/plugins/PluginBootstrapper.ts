import type {AppExtensionRegistrar, HawkiCorePlugin, HawkiPluginContext, HawkiPluginContextWithConfig, HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {HawkiApp, UnfinishedHawkiApp} from '$lib/kernel/HawkiApp.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';
import type {ResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';
import type {ConfigSchemaRegistrar} from '$lib/kernel/config/configSchemaRegistrar.js';
import type {ModuleRegistrar} from '$lib/kernel/modules/moduleRegistrar.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';
import type {RouteRegistrar} from '@hawk-hhg/hawki-svelte-components';
import {getPluginRoutePrefix} from '$lib/kernel/routing/routeInflection.js';

/**
 * Dispatches each `HawkiPlugin` lifecycle hook (see `$lib/kernel/plugins/types.js`)
 * to every registered plugin, in registration order, isolating failures so
 * one broken plugin can't stop the others from running.
 *
 * One instance is created by `PluginExtension.init()` and exposed as
 * `app.plugins.bootstrapper`. Its `run*` methods are not called all at once —
 * each is invoked by whichever extension owns that concern, at the point in
 * the app/bootstrap lifecycle where it makes sense:
 * - `runInit`, `runExtensions`, `runResourceSchemas` — called directly by
 *   `PluginExtension.init()`, before the rest of the app is assembled.
 * - `runConfigSchemas` — called by `ConfigurationExtension.init()`.
 * - `runMigrations` — called by `MigrationExtension` (core plugins only —
 *   `HawkiCorePlugin.migrations` is not part of the third-party `HawkiPlugin`
 *   contract).
 * - `runModules`, `runStores`, `runRoutes` — called by `ModuleExtension`,
 *   `StoreExtension`, and the routing extension respectively.
 * - `runBoot`, `runReady` — scheduled by `PluginExtension.ready()` against the
 *   `Bootstrapper`'s `preparation`/`finalization` stages.
 *
 * Every hook after `runInit`/`runExtensions`/`runResourceSchemas` needs the
 * parsed app config, which isn't available yet during `PluginExtension.init()`;
 * {@link setConfig} must be called (by `ConfigurationExtension`) before any of
 * those later `run*` methods are used, otherwise `contextWithConfig` throws.
 */
export class PluginBootstrapper {
    private _contextWithConfig: HawkiPluginContextWithConfig | null = null;

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

    /** Extends the base plugin context with the resolved `config` extension. Must be called once (by `ConfigurationExtension`) before any `run*` method that needs `contextWithConfig` (everything except `runInit`/`runExtensions`/`runResourceSchemas`). */
    public setConfig(config: HawkiAppExtensions['config']) {
        this._contextWithConfig = {
            ...this.context,
            config
        };
    }

    /** Calls `plugin.init()` on every plugin — the very first plugin hook, before app extensions or resource schemas exist. */
    public runInit() {
        return this.runForEach(plugin => plugin.init?.(this.context));
    }

    /** Calls `plugin.extensions()`, handing each plugin an `AppExtensionRegistrar` so it can register further `HawkiAppExtension`s via `app.addExtension`. */
    public runExtensions(app: UnfinishedHawkiApp) {
        const registrar: AppExtensionRegistrar = {
            addExtension: app.addExtension.bind(app)
        };

        return this.runForEach(plugin => plugin.extensions?.(registrar, this.context));
    }

    /** Calls `plugin.configSchemas()` so each plugin can register its Zod config schema on the given `ConfigSchemaRegistrar`. */
    public runConfigSchemas(registrar: ConfigSchemaRegistrar) {
        return this.runForEach(plugin => plugin.configSchemas?.(registrar, this.context));
    }

    /** Calls `plugin.resourceSchemas()` so each plugin can register its Zod resource schemas on the given `ResourceSchemaRegistrar`. */
    public runResourceSchemas(registrar: ResourceSchemaRegistrar) {
        return this.runForEach(plugin => plugin.resourceSchemas?.(registrar, this.context));
    }

    /** Calls `plugin.modules()`, creating a fresh `ModuleRegistrar` per plugin via `registrarFactory` (so registered modules are attributed to the correct plugin, e.g. for route prefixing). */
    public runModules(
        registrarFactory: (plugin: HawkiPluginWithMetadata) => ModuleRegistrar
    ) {
        return this.runForEach(plugin => plugin.modules?.(registrarFactory(plugin), this.contextWithConfig));
    }

    /** Calls `plugin.migrations()`, but only for core plugins (`isCorePlugin === true`) — third-party plugins cannot register migrations. */
    public runMigrations(registrar: MigrationRegistrar) {
        return this.runForEach(plugin => {
            if (!plugin.isCorePlugin) {
                return;
            }
            return (plugin as HawkiCorePlugin).migrations?.(registrar, this.contextWithConfig);
        });
    }

    /** Calls `plugin.stores()` so each plugin can register its `DataStore`s on the given `StoreRegistrar`. */
    public runStores(registrar: StoreRegistrar) {
        return this.runForEach(plugin => plugin.stores?.(registrar, this.contextWithConfig));
    }

    /**
     * Calls `plugin.routes()` so each plugin can register its routes on the
     * given `RouteRegistrar`, wrapped in a `registrar.group()` under the
     * plugin's route prefix (see {@link getPluginRoutePrefix} —
     * `/plugins/<plugin>` for third-party plugins, unprefixed for core
     * plugins). Mirrors how `createModuleRegistrar` prefixes module routes.
     */
    public runRoutes(registrar: RouteRegistrar) {
        return this.runForEach(plugin => {
            if (!plugin.routes) {
                return;
            }
            const innerRoutes = plugin.routes.bind(plugin);
            registrar.group(
                getPluginRoutePrefix(plugin.name, plugin.isCorePlugin),
                (groupRegistrar) => innerRoutes(groupRegistrar, this.contextWithConfig),
                {name: `plugin.${plugin.name}`}
            );
        });
    }

    /** Calls `plugin.boot()` on every plugin. Triggered once the `preparation` bootstrap stage has passed; stores are not populated yet at this point. */
    public runBoot(app: HawkiApp) {
        return this.runForEach(plugin => plugin.boot?.(app, this.contextWithConfig));
    }

    /** Calls `plugin.ready()` on every plugin. Triggered when the `finalization` bootstrap stage is reached, just before the Svelte app mounts. */
    public runReady(app: HawkiApp) {
        return this.runForEach(plugin => plugin.ready?.(app, this.contextWithConfig));
    }

    /** Runs `callback` for every plugin in order, catching and logging (not rethrowing) any error so a single failing plugin doesn't block the rest. */
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
