# Routing

:::warning[Not implemented yet]
The routing subsystem is scaffolding for the single-page-app rewrite planned for HAWKI v3.0.0. The code under `resources/js/kernel/routing/` exists but is **not wired into the running app** — `RoutingExtension` is registered in `app.ts`, but the frontend still navigates through the legacy Blade layer. Treat everything in this section as reserved and subject to change. This page will be written once routing actually lands.
:::

## What exists today

The directory `resources/js/kernel/routing/` holds the intended building blocks:

| File | Role (planned) |
|---|---|
| `RoutingExtension.ts` | App extension that would own the route registry and render pipeline |
| `RouteRegistrar.ts` | Registrar plugins use to register routes (`plugin.routes()`) |
| `buildMiddlewareStack.ts` | Compose per-route middleware into a single handler chain |
| `routeRenderer.ts` | Resolve a matched route to the component that renders it |
| `routeInflection.ts` | Helpers for route-name ↔ path conversion |

None of this is active. Plugins can already declare a `routes()` lifecycle hook (see `HawkiPlugin` in `kernel/plugins/types.ts`), but the kernel does not yet dispatch or render those routes on the frontend — navigation is still server-rendered Blade with Svelte snippets mounted into it (see [Old UI Integration](300-Old-Ui.md)).

## Why it lives here already

Routing touches nearly every other extension (modules register routes, the renderer needs the module/component registry, middleware needs config and connection state). Landing the scaffolding alongside the extension system lets the surrounding contracts settle before the implementation follows, so that enabling routing later is a localized change rather than a cross-cutting one.

## Where to look in the meantime

- For how navigation works today: [Old UI Integration](300-Old-Ui.md) (`OldUiBridge` triggers, the `<svelte-snippet>` element).
- For the extension system routing will plug into: [The App & Kernel](110-App-and-Kernel.md) and [Writing a Plugin](130-Writing-a-Plugin.md).
