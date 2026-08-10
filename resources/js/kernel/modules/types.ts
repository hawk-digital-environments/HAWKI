import type {HawkiPlugin} from '$lib/kernel/plugins/types.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Component} from 'svelte';
import type {RouteRegistrar} from '$lib/kernel/routing/RouteRegistrar.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';

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

    title?(translate: Translator['translate'], locale: Locale): string;

    description?(translate: Translator['translate'], locale: Locale): string;

    icon?(locale: Locale): string | IconComponent | Component;

    routes?(registrar: RouteRegistrar): void | Promise<void>;

    sidebar?(locale: Locale): Component;
}

/** A {@link HawkiModule} paired with the {@link HawkiPlugin} that registered it,
 *  as stored by the {@link ModuleExtension}. */
export interface HawkiModuleWithPlugin extends HawkiModule {
    readonly plugin: HawkiPlugin;
}
