import type {HawkiPlugin} from '$lib/kernel/plugins/types.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Component} from 'svelte';
import type {RouteRegistrar} from '$lib/kernel/routing/RouteRegistrar.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';

export interface HawkiModule {
    readonly name: string;

    title?(translate: Translator['translate'], locale: Locale): string;

    description?(translate: Translator['translate'], locale: Locale): string;

    icon?(locale: Locale): string | IconComponent | Component;

    routes?(registrar: RouteRegistrar): void | Promise<void>;

    sidebar?(locale: Locale): Component;
}

export interface HawkiModuleWithPlugin extends HawkiModule {
    readonly plugin: HawkiPlugin;
}
