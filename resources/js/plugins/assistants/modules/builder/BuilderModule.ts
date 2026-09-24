import {HawkiCoreModule, HawkiModule} from "$lib/kernel/modules/types";
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Component} from 'svelte';
import BotIcon from '$lib/components/ui/icons/iconset/BotIcon.svelte';
import AssistantsSidebar from '$plugins/assistants/components/AssistantsSidebar.svelte';

/**
 * The builder has no module-selector entry of its own — entering it (from
 * the dashboard's "create"/"edit" actions) is a drill-down inside the same
 * assistants sidebar that {@link DashboardModule} shows, not a module
 * switch. `sidebar()` still returns that same component (rather than
 * nothing) so the app sidebar renders correctly on a direct page load of a
 * builder route, before any dashboard visit has set the fallback.
 *
 * `title()`/`icon()` mirror {@link DashboardModule}'s too, even though the
 * builder never appears in the module-selector list: while the builder is
 * the active module, `ModuleSelector.svelte` still reads its `title()`/
 * `icon()` for the *trigger button's* label/icon (it just isn't one of the
 * list's own rows) — without these it would show up as `assistants:builder`.
 */
export class BuilderModule implements HawkiCoreModule {
    public readonly name = 'builder';
    public readonly pluginNameInRoutes = true;

    public visible(_app: HawkiApp): boolean {
        return false;
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
