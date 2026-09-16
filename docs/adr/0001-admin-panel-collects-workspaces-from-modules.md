---
status: proposed
---

# The Admin Panel collects Workspaces from Modules itself; the kernel stays unaware of it

Modules need to add their own Sections and Workspaces to the Admin Panel. The panel is a plugin, not part of the
kernel, and the lifecycle hooks of different plugins run in discovery order, so a contributing Module cannot rely on
the panel being ready when its own plugin's hooks run. We decided that a Module only *declares* its Sections and
Workspaces through an optional `adminWorkspaces(registrar)` method, and the admin plugin *pulls* those declarations
from every registered Module once all Modules exist, before the router is compiled. Nothing about the panel is added
to the kernel's `HawkiModule` or `HawkiPlugin` contracts.

## Considered Options

- **Kernel hook** (`HawkiModule.adminWorkspaces`, like `HawkiModule.search`): rejected because the kernel would gain
  a dependency on one plugin's concept, and the registry would exist even in a build without the panel.
- **Push via plugin lookup** (`context.plugins.get('admin').register(...)` from the contributor's hooks): rejected
  because it depends on hook ordering between plugins and on the panel accepting registrations before its routes
  are built, which is fragile and not visible at the call site.

## Consequences

- Declarations are static. Anything needing app state (permissions, config) is evaluated by the panel at render
  time, not by the Module at registration time.
- URLs follow the kernel's plugin rule: Workspaces of Modules from core plugins sit directly under `/admin/<id>`, so
  such a Module can later take over a built-in Workspace without moving its URL; Workspaces of third-party Modules
  sit under `/admin/plugins/<plugin-slug>/<id>`. Duplicate ids among core-plugin Workspaces fail at boot.
- The built-in Sections and Workspaces stay in the admin plugin. Moving them into `core` is possible but not planned.
