# Modules

A module is HAWKI's unit of "one logical feature". It bundles everything a feature needs behind a single `name`: its pages (via `routes()`), optional navigation metadata (`title`/`description`/`icon`/`sidebar`), and whatever stores and schemas the owning plugin registered on its behalf.

Modules are both an organizational pattern and a programmatic one. Organizationally, a module groups related pages and components under one name so the sidebar, module selector, and future permission checks can address the feature as a unit. Programmatically, a module is a class implementing `HawkiModule` with a `name` and a `routes()` callback — the router calls it to collect the feature's pages.

## How modules work

A module is registered by a plugin's `modules()` hook. The `ModuleRegistrar` stores each under the globally unique key `${pluginName}:${module.name}` (so two plugins can't collide) and auto-prefixes any `routes()` the module declares with the plugin's route namespace.

```ts
// plugins/core/modules/chat/ChatModule.ts
export class ChatModule implements HawkiModule {
    readonly name = 'chat';

    public routes(registrar: RouteRegistrar): void | Promise<void> {
        registrar
            .lazyRoute('/', async () => import('./pages/ChatIndex.svelte'), {name: 'chat.index'})
            .lazyRoute('/room/:id', async () => import('./pages/ChatConversation.svelte'), 'chat.conversation');
    }
}
```

`lazyRoute` defers importing the page component until the route is actually navigated to, keeping it out of the initial bundle. Use `route` for eagerly imported pages.

## Module metadata

The metadata methods (`title`, `description`, `icon`, `sidebar`) are optional. Each receives the active `Locale` and the `Translator` so a module can render its label and icon in the user's language. The app sidebar and module selector consume these.

## Where modules fit in the boot

`ModuleExtension.init()` calls `PluginBootstrapper.runModules()` during assembly, before the router is built. `RoutingExtension.init()` then calls each module's `routes()` on the shared `RouteRegistrar`, wrapped in a group under the module's route prefix. The router is compiled later, on the `late` boot stage. See [The App & Kernel](120-App-and-Kernel/index.md) for the full extension order and [App Startup](120-App-and-Kernel/110-App-Startup.md) for the boot stages.

## Where to go next

| I want to… | Read |
|---|---|
| Understand routing (pages, layouts, data loading) | [Routing](190-Routing.md) |
| Understand how the router is built from registrations | [The App & Kernel](120-App-and-Kernel/index.md) |
| Write a plugin that registers modules | [Writing a Frontend Plugin](../../800-Plugins/200-Extending-HAWKI/100-Writing-a-Frontend-Plugin.md) |
