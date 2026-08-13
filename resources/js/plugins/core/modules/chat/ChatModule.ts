import type {HawkiModule} from '$lib/kernel/modules/types.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Component} from 'svelte';
import Chat01Icon from '$lib/components/ui/icons/iconset/Chat01Icon.svelte';
import ChatSidebar from '$plugins/core/modules/chat/components/ChatSidebar.svelte';

// Both chat routes render the same page. Sharing one loader also shares the
// resolved-component cache between the empty chat and conversation URLs.
const loadChatPage = async () => import('./pages/ChatIndex.svelte');

/**
 * The "chat" feature module of the `core` plugin.
 *
 * A `HawkiModule` bundles everything one logical app feature needs (routes,
 * title/description/icon, sidebar entry) behind a single `name`. Modules are
 * handed to a plugin's `modules(registrar)` hook, where `registrar.add(module)`
 * (see `createModuleRegistrar` in `kernel/modules/moduleRegistrar.ts`) registers
 * them under the key `${pluginName}:${module.name}` (here `core:chat`) and
 * automatically namespaces any routes the module declares under the plugin's
 * route prefix.
 *
 * `/` resolves the module index at `/chat`, while `/:slug` opens a concrete
 * conversation. Both routes lazy-load the same routed chat page and pass the
 * optional slug through the router props.
 *
 * @example Registration happens in the owning plugin, not here:
 * // plugins/core/core.plugin.ts
 * public modules(registrar: ModuleRegistrar): void {
 *     registrar.add(new ChatModule());
 * }
 */
export class ChatModule implements HawkiModule {
    /**
     * Module identifier. Combined with the owning plugin name by the registrar
     * into the globally unique module key `core:chat`.
     */
    readonly name = 'chat';

    /**
     * Declares the module's routes. Called by the `ModuleRegistrar` while the
     * owning plugin runs its `modules()` lifecycle hook; every path registered
     * here is automatically prefixed with the plugin's route namespace.
     *
     * @param registrar Route collector handed in by the kernel. Use `lazyRoute`
     *   for page components that should be code-split, `route` for eagerly
     *   imported ones.
     */
    public routes(registrar: RouteRegistrar): void | Promise<void> {
        registrar
            .lazyRoute('/', loadChatPage, {name: 'chat.index'})
            .lazyRoute('/:slug', loadChatPage, {name: 'chat.conversation'});
    }

    public title(translate: Translator['translate'], _locale: Locale): string {
        return translate('chat.module.title');
    }

    public icon(_locale: Locale): string | IconComponent | Component {
        return Chat01Icon;
    }

    public sidebar(_locale: Locale): Component {
        return ChatSidebar;
    }
}
