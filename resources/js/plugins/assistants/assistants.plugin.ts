import { ModuleRegistrar } from "$lib/kernel/modules/moduleRegistrar";
import type {RouteRegistrar} from "$lib/components/ui/routing/index.js";
import {
    HawkiPlugin,
    HawkiPluginContext,
    HawkiPluginContextWithConfig
} from "$lib/kernel/plugins/types";
import {ResourceSchemaRegistrar} from "$lib/kernel/resources/resourceSchemaRegistrar";
import {StoreRegistrar} from "$lib/kernel/stores/storeRegistrar";
import {DashboardModule} from "$plugins/assistants/modules/dashboard/DashboardModule";
import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte";
import AssistantsSchema from "$plugins/assistants/api/schemas/resources/assistants.schema";
import AssistantAvatarsSchema from "$plugins/assistants/api/schemas/resources/assistant-avatars.schema";
import AssistantFeedbackSchema from "$plugins/assistants/api/schemas/resources/assistant-feedback.schema";
import AssistantCategoriesSchema from "$plugins/assistants/api/schemas/resources/assistant-categories.schema";
import AssistantTagsSchema from "$plugins/assistants/api/schemas/resources/assistant-tags.schema";
import AssistantSettingsSchema from "$plugins/assistants/api/schemas/resources/assistant-settings.schema";
import {BuilderModule} from "$plugins/assistants/modules/builder/BuilderModule";


declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiPlugins {
        assistants: AssistantsPlugin;
    }
}

export default class AssistantsPlugin implements HawkiPlugin {

    readonly name = 'assistants';


    public resourceSchemas(registrar: ResourceSchemaRegistrar, context: HawkiPluginContext): void | Promise<void> {
        registrar.add('assistants', AssistantsSchema);
        registrar.add('assistant-avatars', AssistantAvatarsSchema);
        registrar.add('assistant-feedback', AssistantFeedbackSchema)
        registrar.add('assistant-categories', AssistantCategoriesSchema)
        registrar.add('assistant-tags', AssistantTagsSchema)
        registrar.add('assistant-settings', AssistantSettingsSchema)
    }
    public modules({add}: ModuleRegistrar): void | Promise<void> {
        add(new DashboardModule());
        add(new BuilderModule())
    }

    /**
     * The plugin prefix `/assistants` as an entry point: redirects to the
     * dashboard module (the plugin's canonical landing page) via the
     * loader-redirect page pattern. Plugin-level routes register under
     * `getPluginRoutePrefix` (see `PluginBootstrapper.runRoutes`), which is
     * `''` for core plugins like this one — hence the literal `/assistants`
     * path here, landing exactly on the bare prefix the modules live below.
     */
    public routes(registrar: RouteRegistrar): void | Promise<void> {
        registrar.lazyRoute(
            '/assistants',
            () => import('$plugins/assistants/pages/Index.svelte'),
            {name: 'assistants.index'}
        );
    }

    public stores(registrar: StoreRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void> {
        // The builder's draft state is a BuilderContext now (created/owned by
        // modules/builder/pages/advance/builderLayout.svelte), not a store — only
        // the still-app-wide assistant options list is registered here.
        registrar.add(assistantOptionsStore);
    }

    // ready(app){
    //     app.router.debug()
    // }

}
