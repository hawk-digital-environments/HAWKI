import type {HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';

/**
 * A HAWKI feature module — the unit registered with the {@link ModuleExtension}.
 *
 * A module's job is route bundling: everything one logical feature needs behind
 * a single `name`, auto-prefixed with the plugin's namespace by
 * `moduleRegistrar.ts`. The kernel stores modules under
 * `${pluginName}:${module.name}` (so two plugins can't collide).
 *
 * A module's *visible* presence (module selector entry, sidebar panel) is not
 * declared here — plugins contribute those via the sidebar collector events
 * (see `$lib/app/ui/sidebarHooks.ts`) from their `hooks()` lifecycle hook.
 */
export interface HawkiModule {
    readonly name: string;

    /**
     * Register the module's routes with the given {@link RouteRegistrar}.
     * The registrar is already scoped under the module's group, so declare paths
     * relative to the module, not the plugin.
     */
    routes?(registrar: RouteRegistrar): void | Promise<void>;
}

/** A {@link HawkiModule} paired with the {@link HawkiPlugin} that registered it,
 *  as stored by the {@link ModuleExtension}. */
export interface HawkiModuleWithPlugin extends HawkiModule {
    readonly plugin: HawkiPluginWithMetadata;
}

/** A module of a core plugin that keeps its plugin name in route prefixes
 *  (see {@link getModuleRoutePrefix}). */
export interface HawkiCoreModule extends HawkiModule {
    readonly pluginNameInRoutes?: boolean;
}

export interface HawkiCoreModuleWithPlugin extends HawkiCoreModule {
    readonly plugin: HawkiPluginWithMetadata;
}
