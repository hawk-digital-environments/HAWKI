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
// import {assistantBuilderStore} from "$plugins/assistants/stores/AssistantBuilderStore.svelte";


declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiPlugins {
        assistants: AssistantsPlugin;
    }
}

export default class AssistantsPlugin implements HawkiCorePlugin {

    readonly name = 'assistants';


    resourceSchemas?(registrar: ResourceSchemaRegistrar, context: HawkiPluginContext): void | Promise<void> {
        registrar.add('assistants', AssistantsSchema);
        registrar.add('assistant_avatar', AssistantAvatarSchema);
        registrar.add('assistant_feedback', AssistantFeedbackSchema)
    }
    public modules({add}: ModuleRegistrar): void | Promise<void> {
        add(new DashboardModule());
    }

    public routes(registrar: RouteRegistrar): void | Promise<void> {
        // registrar.group('/assistants', (assistants) => {
        //     assistants.group('/dashboard', (dashboard) => {
        //         dashboard.lazyRoute('/store', async () => (await import('$plugins/assistants/modules/dashboard/pages/store/page.svelte')).default);
        //         dashboard.lazyRoute('/my_drafts', async () => (await import('$plugins/assistants/modules/dashboard/pages/my_drafts/page.svelte')).default);
        //         dashboard.lazyRoute('/favourites', async () => (await import('$plugins/assistants/modules/dashboard/pages/favourites/page.svelte')).default);
        //         dashboard.lazyRoute('/shared', async () => (await import('$plugins/assistants/modules/dashboard/pages/shared/page.svelte')).default);
        //     })
        //     assistants.lazyRoute(`/:id`, async () => (await import('$plugins/assistants/modules/dashboard/pages/detail/page.svelte')).default);
        //
        //     // assistants.group('/builder', (builder) =>{
        //         // builder.group('/advanced', (advanced) => {
        //         //     advanced.lazyRoute('/general', async () => (await import('$plugins/assistants/modules/builder/')).default);
        //         //     advanced.lazyRoute('/behaviour', async () => (await import('$plugins/assistants/modules/builder/')).default);
        //         //     advanced.lazyRoute('/knowledge', async () => (await import('$plugins/assistants/modules/builder/')).default);
        //         //     advanced.lazyRoute('/model', async () => (await import('$plugins/assistants/modules/builder/')).default);
        //         //     advanced.lazyRoute('/test', async () => (await import('$plugins/assistants/modules/builder/')).default);
        //         //     advanced.lazyRoute('/publish', async () => (await import('$plugins/assistants/modules/builder/')).default);
        //         // })
        //
        //     // })
        // })
    }


    stores?(registrar: StoreRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void> {
        // Register the singleton rather than a fresh instance: the validator and
        // the builder pages import `assistantBuilderStore` directly, so a second
        // instance here would hold a draft nobody reads.
        registrar.add(assistantOptionsStore);
    }

    ready(app){
        app.router.debug()
    }

}
