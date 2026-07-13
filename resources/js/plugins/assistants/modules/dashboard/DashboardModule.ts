import {HawkiCoreModule, HawkiModule} from "$lib/kernel/modules/types";
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Component} from 'svelte';
import BotIcon from '$lib/components/ui/icons/iconset/BotIcon.svelte';
import AssistantsSidebar from '$plugins/assistants/components/AssistantsSidebar.svelte';

export class DashboardModule implements HawkiCoreModule {
    public readonly name = 'dashboard';
    public readonly pluginNameInRoutes = true;

    routes(registrar: RouteRegistrar): void | Promise<void> {
        // Landing route for the module itself (the module selector navigates
        // to the module's prefix, e.g. /assistants/dashboard) — renders the
        // store page directly.
        registrar.lazyRoute(
            '/',
            () => import('$plugins/assistants/modules/dashboard/pages/store/page.svelte'),
            {name: "assistants.dashboard.index"}
        );
        registrar.lazyRoute(
            '/store',
            () => import('$plugins/assistants/modules/dashboard/pages/store/page.svelte'),
            {name: "assistants.dashboard.store"}
        );
        registrar.lazyRoute(
            '/drafts',
            () => import('$plugins/assistants/modules/dashboard/pages/drafts/page.svelte'),
            {name: "assistants.dashboard.drafts"}
        );
        registrar.lazyRoute(
            '/favourites',
            () => import('$plugins/assistants/modules/dashboard/pages/favourites/page.svelte'),
            {name: "assistants.dashboard.favourites"}
        );
        registrar.lazyRoute(
            '/shared',
            () => import('$plugins/assistants/modules/dashboard/pages/shared/page.svelte'),
            {name: "assistants.dashboard.shared"}
        );

        registrar.lazyRoute(
            `/:id`,
            () => import('$plugins/assistants/modules/dashboard/pages/detail/page.svelte'),
            {name: "assistants.dashboard.details"});

    }

    public title(translate: Translator['translate'], _locale: Locale): string {
        return translate('assistants.assistants');
    }

    public icon(_locale: Locale): string | IconComponent | Component {
        return BotIcon;
    }

    public sidebar(_locale: Locale): Component {
        return AssistantsSidebar;
    }
}
