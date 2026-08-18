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
import AssistantsSchema from "$plugins/assistants/api/schemas/assistants.schema";
import {AssistantFeedbackSchema} from "$plugins/assistants/types/assistant";
import {AssistantAvatarSchema} from "$plugins/assistants/types/assistant";
import {AssistantCategorySchema} from "$plugins/assistants/types/assistant/AssistantCategory";
import {AssistantTagSchema} from "$plugins/assistants/types/assistant/AssistantTag";
import {AssistantSettingSchema} from "$plugins/assistants/types/assistant/AssistantSetting";
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
        registrar.add('assistant-avatar', AssistantAvatarSchema);
        registrar.add('assistant-feedback', AssistantFeedbackSchema)
        registrar.add('assistant-categories', AssistantCategorySchema)
        registrar.add('assistant-tags', AssistantTagSchema)
        registrar.add('assistant-settings', AssistantSettingSchema)
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
