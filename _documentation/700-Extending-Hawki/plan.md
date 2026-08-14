# Plan: 700-Extending-Hawki

This section is the single home for plugin and extension authoring how-tos, cross-cutting frontend and backend. The frontend pages are done; the backend pages need writing. This file is a stub for the next agent to pick up.

## What's done

- `index.md` — section overview, routes to frontend pages + external backend links.
- `100-Writing-a-Frontend-Plugin.md` — the `HawkiPlugin`/`HawkiCorePlugin` contract, hooks, discovery, minimal plugin. Verified against code (core plugin implements `name, migrations, modules, routes, stores` — no `boot`/`ready`; snippets are registered by `legacyInitializeSnippetApps`, not the plugin).
- `200-Writing-a-Frontend-Extension.md` — the `HawkiAppExtension` contract, declaration merging, minimal extension, letting plugins contribute. Verified against code (no `bootstrapper` singleton; `ShellExtension` is a real example).

## What's missing — backend authoring pages

The backend has live, usable-today extension points documented at `500-Backend/200-Concepts/220-Extending-HAWKI.md` (agent registry, provider adapters, the `'ai.tool'` container tag, JSON:API schema registration, etc.). That page is currently the backend's "every extension point in one place" hub.

The question for the next agent: **move that content here, or keep it in the backend and link to it from here?**

### Suggested approach

Keep `500-Backend/200-Concepts/220-Extending-HAWKI.md` as the backend's concept page (it fits the backend Concepts hub), but write companion **how-to** pages here that walk a new author through adding each kind of backend extension, cross-linking to the concept page for the why. Proposed pages:

- `300-Writing-an-AI-Provider-Adapter.md` — implement `ProviderAdapterInterface`, register via `ProviderAdapterRegistry`.
- `400-Writing-an-AI-Tool.md` — implement a tool, register via the `'ai.tool'` container tag.
- `500-Writing-a-JSON-API-Resource.md` — schema + resource + filter + controller wiring for a new JSON:API resource.
- `600-Writing-a-Backend-Service.md` — a domain service under `app/Services/{Domain}/`, the lightweight DDD layering.

Each should mirror the frontend pages: real, copyable code with every convention-bearing line (`#[Singleton]`, `readonly`, `#[Config('...')]`, return types), not simplified examples. Read `500-Backend/200-Concepts/220-Extending-HAWKI.md` and the linked source first to confirm the current extension surface before writing.

### Unified plugin contract (v3)

The not-yet-implemented v3 plugin system (a single plugin spanning frontend + backend) lives at `500-Backend/700-Roadmap/100-Plugin-System.md`. When that lands, this section becomes the home for the unified authoring guide. Until then, the frontend plugin contract (`HawkiPlugin`) and the backend extension points are separate surfaces.

## Verification notes for the next agent

- The frontend pages above were verified against the actual code in `resources/js/kernel/` and `resources/js/plugins/core/core.plugin.ts` as of the restructure. Re-verify before writing the backend pages — the backend extension surface may have moved.
- Follow `.documentation.md` and the `documentator` skill: real copyable code, one audience per page, no method/field enumeration of internal classes, link to the source for the full surface.
