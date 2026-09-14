import type {HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';
import type {ModuleSearchRegistrar} from '$lib/kernel/search/types.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';
import type {Component} from 'svelte';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';

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
    visible?(app: import('$lib/kernel/HawkiApp.js').HawkiApp): boolean;

    /**
     * The visible title of the module, e.g. in search scope labels.
     * If not provided, the title falls back to the module's raw name (e.g. `core:chat` → `chat`).
     */
    title?(translate: Translator['translate'], locale: Locale): string;

    /** The visible description of the module. */
    description?(translate: Translator['translate'], locale: Locale): string;

    /**
     * The icon of the module. Can be either a component or a base64-encoded
     * image URL (e.g. `data:image/svg+xml;base64,...`).
     */
    icon?(locale: Locale): string | IconComponent | Component;

    /**
     * Register the module's routes with the given {@link RouteRegistrar}.
     * The registrar is already scoped under the module's group, so declare paths
     * relative to the module, not the plugin.
     */
    routes?(registrar: RouteRegistrar): void | Promise<void>;

    /** Declare search providers synchronously. Runtime callbacks run after stores load. */
    search?(registrar: ModuleSearchRegistrar): void;

    /**
     * Each module can optionally provide a sidebar component that will be rendered in the app's sidebar.
     * The component will be rendered when the module is active (i.e. when the user navigates to a route that belongs to the module).
     */
    sidebar?(locale: Locale): Component;
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
