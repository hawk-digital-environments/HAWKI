import type {HawkiModule} from '$lib/kernel/modules/types.js';
import type {RouteRegistrar} from '$lib/kernel/routing/RouteRegistrar.js';

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
 * This module only declares a single route, `/`, lazily loading `ChatIndex.svelte`
 * as the page component. `registrar.lazyRoute` (as opposed to `registrar.route`)
 * defers importing the page until the route is actually navigated to, keeping it
 * out of the initial bundle.
 *
 * Note that the bulk of the chat feature is *not* reachable through this module
 * yet: the live composer UI under `components/composer/` is mounted by the legacy
 * UI through the `ChatComposer` `<svelte-snippet>` (registered in `core.plugin.ts`),
 * not by this route. This module is the future home of that UI once the routing
 * migration completes.
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
        registrar.lazyRoute('/', async () => (await import('./pages/ChatIndex.svelte')).default);
    }
}
