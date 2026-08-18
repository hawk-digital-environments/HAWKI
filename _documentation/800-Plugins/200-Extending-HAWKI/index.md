# Extending HAWKI

How to add features to HAWKI without touching core. Plugin and extension authoring touches both backend and frontend — this section is the single home for the how-to, cross-cutting both sides.

:::info[Under construction]
This section is being built up. The frontend authoring pages are here; the backend authoring pages will follow. For the live, usable-today backend extension points in the meantime, see [Backend Concepts → Extending HAWKI](../../500-Backend/200-Concepts/220-Extending-HAWKI.md).
:::

## How the docs are organised

| Page | What it covers |
|---|---|
| [Writing a Frontend Plugin](100-Writing-a-Frontend-Plugin.md) | The `HawkiPlugin` / `HawkiCorePlugin` contract, the lifecycle hooks, discovery, and a minimal plugin. |
| [Writing a Frontend Extension](200-Writing-a-Frontend-Extension.md) | The `HawkiAppExtension` contract for the rare case of a new app-wide subsystem. |
| [Backend Extension Points](../../500-Backend/200-Concepts/220-Extending-HAWKI.md) | Every live backend extension point in one place. |

## Frontend or backend?

| You want to add… | Where it lives |
|---|---|
| A frontend feature (stores, schemas, snippets, modules, routes) | [Writing a Frontend Plugin](100-Writing-a-Frontend-Plugin.md) |
| A new app-wide frontend subsystem (a new registry other extensions/plugins depend on) | [Writing a Frontend Extension](200-Writing-a-Frontend-Extension.md) |
| A backend feature (AI provider, tool, JSON:API resource, service) | [Backend Extension Points](../../500-Backend/200-Concepts/220-Extending-HAWKI.md) |
| A full plugin spanning both sides | Start with the frontend plugin + backend extension points; the unified plugin contract is part of the v3 roadmap. |

For the kernel internals behind these authoring pages (how plugins are discovered, how the bootstrapper dispatches hooks, how modules and routing work), see [Frontend Architecture](../../600-Frontend/300-Architecture/index.md).
