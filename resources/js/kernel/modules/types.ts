import type {HawkiPlugin} from '$lib/kernel/plugins/types.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Component} from 'svelte';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

/**
 * A HAWKI feature module — the unit registered with the {@link ModuleExtension}.
 *
 * A module bundles everything one logical feature needs behind a single
 * `name`: localisable title/description/icon, a set of routes, and an optional
 * sidebar entry. The kernel stores modules under `${pluginName}:${module.name}`
 * (so two plugins can't collide) and auto-prefixes any `routes()` the module
 * declares with the plugin's namespace (see `moduleRegistrar.ts`).
 *
 * All optional members receive the active {@link Locale} so a module can render
 * its label/icon in the user's language. The `routes()` callback receives a
 * {@link RouteRegistrar} scoped under the module's group; declare paths
 * relative to the module, not the plugin.
 */
export interface HawkiModule {
    readonly name: string;

    /**
     * The visible title of the module, for example in the sidebar.
     * If not provided, the title will be inferred from the module's name (e.g. `core:chat` → `Chat`).
     */
    title?(translate: Translator['translate'], locale: Locale): string;

    /**
     * The visible description of the module, for example in the sidebar.
     */
    description?(translate: Translator['translate'], locale: Locale): string;

    /**
     * The icon of the module, for example in the sidebar.
     * If not provided, a default icon will be used.
     * Can be either a component or a base64-encoded Image URL (e.g. `data:image/svg+xml;base64,...`).
     */
    icon?(locale: Locale): string | IconComponent | Component;

    /**
     * Register the module's routes with the given {@link RouteRegistrar}.
     * The registrar is already scoped under the module's group, so declare paths
     * relative to the module, not the plugin.
     */
    routes?(registrar: RouteRegistrar): void | Promise<void>;

    /**
     * Each module can optionally provide a sidebar component that will be rendered in the app's sidebar.
     * The component will be rendered when the module is active (i.e. when the user navigates to a route that belongs to the module).
     * @param locale
     */
    sidebar?(locale: Locale): Component;
}

export interface HawkiCoreModule extends HawkiModule{
    readonly pluginNameInRoutes?: boolean;

}


/** A {@link HawkiModule} paired with the {@link HawkiPlugin} that registered it,
 *  as stored by the {@link ModuleExtension}. */
export interface HawkiModuleWithPlugin extends HawkiModule {

    readonly plugin: HawkiPlugin;
}

export interface HawkiCoreModuleWithPlugin extends HawkiCoreModule {

    readonly plugin: HawkiPlugin;
}

