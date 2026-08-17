import {HawkiCoreModule, HawkiModule} from "$lib/kernel/modules/types";
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

export class DashboardModule implements HawkiCoreModule {
    public readonly name = 'dashboard';
    public readonly pluginNameInRoutes = true;

    routes(registrar: RouteRegistrar): void | Promise<void> {
        registrar.lazyRoute(
            '/store',
            () => import('$plugins/assistants/modules/dashboard/pages/store/page.svelte'),
            {name: "assistant.dashboard.store"}
        );
        registrar.lazyRoute(
            '/my_drafts',
            () => import('$plugins/assistants/modules/dashboard/pages/my_drafts/page.svelte'),
            {name: "assistant.dashboard.my_drafts"}
        );
        registrar.lazyRoute(
            '/favourites',
            () => import('$plugins/assistants/modules/dashboard/pages/favourites/page.svelte'),
            {name: "assistant.dashboard.favourites"}
        );
        registrar.lazyRoute(
            '/shared',
            () => import('$plugins/assistants/modules/dashboard/pages/shared/page.svelte'),
            {name: "assistant.dashboard.shared"}
        );

        registrar.lazyRoute(
            `/:id`,
            () => import('$plugins/assistants/modules/dashboard/pages/detail/page.svelte'),
            {name: "assistants.dashboard.details"});

    }
}
