# Routing

The frontend router resolves the current URL into a page component wrapped in a stack of layouts, runs each node's data loader, and hands the results to `RouterView` for rendering. This page covers the mental model a page or plugin author needs: the routing tree, how to write pages, apply layouts, use middleware, and add nested routers. For how the router plugs into the kernel and the boot stages, see [Modules & Routing](120-App-and-Kernel/120-Routing-and-Shell.md).

The router lives in `resources/js/components/ui/routing/`. Import its public surface from the barrel:

```ts
import {configurePage, type RouteProps, useRouter} from '$lib/components/ui/routing/index.js';
```

The three Svelte components (`RouterView`, `RouteNotFound`, `RouteError`) keep their own full paths — they are `.svelte` files, not type/value re-exports.

## The routing tree

Every registered route compiles into a `universal-router` tree. The router walks it in registration order and takes the first match. HAWKI wraps each match in an ordered **render chain** before rendering — the central concept the rest of this page builds on. `universal-router` ([GitHub](https://github.com/kriasoft/universal-router)) is the underlying path-matching engine; HAWKI's routing kit wraps it with the render chain, data loading, caching, and Svelte integration.

### Node, route, layout

Three terms, each pointing at a distinct thing:

- **Node** — a single renderable unit in the chain. Every node is either a **layout** node or a **page** node. A node carries a stable id, the component (or a lazy loader for it), and an effective config (`loadData`, `cacheKey`, `paramSchema`). Nodes are stamped once at build time by `RouteRegistrar` and treated as immutable data afterwards.
- **Route** — a registration entry: a path, a page component, and optional `name`, `meta`, `middlewares`, `layout`, and `config`. One route produces up to two nodes (its page node, plus a layout node if it declares a `layout`). Routes without a stamped node (middleware wrappers, groups with no layout) drop out of the render chain silently.
- **Layout** — a component that wraps its children via a `{@render children()}` snippet. Layouts stay mounted across navigations within their group, which is why a layout's `loadData` runs once and is not re-run on every page change inside it. A layout is registered either on a route (`RouteOptions.layout` / `lazyLayout`), on a group (`RouteGroupOptions.layout` / `lazyLayout`), or router-wide (`CreateRouterOptions.rootLayout` / `lazyRootLayout`).

### The render chain

For a matched route, the router collects the node chain by walking the matched route's `parent` chain, then prepends the router-wide root layout. The result is ordered outermost layout first, page last:

```mermaid
flowchart LR
    R[Root layout] --> L1[Group layout] --> L2[Route layout] --> P[Page]
```

`RouterView` nests these into each other left-to-right. Every node's `loadData` runs concurrently — there is no data inheritance between a layout and its page. Each node receives its own `data` and `params` as props; `meta` is shared across the whole chain because it belongs to the route, not to the components rendering it.

## Why routes and layouts are lazy

`lazyRoute` and `lazyLayout` take a function that imports the component on demand, not the component itself. The tradeoff is simple: the component lands in a separate chunk (a `.js` file the browser fetches only when the route is navigated to), so the initial bundle stays small and the page loads faster on first paint. The cost is a one-time network fetch the first time the route is visited.

Use `lazyRoute` / `lazyLayout` / `lazyRootLayout` for everything that is not on the critical first-paint path. `route` (eager) is for components that must be in the initial bundle — rare in practice.

## Writing a simple page

A page is a Svelte component. At its simplest, it is just markup:

```svelte
<h1>Welcome</h1>
```

Register it with `lazyRoute` so the component stays out of the initial bundle:

```ts
// Inside a module's or plugin's routes() hook
registrar.lazyRoute('/', async () => (await import('./pages/Welcome.svelte')).default, {
    name: 'welcome',
});
```

That is a complete, working page. No params, no data loading, no cache.

### Route props

`RouterView` passes four props to every page and layout: `params`, `data`, `meta`, and `route`. Destructure them off `$props()` when you need them:

```svelte
<script lang="ts">
    import type {RouteProps} from '$lib/components/ui/routing/index.js';

    const {params, data, meta, route}: RouteProps = $props();
</script>
```

| Prop     | What it holds                                                                                                                                                                                                                                                     |
|----------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `params` | The matched route params. Raw `RouteParams` (everything is `string \| string[]`) unless the page declares a `paramSchema` — then it is the schema's parsed output.                                                                                                |
| `data`   | The object the page's `loadData` returned, or `{}` when the page declares no loader.                                                                                                                                                                              |
| `meta`   | The matched route's `meta` object (arbitrary data set at registration time — page title, icon, permission hints). `{}` when the route declares none. Shared across the whole render chain: a layout reads the meta of whichever page is currently open inside it. |
| `route`  | The matched `universal-router` `Route` object, or `null` while nothing is rendered (loading, 404, error).                                                                                                                                                         |

`RouteProps` with no type arguments reflects the no-config case: `data` is `{}`, `params` is raw. Pass `RouteProps<typeof config>` to get the parsed params and typed data — see [Configuring a page](#configuring-a-page).

## Configuring a page

Params, data loading, and cache identity are declared together in a `config` export, built with `configurePage`. Svelte 5's `<script module>` block is where module-level exports live — it runs once when the component module is first imported, not per-instance. The `config` object is what the router reads; the `<script>` block (no `module`) is what the component instance reads off `$props()`.

```svelte
<script module lang="ts">
    import {z} from 'zod';
    import {configurePage} from '$lib/components/ui/routing/index.js';

    export const config = configurePage({
        paramSchema: z.object({id: z.coerce.number()}),
        loadData: async (ctx) => ({
            chat: await ctx.restApi.getResource('chats', ctx.params.id),
        }),
    });
</script>

<script lang="ts">
    import type {RouteProps} from '$lib/components/ui/routing/index.js';

    const {params, data, meta, route}: RouteProps<typeof config> = $props();
</script>
```

`RouteProps<typeof config>` carries both the parsed params and the loader's return type — there is nothing else to spell out. Each member of the config is optional; the sections below cover them individually.

### Parameters and validation

A route path can contain params (`/room/:id`). The router matches them as strings; `paramSchema` is where you validate and coerce them before anything else runs.

```ts
export const config = configurePage({
    paramSchema: z.object({id: z.coerce.number()}),
    // loadData and cacheKey are optional
});
```

The schema's output type flows into `loadData` (as `ctx.params.id: number`) and into the component's `params` prop (`RouteProps<typeof config>` picks it up automatically). Without a `paramSchema`, the node receives raw `RouteParams` (everything is `string | string[]`).

:::warning[Validation failure is a 404]
Validation runs as a synchronous `safeParse` before the loader. On failure the router warns once with the route path and the zod issues, then fails the resolution with a 404 — not a thrown error and not a silent pass. An async-refined schema throws loudly rather than silently deadlocking.
:::

### Loading data for a page

`loadData` is an async function that receives a context and returns an object. The return value becomes the page's `data` prop.

```ts
export const config = configurePage({
    loadData: async (ctx) => ({
        chat: await ctx.restApi.getResource('chats', ctx.params.id),
    }),
});
```

The loader context (`ctx`) carries what a loader needs:

- `ctx.params` — the parsed params (typed by the sibling `paramSchema`).
- `ctx.router` — the owning router's `RouterHandle`, for navigation within a loader.
- `ctx.signal` — an `AbortSignal` that fires when this resolution is superseded by a newer navigation. Pass it to `restApi` calls so a superseded fetch is aborted, not left dangling.
- `ctx.redirect(pathOrRoute, params?)` — throws, never returns. Resolved against the owning router.
- `ctx.error(status, message?)` — throws, never returns. `404` lands the router on `state: 'notFound'`, anything else on `state: 'error'`.
- `ctx.disableCache()` — discards this loader's result instead of storing it. See [Caching](#caching-data) below.

App-level services like `ctx.app` and `ctx.restApi` are available through declaration merging — see [Advanced: reaching app services](#advanced-reaching-app-services-from-a-loader) at the end of this page.

### Caching data

The router keeps an LRU cache of `loadData` results (default 30 entries, configurable via `CreateRouterOptions.dataCacheSize`). A cache hit skips the loader entirely. Caching is per-node: each node in the render chain has its own cache identity.

:::tip[What is an LRU?]
LRU stands for "least recently used." The cache holds a fixed number of entries; when it is full, the entry that has not been touched for the longest time is evicted to make room. The tradeoff is bounded memory: the cache never grows past its size, but a page visited long ago may need to re-fetch when revisited.
:::

**What is cached:** the object your `loadData` returns, keyed by the node's cache key. The key is computed *before* the loader runs, so the router can answer "is this already cached?" without fetching anything.

**The default key:** the node's build-time id plus the full normalized path. This is already safe — two different URLs through the same node can never collide. You do not need to declare a `cacheKey` unless you want to *share* one cache entry across different paths (e.g. a layout whose data is the same regardless of which page is open inside it).

**Setting a custom key:** use `ctx.makeKey(prefix)` inside a `cacheKey` function. It serializes `prefix` plus every matched param, so you cannot forget one:

```ts
export const config = configurePage({
    cacheKey: (ctx) => ctx.makeKey('chat'),
    loadData: async (ctx) => ({...}),
});
```

:::warning[A hand-written `cacheKey` that omits a param serves one URL's data on another]
`/chat/a`'s data rendered on `/chat/b`, no error. The default key folds in the node id and full path, which is already safe; you only declare `cacheKey` to *share* one cache entry across different paths. Use `ctx.makeKey(prefix)` — it serializes `prefix` plus every matched param, so you cannot forget one. A raw string key is the expert escape hatch, not the default.
:::

:::warning[Cache keys must be unique across plugins]
Two nodes with the same cache key share one cache entry — that is the feature, not the bug. But if two plugins independently pick the same prefix for `ctx.makeKey(...)`, they will silently serve each other's data. Prefix your keys with something plugin-specific (e.g. `ctx.makeKey('core:chat')`, not `ctx.makeKey('chat')`).
:::

**Disabling caching:** either set `cacheKey: false` to never cache this node, or call `ctx.disableCache()` inside the loader to discard *this run's* result without affecting the lookup that already happened.

**Invalidating cached data:** `router.clearData(target?)` removes entries. No argument clears everything. `{key}`, `{keyStartsWith}`, `{path}`, and `{route, params?}` target specific entries. `clearData` does not re-resolve the current route — call `router.reload()` for that.

## Applying a layout

A layout is a component that wraps its children via a `{@render children()}` snippet. It provides shared chrome — sidebar, header, navigation — that stays mounted while the page inside it changes.

```svelte
<script lang="ts">
    import type {RouteLayoutProps} from '$lib/components/ui/routing/index.js';

    const {children, data, params, meta, route}: RouteLayoutProps = $props();
</script>

<nav>...</nav>
<main>
    {@render children()}
</main>
```

Layouts can be applied at three levels, and they stack:

| Level       | Option                                              | What it wraps                                                 |
|-------------|-----------------------------------------------------|---------------------------------------------------------------|
| Router-wide | `CreateRouterOptions.rootLayout` / `lazyRootLayout` | Everything this router renders, including 404 and error pages |
| Group       | `RouteGroupOptions.layout` / `lazyLayout`           | Every route inside the group                                  |
| Route       | `RouteOptions.layout` / `lazyLayout`                | This route's page only                                        |

A route inside a group inside a router with a root layout gets all three stacked: root layout → group layout → route layout → page. `RouterView` nests them outermost-first.

Prefer `lazyLayout` / `lazyRootLayout` over the eager variants so the layout stays out of the initial bundle. Declaring both `layout` and `lazyLayout` (or `rootLayout` and `lazyRootLayout`) throws when the router is created — silently preferring one would leave the other's component registered nowhere.

:::info[Why layouts stay mounted]
The router caches component references by loader identity, so a layout shared by two routes comes back as the same component instance across navigations. That is why navigating between sibling routes does not unmount the group layout — and why a layout's `loadData` does not re-run on every page change inside it.
:::

### Loading data for layouts

A layout declares its own `config` with `configureLayout` — same shape as `configurePage`, separate name for clarity:

```sveltehtml
// AdminLayout.svelte
<script module lang="ts">
    export const config = configureLayout({
        loadData: async (ctx) => ({
            sidebar: await ctx.restApi.getResourceCollection('admin-menu'),
        }),
    });
</script>
```

**How layouts resolve:** every node's `loadData` runs concurrently — there is no data inheritance between a layout and its page. Each node receives its own `data` as a prop.

**Is the data cached?** Yes, same LRU cache and same rules as pages. A layout's cache key is computed from its own `cacheKey` (or the default node-id-plus-path key).

**Is the loader executed every time?** No. Layouts stay mounted across navigations within their group (see the info box above), so the router does not re-resolve them on every page change. The loader runs once when the layout first mounts, and again only when the cache is invalidated or the layout is re-mounted after being unmounted.

## Middlewares

A middleware is a guard that runs before the route (or route group) it is attached to. It can redirect, throw an error, replace the rendered component, or pass through to the guarded route.

```ts
import {redirect, routeError} from '$lib/components/ui/routing/index.js';

const requireAdmin: RouteMiddleware = async (context, next) => {
    const user = context.app.auth.user;
    if (!user?.isAdmin) {
        redirect('login');  // throws — never returns
    }
    return next();  // pass through to the guarded route
};
```

The three meaningful return shapes:

- `return await next()` — pass through. Whatever the guarded route resolves to is handed back unchanged.
- Return a `RouteResultBody` (`{component, context, params}`) — take over rendering. The body carries the component (eager or lazy), so a middleware can replace the page and rewrite the params the page will receive.
- Return nothing (or throw) — mark the guarded route as unreachable. `universal-router` skips the route's subtree, falls through to the next sibling, and 404s if nothing else matches. This is the permission-deny signal.

`redirect(pathOrRoute, params?)` and `routeError(status, message?)` are thrown signals — they work from both middlewares and loaders. A middleware that has no router instance imports them from the barrel; a loader reaches the same behavior via `ctx.redirect` / `ctx.error`. `redirect()` accepts a route name or a literal path (a leading `/` selects the literal branch). Resolution is deferred until the router handles it.

Middlewares are registered on routes and groups:

```ts
registrar.group('/admin', (admin) => {
    admin.lazyRoute('/users', async () => (await import('./pages/UserList.svelte')).default);
}, {
    middlewares: [requireAdmin],
    lazyLayout: async () => (await import('./AdminLayout.svelte')).default,
});
```

`addMiddlewareToRoute(path, middleware)` and `addMiddlewareToGroup(path, middleware)` let foreign code guard a route it does not own — the owning plugin/module must have run its `routes()` hook first (plugins run before modules).

## Adding a nested router

The app router (`app.router`) is the only one most pages need. But a component can create its own router — a nested "app inside an app" — with `createRouter` or `createRouterFromRegistrar`, and render it with its own `RouterView`.

```svelte
<script lang="ts">
    import {createRouter} from '$lib/components/ui/routing/index.js';
    import RouterView from '$lib/components/ui/routing/RouterView.svelte';

    const router = createRouter('nested', (registrar) => {
        registrar.lazyRoute('/', async () => (await import('./NestedIndex.svelte')).default);
    }, {
        strategy: 'transient',  // in-memory, no URL sync
    });
</script>

<RouterView {router} />
```

A nested router is fully independent: it has its own route tree, its own data cache, its own state. Loaders inside it receive `ctx.router` pointing at the nested router, not `app.router`.

### Which router does `useRouter()` return?

**The one rendering you.** Every `RouterView` pins its own router for its subtree, so a bare `useRouter()` inside the nested view returns the nested handle, and the same call above it returns `app.router`. Nothing has to be opted into — where a component is mounted is the whole answer.

That matters because every method of a `RouterHandle` — `goTo`, `getPath`, `isActive`, `clearData` — is meaningless except relative to one router's route tree and current path. A sidebar layout calling `isActive('chat')` while rendered by the nested router must compare against the path *that* router is showing; answering from `app.router` would return a plausible boolean about a different page entirely.

To reach a *different* router, name it: `useRouter('app')` works anywhere below the app's `RouterView`, including inside a nested one. `<Link router="app" href={{name: 'chat'}}>` does the same for a link.

Names resolve nearest-first, so a nested router reusing an outer router's name shadows it — and makes the outer one unreachable from inside that subtree. Pick distinct names unless you mean exactly that.

:::info Resolving a router name that changes at runtime
`useRouter(name)` reads Svelte context, so it only works during component initialization. A component that picks its router from a *prop* — as `<Link router={…}>` does — cannot call it again when that prop changes, and would keep a handle to the router it saw first.

For that case only, capture the scope once and resolve names against it later:

```svelte
<script lang="ts">
    import {type RouterScope, useRouterScope} from '$lib/components/ui/routing/index.js';

    const {routerName}: {routerName?: string} = $props();

    const scope: RouterScope = useRouterScope();          // init only
    const router = $derived(                              // re-resolves freely
        routerName === undefined ? scope.current : scope.get(routerName)
    );
</script>
```

`RouterScope` exposes `current` (the router rendering you), `get(name)` and `names()` — the last one for error messages listing what is actually reachable. Everything else should call `useRouter()`.
:::

:::warning A component shared between two routers cannot navigate by route name
Route names are per-router. A layout used by both the app router and a nested one that calls `getPath('chat')` will throw `Route "chat" not found` the moment the nested router renders it, because that name exists only in the app's tree.

This is deliberate — it fails loudly on first render instead of silently answering about the wrong page. If a shared component genuinely means the app router, say `useRouter('app')`; otherwise take the target path as a prop.
:::

### What a nested router does and does not share

|                                         | Shared with the parent router?                                                                                                          |
|-----------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------|
| Route tree, current path, `RouterState` | No — fully separate                                                                                                                     |
| Data cache (`loadData` results)         | No — each router builds its own LRU, so a layout used by both fetches once per router                                                   |
| Loaded component modules                | **Yes** — the module cache is keyed on loader identity process-wide, so a lazy layout used by both downloads once and is evaluated once |

A nested router also lives only as long as the component that created it. `createRouter` runs per component instance, so if an app-level navigation unmounts the component holding the nested `RouterView`, the router goes with it — tree, state and cache. Navigate back and you get a fresh one with an empty cache. "Layouts stay mounted across navigations" holds *within* a router; it says nothing about surviving the teardown of the router itself.

## Routing strategies

A routing strategy owns "the current path" — the source the router reads from and writes to when navigating. Three built-in strategies:

| Strategy    | URL sync                                 | Use case                                                                   |
|-------------|------------------------------------------|----------------------------------------------------------------------------|
| `path`      | Browser URL (`window.location.pathname`) | App owns the browser URL (the default for `app.router`)                    |
| `hash`      | Hash fragment (`#/path`)                 | App shares the URL with a legacy server that does not understand SPA paths |
| `transient` | In-memory only                           | Nested routers, modals, preview environments — no URL to sync              |

Set the strategy on `CreateRouterOptions.strategy`. A nested router can use a different strategy than its parent — a `transient` router inside a `path` router is the common case for a modal or sub-view that should not pollute the browser URL.

:::info[Query strings are not part of routing]
The router reads the path only. Query params never affect matching or caching. Reasoning: backend frameworks ignore query at the routing level too, and under the hash strategy it is genuinely ambiguous whether `?x=1` belongs to the path or the fragment. Read query params off `window.location.search` if you need them.
:::

## Meta and catch-all routes

### Meta

Every route can carry arbitrary `meta` data — page title, icon, permission hints, whatever the owning plugin needs. Meta belongs to the **route**, not to the components around it: the page and every layout wrapping it receive the matched route's `meta` as their `meta` prop, so a layout reads the meta of whichever page is currently open inside it.

```ts
registrar.lazyRoute('/room/:id', loader, {
    name: 'chat.room',
    meta: {title: 'Chat', icon: 'chat'},
});
```

Meta is untyped at the router level. Narrow it where you read it, through `RouteProps`' third type argument: `RouteProps<typeof config, void, ChatMeta>` makes `meta` typed as `ChatMeta`. Pass the inferred type as the `TMeta` argument on `lazyRoute` (`registrar.lazyRoute<ChatMeta>(...)`) to get the same narrowing from the registration side.

Router-internal settings (such as `layout`) live outside of `meta`, so they can never collide with or leak into a plugin's own data.

### Catch-all routes

A catch-all route matches its path **and everything below it** — a `/files` catch-all also answers `/files/a/b/c`. Matching stays segment-aware, so `/filesX` is still a miss.

```ts
registrar.lazyRoute('/files', loader, {catchAll: true});
```

Catch-alls are always emitted last by `RouteRegistrar.build()`, no matter where they were registered, because a catch-all shadows every route after it. Register one with path `/` inside a `group` to catch that group's subtree only.

The matched remainder is **not** exposed as a param. Use a wildcard in the path instead if you need it: `'/files/*rest'` yields `params.rest === ['a', 'b', 'c']`. A wildcard needs at least one segment, so it does not match the bare `/files` — combine with a catch-all if you need both.

## Advanced: reaching app services from a loader

The `components/ui/` package must not import from `kernel/` — that direction is deliberately not a dependency, so the routing kit can be externalized into its own package. Loaders reach app services (`app`, `restApi`) through TypeScript declaration merging on `RouteDataLoaderContextExtensions` in `resources/js/components/ui/routing/extendableTypes.ts`:

```ts
// In RoutingExtension.ts
declare module '$lib/components/ui/routing/extendableTypes.js' {
    interface RouteDataLoaderContextExtensions {
        app: HawkiApp;
        restApi: RestApi;
    }
}
```

Augmenting only makes the properties *visible* on the type; `CreateRouterOptions.loaderContext` is what supplies the values at runtime, so a router created without them type-checks but hands loaders a context missing what it promised. The two belong together.

`RouteCacheKeyContext` deliberately does **not** carry app services. The cache key is evaluated to decide whether the loader needs to run at all, so it must be computable without fetching anything.

## Where to go next

| I want to…                                                  | Read                                                                                    |
|-------------------------------------------------------------|-----------------------------------------------------------------------------------------|
| Understand how the router plugs into boot and the SPA shell | [Modules & Routing](120-App-and-Kernel/120-Routing-and-Shell.md)                     |
| Walk a routed page end-to-end                               | [App Startup](120-App-and-Kernel/110-App-Startup.md)                        |
| Write a plugin or module that registers routes              | [Writing a Frontend Plugin](../../800-Plugins/200-Extending-HAWKI/100-Writing-a-Frontend-Plugin.md) |
