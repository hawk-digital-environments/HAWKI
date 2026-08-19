import { ModuleRegistrar } from "$lib/kernel/modules/moduleRegistrar";
import {
    HawkiCorePlugin,
    HawkiPluginContext,
    HawkiPluginContextWithConfig
} from "$lib/kernel/plugins/types";
import {ResourceSchemaRegistrar} from "$lib/kernel/resources/resourceSchemaRegistrar";
import {StoreRegistrar} from "$lib/kernel/stores/storeRegistrar";
import {DashboardModule} from "$plugins/assistants/modules/dashboard/DashboardModule";
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
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

export default class AssistantsPlugin implements HawkiCorePlugin {

    readonly name = 'assistants';


    resourceSchemas?(registrar: ResourceSchemaRegistrar, context: HawkiPluginContext): void | Promise<void> {
        registrar.add('assistants', AssistantsSchema);
        // Was registered as 'assistant-avatar' (singular) — the backend resource
        // type is plural (`AssistantAvatarSchema::type()`); fixed while moving.
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

    public routes(registrar: RouteRegistrar): void | Promise<void> {

    }


    stores?(registrar: StoreRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void> {
        // The builder's draft state is a BuilderContext now (created/owned by
        // modules/builder/pages/advance/builderLayout.svelte), not a store — only
        // the still-app-wide assistant options list is registered here.
        registrar.add(assistantOptionsStore);
    }

    // ready(app){
    //     app.router.debug()
    // }

}
