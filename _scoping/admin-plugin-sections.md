# Admin Panel: Sections and Workspaces added by Modules

Design for letting a Module add its own Section (a sidebar cluster) or Workspaces (pages) to the Admin Panel.
Vocabulary is defined in [CONTEXT.md](../CONTEXT.md#admin-panel); the mechanism decision is
[ADR 0001](../docs/adr/0001-admin-panel-collects-workspaces-from-modules.md). Frontend only: there is no backend
plugin concept yet, and `PluginExtension.autoRegisterInstalledPlugins` is still a no-op, so the first consumers are
built-in Modules such as Chat.

Decisions taken with Max on 2026-09-16: the contributor is a **Module**, not a Plugin. A **Section** is the sidebar
cluster (*AI services*, *People and access*, *System*); a **Workspace** is one page inside a Section; a Workspace
holds one or more **Record Sets** (`AdminMcp.svelte` already holds two). The built-in Sections and Workspaces stay in
the admin plugin. A Workspace naming an unknown permission stays silently invisible.

## Glossary versus code

The code predates the vocabulary and uses the words differently. Both mismatches should be resolved while the
registry refactor touches these files anyway; URLs, route names and translation keys stay as they are.

| Glossary | Code today | Rename to |
| --- | --- | --- |
| Section | `group` field of a section entry; `AdminSidebarGroup.svelte`; `admin.groups.*` keys | `AdminSection`, `AdminSidebarSection.svelte` (keys unchanged) |
| Workspace | `sections.ts` entries, `SectionId`, `AdminPage section=` prop, `admin.sections.*` keys | `workspaces.ts`, `WorkspaceId`, `AdminPage workspace=` (keys and `admin.<id>` route names unchanged) |
| Record Set | `AdminWorkspace` class, `useAdminWorkspace`, `workspace.svelte.ts` | `AdminRecordSet`, `useAdminRecordSet`, `recordSet.svelte.ts` |

## What exists today

| Concept | Where | Coupling to replace |
| --- | --- | --- |
| Workspace list | `resources/js/plugins/admin/sections.ts` | static `as const` array; literal id union |
| Workspace routes | `routes.ts` | `admin.<id>` route names, `/admin/<id>` paths, `admin.sections.<id>` titles |
| Sidebar | `AdminSidebar.svelte`, `AdminSidebarGroup.svelte` | three hardcoded Sections with icons; filters the list by `group` |
| Overview | `AdminHome.svelte` | iterates `['ai','people','system']`; `admin.descriptions.<id>` |
| Page shell | `AdminPage.svelte` | `section` prop; looks the permission up in the static list |
| Record Set | `workspace.svelte.ts` | already independent of the list; several per page allowed |
| Backend | `App\Services\Admin\ResourceCatalog::SECTIONS` | admin-internal; maps built-in resources to permissions. Not part of the contract |

A Workspace's permission is checked in three places that must keep agreeing: the route guard, the sidebar/overview
filter, and `AdminPage`'s reactive `allowed`. The registry below becomes the single source for all three.

## Contract

```ts
// resources/js/plugins/admin/api.ts — the admin plugin's public surface
import type { IconComponent } from '$lib/components/ui/icons/index.js';
import type { RouteComponentLoader } from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

export interface AdminSectionDefinition {
    /** Module-local id; must not collide with another Module's Section. */
    id: string;
    icon: IconComponent;
    /** Translation key. */
    title: string;
}

export interface AdminWorkspaceDefinition {
    /** Module-local id, slugified into the URL. Workspaces of core-plugin Modules share `/admin/<id>` and must be unique across them. */
    id: string;
    /** Workspace Permission, required in addition to `admin.access`. */
    permission: string;
    /** A built-in Section (`'ai' | 'people' | 'system'`) or a Section this Module declared. */
    section: string;
    /** Translation keys; the title also becomes the route's document title. */
    title: string;
    description: string;
    page: RouteComponentLoader;
}

export interface AdminWorkspaceRegistrar {
    section(definition: AdminSectionDefinition): void;
    workspace(definition: AdminWorkspaceDefinition): void;
}

/** Implemented by a Module alongside `HawkiModule`. Declarative: no app state is available. */
export interface AdminWorkspaceProvider {
    adminWorkspaces(registrar: AdminWorkspaceRegistrar): void;
}
```

Usage by a Module:

```ts
export class ChatModule implements HawkiModule, AdminWorkspaceProvider {
    readonly name = 'chat';
    // …title, icon, routes, search as today…

    adminWorkspaces({ workspace }: AdminWorkspaceRegistrar) {
        workspace({
            id: 'prompts',
            section: 'ai',
            permission: 'prompts.manage',
            title: 'chat.admin.prompts',
            description: 'chat.admin.prompts_description',
            page: () => import('./admin/ChatPrompts.svelte')
        });
    }
}
```

The page is an ordinary Svelte route component. It creates one Record Set per data set with `useAdminRecordSet`
(today `useAdminWorkspace`) and composes `AdminPage`, `AdminSearch`, `AdminTable`, `AdminResultDialog`, all
re-exported from `api.ts`. Its data comes from JSON:API resources the owning plugin registers through the existing
`resourceSchemas` hook; each backend resource does its own authorization, ideally with the same permission the
Workspace names.

## Rules the panel enforces

1. **Identity.** A Workspace's full name is `<plugin>:<module>:<id>`, extending the Module naming scheme. Built-in
   Workspaces are `admin:admin:providers` etc. Sections are named the same way.
2. **URL and route name** follow `getPluginRoutePrefix` of the Module's plugin: core plugins at `/admin/<id>` with
   route name `admin.<id>`; third-party plugins at `/admin/plugins/<slug>/<id>` with route name
   `admin.plugins.<slug>.<id>`. Two core-plugin Modules declaring the same Workspace id throw at boot. Sections have
   no URL.
3. **Access.** Route guard, sidebar, overview and `AdminPage` all read the permission from the registry. The route
   guard keeps requiring `admin.access` *and* the Workspace Permission independently (existing tests in
   `tests/js/admin/access.test.ts` stay green). An unknown permission is not detected; the Workspace is simply never
   visible.
4. **Sections.** Built-in Sections first, then declared Sections in registration order; Workspaces inside a Section
   in registration order, built-in before added ones. A Section with no visible Workspace is hidden. A Workspace
   naming an unknown Section throws at boot. Declaring a Section id another Module already declared throws.
5. **Labels** are translation keys in the definition, so Modules choose their own keys and the route meta can
   reuse the title as document title (`RouterView` translates `meta.title`). Built-in Workspaces wrap the existing `admin.sections.<id>` / `admin.descriptions.<id>` keys.
6. **Collection time.** See mechanism below: all Modules exist and the route registrar is still open when the
   panel collects.

## Mechanism

Plugin hooks of the admin plugin cannot see other plugins' Modules: `runModules` visits plugins in discovery order
and `admin` comes first alphabetically. But `createApp` queues app extensions registered by plugins and initialises
them *after* the entry-point extensions in `resources/js/app.ts` (`ModuleExtension`, `RoutingExtension`, …) and
*before* `bootstrapper.run()`. `RoutingExtension` compiles the router only at the late stage, so its registrar is
still open at that point.

1. `AdminPlugin.extensions()` registers an `AdminExtension` (published as `app.admin`, augmenting
   `HawkiAppExtensions`). It owns the Section and Workspace registry.
2. `AdminModule.routes(registrar)` registers the home route and hands the registrar to the extension.
3. `AdminExtension.init(app)` iterates `app.modules.all`, calls `adminWorkspaces` on every Module that implements
   `AdminWorkspaceProvider` with a registrar bound to that Module (for namespacing and the URL prefix), registers the
   admin Module's own 3 Sections and 12 Workspaces the same way, then adds one guarded lazy route per Workspace.
4. Sidebar, overview and `AdminPage` read `app.admin.sections` / `app.admin.workspaces` instead of importing the
   static list. `AdminPage` keeps its explicit id prop (decision of 2026-09-14): built-in pages pass the bare id,
   pages of other Modules pass the full name.

## Known limitation

`editorSchema()` in `forms/schemas.ts` knows only the built-in Workspace ids and throws for any other key, so a
Workspace added by another Module can list and run actions but cannot open the editor yet. Making editor schemas
part of the Workspace definition is a follow-up.

## What a Module cannot do (deliberate)

- Add a column, row action, page action or Record Set to a Workspace it does not own. That is a different extension
  point ("Workspace extension"); not designed here.
- Register a permission. Permissions live in `App\Services\Admin\Permission` on the server.
- Reorder or hide built-in Sections or Workspaces, or place a Workspace outside a Section.
- Depend on app state at registration. Visibility is always evaluated at render time from `app.can(...)`.

## Scenarios checked

| Scenario | Outcome |
| --- | --- |
| Chat adds Workspace `prompts` into Section `ai` | Listed after *MCP servers* in the sidebar and overview |
| Billing Module adds Section `billing` with Workspaces `invoices` and `budgets` | New Section appears after *System*; hidden for users holding neither permission |
| Two Modules both declare Section `billing` | Boot error naming both Modules |
| Workspace with two Record Sets (servers and tools) | Already the case in `AdminMcp.svelte`; each Record Set has its own reads, writes and dialogs |
| Permission revoked while an added Workspace is open | `AdminPage` invalidates every Record Set of the page and shows the forbidden state, same as built-in Workspaces |
| Workspace Permission held but a resource returns 403 | That Record Set enters `authorizationDenied`; the other Record Sets of the page keep working |
| Workspace names a permission the server never registered | Invisible to everyone, no warning (decided) |
| Third-party Module (`isCorePlugin = false`) | Same contract; only the URL prefix differs |

## Implementation (done 2026-09-16)

Implemented on `feature/max/admin-panel` by two Codex agents (Terra: Record Set rename; Sol: registry, extension,
routes, sidebar, overview, page shell) with the parent fixing the page-shell imports and the Node test imports.
Files: `api.ts`, `registry.ts`, `AdminExtension.ts`, `workspaces.ts`, `recordSet.svelte.ts`,
`components/AdminSidebarSection.svelte`; `sections.ts`, `workspace.svelte.ts` and `AdminSidebarGroup.svelte` are gone.
Note for tests: `api.ts` re-exports Svelte components, so Node tests import `registry.ts` and `workspaces.ts` directly.

## Original implementation sketch

- New `AdminExtension` with `createAdminRegistry()` holding Sections and Workspaces with their owning Module;
  `sections.ts` becomes `workspaces.ts` with the admin Module's own declarations.
- `routes.ts` → home route only; Workspace routes come from the extension, path and name via `getPluginRoutePrefix`.
- `AdminSidebar.svelte` / `AdminHome.svelte` → iterate `app.admin.sections` instead of the three literals.
- `AdminSidebarGroup.svelte` → `AdminSidebarSection.svelte`, taking a Section definition (id, icon, title).
- `workspace.svelte.ts` → `recordSet.svelte.ts`; `AdminWorkspace` → `AdminRecordSet`, `useAdminWorkspace` →
  `useAdminRecordSet`; page-local variable names follow.
- New `api.ts` barrel re-exporting `useAdminRecordSet`, `AdminColumn`, `AdminPage`, `AdminSearch`, `AdminTable`,
  `AdminResultDialog` and the contract types.
- `access.test.ts` → add one Workspace from a fake core-plugin Module and one from a fake third-party Module,
  asserting URL, route name and the two independent permission checks.
