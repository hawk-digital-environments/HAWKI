import type {HawkiModule} from '$lib/kernel/modules/types.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';

const loadIndexPage = async () => import('./pages/ChatIndex.svelte');
const loadConversationPage = async () => import('./pages/ChatConversation.svelte');

/**
 * The "chat" feature module of the `core` plugin.
 *
 * A `HawkiModule` bundles a feature's routes behind a single `name`. Modules
 * are handed to a plugin's `modules(registrar)` hook, where
 * `registrar.add(module)` (see `createModuleRegistrar` in
 * `kernel/modules/moduleRegistrar.ts`) registers them under the key
 * `${pluginName}:${module.name}` (here `core:chat`) and automatically
 * namespaces any routes the module declares under the plugin's route prefix.
 * The feature's sidebar presence is contributed by the owning plugin via the
 * sidebar collector events instead (see `CorePlugin.boot()`).
 *
 * `/` resolves the module index at `/chat` (the "new chat" page), while
 * `/:slug` opens a concrete conversation. Each route lazy-loads its own page
 * component; the conversation route passes the slug through the router props.
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
            .lazyRoute('/', loadIndexPage, {name: 'chat.index'})
            .lazyRoute('/:slug', loadConversationPage, {name: 'chat.conversation'});
    }
}
