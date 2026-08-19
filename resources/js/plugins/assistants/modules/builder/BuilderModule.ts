import {HawkiCoreModule, HawkiModule} from "$lib/kernel/modules/types";
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import {lazyComponent} from '$lib/components/ui/routing/logistics/lazyComponent.js';

export class BuilderModule implements HawkiCoreModule {
    public readonly name = 'builder';
    public readonly pluginNameInRoutes = true;
    routes(registrar: RouteRegistrar): void | Promise<void> {
        registrar.group('/advanced', (builder)=> {
            // @todo: redirect / to /general
            builder.lazyRoute(
                '/general',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/general.svelte'),
                {name: "assistant.builder.general"}
            );
            builder.lazyRoute(
                '/behaviour',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/behaviour.svelte'),
                {name: "assistant.builder.behaviour"}
            );
            builder.lazyRoute(
                '/knowledge',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/knowledge.svelte'),
                {name: "assistant.builder.knowledge"}
            );
            builder.lazyRoute(
                '/model',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/model.svelte'),
                {name: "assistant.builder.model"}
            );
            builder.lazyRoute(
                '/test',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/test.svelte'),
                {name: "assistant.builder.test"}
            );
            builder.lazyRoute(
                '/publish',
                () => import('$plugins/assistants/modules/builder/pages/advanced/sections/publish.svelte'),
                {name: "assistant.builder.publish"}
            );
        }, {
            // Wraps every route above: creates/provides the BuilderContext
            // once and keeps it mounted while navigating between them (see
            // BuilderContext.svelte.ts and builderLayout.svelte).
            layout: lazyComponent(
                async () => (await import('$plugins/assistants/modules/builder/pages/advanced/layout.svelte')).default
            )
        });
    }
}
