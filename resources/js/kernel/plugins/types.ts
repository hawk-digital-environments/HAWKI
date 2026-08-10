import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {PluginExtension} from '$lib/kernel/plugins/PluginExtension.js';
import type {HawkiClient} from '$lib/kernel/client/dummyClient.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';
import type {RouteRegistrar} from '$lib/kernel/routing/RouteRegistrar.js';
import type {ConfigSchemaRegistrar} from '$lib/kernel/config/configSchemaRegistrar.js';
import type {ResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';
import type {ModuleRegistrar} from '$lib/kernel/modules/moduleRegistrar.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';

// @todo if configuration extension changes, it could become part of HawkiPluginContext (as it is basically a part of the client).

export interface HawkiPluginContext {
    client: HawkiClient;
    bootstrapper: Bootstrapper;
    plugins: PluginExtension;
}

export interface HawkiPluginContextWithConfig extends HawkiPluginContext {
    config: HawkiAppExtensions['config'];
}

export type AppExtensionRegistrar = Pick<HawkiApp, 'addExtension'>;

export interface HawkiPlugin {
    readonly name: string;

    init?(context: HawkiPluginContext): void | Promise<void>;

    extensions?(registrar: AppExtensionRegistrar, context: HawkiPluginContext): void | Promise<void>;

    resourceSchemas?(registrar: ResourceSchemaRegistrar, context: HawkiPluginContext): void | Promise<void>;

    configSchemas?(registrar: ConfigSchemaRegistrar, context: HawkiPluginContext): void | Promise<void>;

    modules?(registrar: ModuleRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    routes?(registrar: RouteRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    stores?(registrar: StoreRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    // Executed after all plugins have been initialized and all app extensions are loaded.
    // Still quite early in the app lifecycle. Triggered after {@link Bootstrapper.onEarlyStage} is done.
    // Stores are not yet populated, this will be done in the "main" boot stage.
    boot?(app: HawkiApp, context: HawkiPluginContextWithConfig): void | Promise<void>;

    // Executed after the app is fully bootstrapped and ready to be used.
    // Triggered at the beginning of the {@link Bootstrapper.onFinalizationStage} stage,
    // Before the Svelte app is being mounted.
    ready?(app: HawkiApp, context: HawkiPluginContextWithConfig): void | Promise<void>;
}

export interface HawkiCorePlugin extends HawkiPlugin {
    migrations?(registrar: MigrationRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;
}

export interface HawkiPluginWithMetadata extends HawkiPlugin {
    readonly isCorePlugin: boolean;
}
