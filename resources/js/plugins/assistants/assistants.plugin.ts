import { ModuleRegistrar } from "$lib/kernel/modules/moduleRegistrar";
import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';
import {
    HawkiPlugin,
    HawkiPluginContext,
    HawkiPluginContextWithConfig
} from '$lib/kernel/plugins/types';
import {ResourceSchemaRegistrar} from '$lib/kernel/resources/resourceSchemaRegistrar';
import {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar';
import type {HookRegistrar} from '$lib/kernel/hooks/hookRegistrar.js';
import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';
import BotIcon from '$lib/components/ui/icons/iconset/BotIcon.svelte';
import Store01Icon from '$lib/components/ui/icons/iconset/Store01Icon.svelte';
import FileEditIcon from '$lib/components/ui/icons/iconset/FileEditIcon.svelte';
import StarIcon from '$lib/components/ui/icons/iconset/StarIcon.svelte';
import Share02Icon from '$lib/components/ui/icons/iconset/Share02Icon.svelte';
import Settings01Icon from '$lib/components/ui/icons/iconset/Settings01Icon.svelte';
import BubbleChatIcon from '$lib/components/ui/icons/iconset/BubbleChatIcon.svelte';
import Database01Icon from '$lib/components/ui/icons/iconset/Database01Icon.svelte';
import ComputerIcon from '$lib/components/ui/icons/iconset/ComputerIcon.svelte';
import TestTube01Icon from '$lib/components/ui/icons/iconset/TestTube01Icon.svelte';
import SentIcon from '$lib/components/ui/icons/iconset/SentIcon.svelte';
import AssistantsSidebar from '$plugins/assistants/components/AssistantsSidebar.svelte';
import CreateAssistantButton from '$plugins/assistants/components/CreateAssistantButton.svelte';
import {DashboardModule} from "$plugins/assistants/modules/dashboard/DashboardModule";
import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte";
import {assistantHandlesStore} from "$plugins/assistants/stores/AssistantHandlesStore.svelte";
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

    /**
     * Contributes the assistants feature's sidebar UI via the sidebar hooks:
     * one module selector entry and one sidebar panel (both active while
     * dashboard *or* builder routes are shown — the builder has no selector
     * entry of its own), plus the assistants sidebar's standard nav rows
     * (see `hooks/assistantMenuHooks.svelte.ts`).
     */
    public hooks(registrar: HookRegistrar): void {
        const dashboardGroup = getModuleRouteGroupName('assistants', 'dashboard');
        const builderGroup = getModuleRouteGroupName('assistants', 'builder');

        registrar.add('moduleSelectorEntries', (entries, ctx) => [...entries, {
            id: 'assistants:dashboard',
            label: ctx.translate('assistants.assistants'),
            icon: BotIcon,
            onSelect: (selectCtx) => {
                void selectCtx.router.goToRoute('assistants.dashboard.index');
            },
            active: ctx.router.isRouteActive(dashboardGroup) || ctx.router.isRouteActive(builderGroup)
        }]);

        registrar.add('sidebarSlots', (slots, ctx) => [...slots,
            {
                id: 'assistants:sidebar',
                position: 'panel',
                component: AssistantsSidebar,
                active: ctx.router.isRouteActive(dashboardGroup) || ctx.router.isRouteActive(builderGroup)
            },
            {
                // Dashboard routes only — inside the builder the primary
                // action would compete with the level's own chrome.
                id: 'assistants:create',
                position: 'action',
                component: CreateAssistantButton,
                active: ctx.router.isRouteActive(dashboardGroup)
            }
        ]);

        registrar.add('aiAssistants', (assistants, ctx) => [...assistants, ...assistantHandlesStore.menuAssistants(ctx.translate)], {order: 10});

        registrar.add('assistantMenuEntries', (menu, ctx) => [...menu,
            {
                id: 'dashboard.store',
                level: 'dashboard',
                label: ctx.translate('assistants.sidebar.store'),
                icon: Store01Icon,
                route: 'assistants.dashboard.store',
                active: ctx.router.isRouteActive('assistants.dashboard.store')
                    || ctx.router.isRouteActive('assistants.dashboard.index')
            },
            {
                id: 'dashboard.drafts',
                level: 'dashboard',
                label: ctx.translate('assistants.sidebar.drafts'),
                icon: FileEditIcon,
                route: 'assistants.dashboard.drafts',
                active: ctx.router.isRouteActive('assistants.dashboard.drafts')
            },
            {
                id: 'dashboard.favourites',
                level: 'dashboard',
                label: ctx.translate('assistants.sidebar.favourites'),
                icon: StarIcon,
                route: 'assistants.dashboard.favourites',
                active: ctx.router.isRouteActive('assistants.dashboard.favourites')
            },
            {
                id: 'dashboard.shared',
                level: 'dashboard',
                label: ctx.translate('assistants.sidebar.shared'),
                icon: Share02Icon,
                route: 'assistants.dashboard.shared',
                active: ctx.router.isRouteActive('assistants.dashboard.shared')
            },
            {
                id: 'builder.general',
                level: 'builder',
                label: ctx.translate('assistants.builder.sidebar.general'),
                icon: Settings01Icon,
                route: 'assistants.builder.general',
                active: ctx.router.isRouteActive('assistants.builder.general')
            },
            {
                id: 'builder.behaviour',
                level: 'builder',
                label: ctx.translate('assistants.builder.sidebar.behaviour'),
                icon: BubbleChatIcon,
                route: 'assistants.builder.behaviour',
                active: ctx.router.isRouteActive('assistants.builder.behaviour')
            },
            {
                id: 'builder.knowledge',
                level: 'builder',
                label: ctx.translate('assistants.builder.sidebar.knowledge'),
                icon: Database01Icon,
                route: 'assistants.builder.knowledge',
                active: ctx.router.isRouteActive('assistants.builder.knowledge')
            },
            {
                id: 'builder.model',
                level: 'builder',
                label: ctx.translate('assistants.builder.sidebar.model'),
                icon: ComputerIcon,
                route: 'assistants.builder.model',
                active: ctx.router.isRouteActive('assistants.builder.model')
            },
            {
                id: 'builder.test',
                level: 'builder',
                label: ctx.translate('assistants.builder.sidebar.test'),
                icon: TestTube01Icon,
                route: 'assistants.builder.test',
                active: ctx.router.isRouteActive('assistants.builder.test')
            },
            {
                id: 'builder.publish',
                level: 'builder',
                label: ctx.translate('assistants.builder.sidebar.publish'),
                icon: SentIcon,
                route: 'assistants.builder.publish',
                active: ctx.router.isRouteActive('assistants.builder.publish')
            }
        ]);
    }


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
        // Lazy singleton behind the composer's `@` menus (see the `aiAssistants`
        // handler in `hooks()` below); no `loadData`, so nothing is fetched
        // until a menu first reads it.
        registrar.add(assistantHandlesStore);
    }

    // ready(app){
    //     app.router.debug()
    // }

}
