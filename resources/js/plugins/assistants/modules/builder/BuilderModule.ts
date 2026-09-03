import {HawkiCoreModule, HawkiModule} from "$lib/kernel/modules/types";
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

export class BuilderModule implements HawkiCoreModule {
    public readonly name = 'builder';
    public readonly pluginNameInRoutes = true;
    routes(registrar: RouteRegistrar): void | Promise<void> {
        registrar.group('/advanced', (builder)=> {
            // The group root redirects to /general via its page's `loadData`
            // (see pages/advanced/index.svelte), so the builder is always
            // entered on a concrete section route.
            builder.lazyRoute(
                '/',
                () => import('$plugins/assistants/modules/builder/pages/advanced/index.svelte'),
                {name: "assistants.builder.index"}
            );
            builder.lazyRoute(
                '/general',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/general.svelte'),
                {name: "assistants.builder.general"}
            );
            builder.lazyRoute(
                '/behaviour',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/behaviour.svelte'),
                {name: "assistants.builder.behaviour"}
            );
            builder.lazyRoute(
                '/knowledge',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/knowledge.svelte'),
                {name: "assistants.builder.knowledge"}
            );
            builder.lazyRoute(
                '/model',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/model.svelte'),
                {name: "assistants.builder.model"}
            );
            builder.lazyRoute(
                '/test',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/test.svelte'),
                {name: "assistants.builder.test"}
            );
            builder.lazyRoute(
                '/publish',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/publish.svelte'),
                {name: "assistants.builder.publish"}
            );
        }, {
            // Wraps every route above: creates/provides the BuilderContext
            // once and keeps it mounted while navigating between them (see
            // BuilderContext.svelte.ts and builderLayout.svelte).
            lazyLayout: async () => (await import('$plugins/assistants/modules/builder/pages/advanced/layout.svelte')).default
        });
    }
}
