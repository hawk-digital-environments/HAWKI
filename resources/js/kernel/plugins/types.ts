import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {PluginExtension} from '$lib/kernel/plugins/PluginExtension.js';
import type {HawkiClient} from '$lib/kernel/client/dummyClient.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';
import type {ConfigSchemaRegistrar} from '$lib/kernel/config/configSchemaRegistrar.js';
import type {ResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar.js';
import type {ModuleRegistrar} from '$lib/kernel/modules/moduleRegistrar.js';
import type {MigrationRegistrar} from '$lib/kernel/migrations/migrationRegistrar.js';
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';
import type {RouteRegistrar} from '@hawk-hhg/hawki-svelte-components';

// @todo if configuration extension changes, it could become part of HawkiPluginContext (as it is basically a part of the client).

/**
 * Data passed to every plugin lifecycle hook that runs before the app config
 * has been resolved (`init`, `extensions`, `resourceSchemas`, `configSchemas`).
 * Gives plugins access to the HTTP client, the shared `Bootstrapper` (to hook
 * into boot stages directly), and the `PluginExtension` itself (e.g. to look
 * up another plugin by name).
 */
export interface HawkiPluginContext {
    client: HawkiClient;
    bootstrapper: Bootstrapper;
    plugins: PluginExtension;
}

/** {@link HawkiPluginContext} plus the resolved `config` extension; passed to hooks that run once config is available (`modules`, `routes`, `stores`, `migrations`, `boot`, `ready`). */
export interface HawkiPluginContextWithConfig extends HawkiPluginContext {
    config: HawkiAppExtensions['config'];
}

/** Narrow view of `HawkiApp` handed to `plugin.extensions()` — plugins may register further `HawkiAppExtension`s but cannot otherwise touch the app. */
export type AppExtensionRegistrar = Pick<HawkiApp, 'addExtension'>;

/**
 * Contract for a HAWKI plugin — the unit of feature composition for the
 * frontend (auth, chat, admin, ...). A plugin implements only the lifecycle
 * hooks it needs; each is optional and called (if present) by
 * {@link PluginBootstrapper}'s matching `run*` method, at the point in the
 * app bootstrap where that concern is resolved (see `PluginBootstrapper` for
 * the full ordering). All hooks receive a `context` object giving access to
 * the client, bootstrapper, and (once resolved) app config.
 *
 * Built-in plugins are auto-discovered from `$lib/plugins/**\/*.plugin.ts`
 * by `PluginExtension`; see `resources/js/plugins/core/core.plugin.ts` for a
 * complete example implementing `name`, `boot`, `migrations`, and `stores`.
 */
export interface HawkiPlugin {
    readonly name: string;

    /** Runs first, before any app extension or resource/config schema exists. Use for setup that has no dependencies on the rest of the app. */
    init?(context: HawkiPluginContext): void | Promise<void>;

    /** Register additional `HawkiAppExtension`s on the app via `registrar.addExtension()`. */
    extensions?(registrar: AppExtensionRegistrar, context: HawkiPluginContext): void | Promise<void>;

    /** Register this plugin's Zod resource schemas (augmenting `HawkiResourceSchemas`) on the registrar. */
    resourceSchemas?(registrar: ResourceSchemaRegistrar, context: HawkiPluginContext): void | Promise<void>;

    /** Register this plugin's Zod config schema (augmenting `HawkiConfigSchemas`) on the registrar. */
    configSchemas?(registrar: ConfigSchemaRegistrar, context: HawkiPluginContext): void | Promise<void>;

    /** Register this plugin's feature modules (bundles of routes + setup) on the registrar. */
    modules?(registrar: ModuleRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    /** Register this plugin's routes on the registrar. */
    routes?(registrar: RouteRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    /** Register this plugin's `DataStore`s on the registrar. */
    stores?(registrar: StoreRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    /**
     * Runs once the `preparation` bootstrap stage has passed (config and
     * connection are available); scheduled via `bootstrapper.onStagePassed('preparation', ...)`
     * in `PluginExtension.ready()`. Stores are not populated yet — that
     * happens on the `main` stage.
     */
    boot?(app: HawkiApp, context: HawkiPluginContextWithConfig): void | Promise<void>;

    /**
     * Runs as soon as the `finalization` bootstrap stage is reached, before
     * the Svelte app mounts; scheduled via `bootstrapper.onStageReached('finalization', ...)`
     * in `PluginExtension.ready()`.
     */
    ready?(app: HawkiApp, context: HawkiPluginContextWithConfig): void | Promise<void>;
}

/** Extends {@link HawkiPlugin} with `migrations` — a hook reserved for core (built-in) plugins, since only they may register app-wide storage migrations. */
export interface HawkiCorePlugin extends HawkiPlugin {
    migrations?(registrar: MigrationRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;
}

/** A plugin instance as stored by `PluginExtension`, tagged with the readonly `isCorePlugin` flag that determines whether its `migrations` hook (if present via {@link HawkiCorePlugin}) is honored. */
export interface HawkiPluginWithMetadata extends HawkiPlugin {
    readonly isCorePlugin: boolean;
}
