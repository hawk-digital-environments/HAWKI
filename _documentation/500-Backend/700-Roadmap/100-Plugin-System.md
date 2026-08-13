# Plugin System

:::note[Not yet implemented]
This page is a feature checklist derived from the design at `_architecture/hawk-ixdlab-docs/hawki/plugins/`. None of the classes, commands, or flows described here exist in the codebase yet. Live, usable-today extension points are in [Extending HAWKI](../200-Concepts/220-Extending-HAWKI.md).
:::

The HAWKI v3 plugin system will let third-party packages extend HAWKI without modifying core code. The full design lives in `_architecture/`; this page is a contributor-facing summary of what is planned, so readers know what is coming and which current patterns are groundwork for it.

## Goal

Make the HAWKI Laravel application extensible by third parties (university IT departments, internal HAWK teams) without requiring changes to the core codebase. A plugin should be able to:

- React to domain events (room created, message sent, user removed, …)
- Add new API routes and controllers
- Register custom SyncLog handlers (push real-time state to connected clients)
- Register custom AI Tools
- Extend the health-check result set
- Contribute frontend configuration / feature flags
- Optionally: add database migrations and model observers

## Implementation phases

The design is split into four phases. Open `_architecture/hawk-ixdlab-docs/hawki/plugins/06-implementation-phases.md` for the full task breakdown.

### Phase 1 — Core Plugin Infrastructure

The Laravel application can discover, load, and manage plugins via Composer. Plugins have a stable entry-point contract, service provider wiring, translation loading, asset publishing, and database-backed configuration. Docker entrypoint supports runtime plugin installation. Sub-phases:

- **1.1 Plugin Entry-Point Contract** — `HawkiPluginInterface` (`pluginName`, `pluginVersion`, `getNamespace()`, `getServiceProviders()`, `getTranslationLoader()`, `getMigrationPath()`, `getPublishPublicPath()`, `storagePath()`, `langPath()`, `webRoutesPath()`, `apiRoutesPath()`, `commandsPath()`, `eventListenerPaths()`) and `AbstractHawkiPlugin` with sensible defaults. The "Hello World" reference plugin at `hawk/hello-world-plugin` demonstrates the contract.
- **1.2 Plugin Registry & Bootstrap** — `PluginRegistry` (lazily instantiates `AbstractHawkiPlugin`; `all()`, `get()`, `guess()` by longest namespace prefix match, `getServiceProviders()`, `getTranslationLoaders()`; topological sort via `IntuitiveTopSorter`). `PluginAwareTrait` resolves containing plugin via `PLUGIN_NAME` constant or namespace prefix. `InstalledPlugins` is the `@internal` static bootstrap wrapper. `PluginModel` base class uses `PluginAwareTrait` for auto-derived collision-safe table names (`plugin_{vendor}_{name}_{model}`).
- **1.3 Plugin Cache & Composer Hooks** — `hawki:plugins:composer:post-update` (hidden) scans `composer.lock` for `hawki-plugin` packages, runs `Migrator::run()` for new/updated plugins, calls `PluginPublisher::publishAll()`, writes `bootstrap/cache/plugins.php`. `hawki:plugins:composer:uninstall` rolls back migrations and unpublishes assets before Composer deletes files. Root `composer.json` wires `post-update-cmd` and `pre-package-uninstall` hooks.
- **1.4 Asset Publishing** — `PluginPublisher::publish()` / `unpublish()` / `publishAll()` copies from `getPublishPublicPath()` to `publicPath()`.
- **1.5 Route Building** — `PluginRouteBuilder` collects routes from all plugins at bootstrap time. Route prefix convention: `GET /api/plugins/{vendor}/{plugin}/...`.
- **1.6 Migration Runner** — `Migrator::run($plugin->getMigrationPath())` and `Migrator::reset()` integrated into the Composer hooks. Plugin migrations run via dedicated commands, not `artisan migrate`.
- **1.7 Database-Backed Configuration** — `config` table (`namespace`, `key`, `value`), `ConfigDb` (SQL I/O), `ConfigService` (identity map, the public DI-injectable API), `ConfigSchema` / `ConfigBlueprint` for migrations, `AbstractConfig` extending `App\Utils\Casts\AbstractCastableObject` with `PluginAwareTrait` for automatic namespace derivation. Sensitive config moves from `.env` into the database.
- **1.8 Translation System Extension** — `TranslationLoaderInterface` per plugin; `LaravelTranslationLoaderAdapter` accepts multiple loaders, merges in load order (base → plugins → user overrides), with optional persistent cache.
- **1.9 Frontend Manifest Endpoint** — `GET /api/plugins/frontend-manifest` exposes installed plugin frontend metadata to the SPA.
- **1.10 Docker Integration** — Entrypoint reads `HAWKI_PLUGINS` (space-separated) and `HAWKI_PLUGINS_FILE`, runs `composer require` for missing packages, then `hawki:plugins:composer:post-update`. Lock file for multi-replica safety.
- **1.11 Developer Experience** — `plugins/composer/` (managed) and `plugins/local/` (path repository for active development). `make:hawki:plugin` wizard scaffolds a complete plugin skeleton. `hawki:plugin:list` operator command.
- **1.12 API Stability & Documentation** — Audit existing event classes, mark stable events with `@api`, document route naming and all extension points into a Plugin Author Guide.

### Phase 2 — First Real Plugin (DeepL)

Validate the entire system end-to-end with a plugin that has external PHP dependencies. Implement `hawk/deepl-plugin` as a Composer package with `type: hawki-plugin` using `deeplcom/deepl-php`. Verify Composer dependency conflict detection, Docker Layer 1 (vendor volume + entrypoint), Docker Layer 2 (baked-in image), and identify developer-experience friction.

### Phase 3 — Plugin Store UI

Allow operators to browse and install plugins from a UI rather than via Composer CLI. Query Packagist for packages with `type: hawki-plugin`, implement in-app install/uninstall flow, show installed plugins with version and updates.

### Phase 4 — Frontend Plugin Architecture

Enable third-party plugins to contribute UI components to the Svelte SPA. **Deferred** — depends on the SPA routing and slot/zone model being settled. Define `slotRegistry` TypeScript interface, implement runtime ESM loading for third-party plugins, wrap slot zones in Svelte 5 error boundaries, document the `defineFeature()` SDK contract.

## Key classes planned

| Class | Location | Role |
|---|---|---|
| `HawkiPluginInterface` | `app/Plugins/` | Plugin entry-point contract |
| `AbstractHawkiPlugin` | `app/Plugins/` | Base implementation with sensible defaults |
| `PluginRegistry` | `app/Services/Plugin/` | Sole public API for runtime plugin introspection |
| `InstalledPlugins` | `app/Services/Plugin/` | `@internal` static bootstrap wrapper |
| `PluginAwareTrait` | `app/Services/Plugin/` | Resolves containing plugin via `PLUGIN_NAME` constant or namespace prefix |
| `PluginModel` | `app/Models/Plugin/` | Base model with auto-derived collision-safe table names |
| `PluginPublisher` | `app/Services/Plugin/` | Publishes/unpublishes plugin public assets |
| `PluginRouteBuilder` | `app/Services/Plugin/` | Static helper for wiring plugin routes/commands into `bootstrap/app.php` |
| `ConfigDb` / `ConfigService` | `app/Services/Config/` | Database-backed config layer |
| `AbstractConfig` | `app/Config/` | Base class for typed config objects |
| `ConfigSchema` / `ConfigBlueprint` | `app/Services/Config/` | Migration-time config row management |

## Plugin directory structure planned

```
plugins/
├── composer/                      Composer-managed plugins (auto-installed, do not edit by hand)
│   └── hawk/
│       └── hello-world-plugin/    Reference implementation (committed to HAWKI repo)
└── local/                         Path repository for plugins under active development
    └── my-plugin/                 Create → `composer require hawk/my-plugin:@dev` → symlinked
```

## Command lifecycle planned

Plugins are managed entirely through Composer. There are no separate enable/disable commands.

| Action | What happens |
|--------|-------------|
| `composer require vendor/plugin` | Composer installs the package. `post-update-cmd` fires `hawki:plugins:composer:post-update`, which scans `composer.lock` for `type:hawki-plugin` packages, writes `bootstrap/cache/plugins.php`, runs plugin migrations, publishes plugin public assets. |
| `composer remove vendor/plugin` | `pre-package-uninstall` hook fires `hawki:plugins:composer:uninstall` first (while files are still present): rolls back plugin migrations, removes published assets. Then Composer removes the package. |
| `composer install` (existing lock) | `post-update-cmd` fires `hawki:plugins:composer:post-update` — picks up any new migrations/assets from previously installed plugins. |
| `hawki:plugin:list` | Operator command to view installed plugins, versions, and status. |

## Open questions blocking specific tasks

| #  | Question                                                | Blocks                                                |
|----|---------------------------------------------------------|-------------------------------------------------------|
| #4 | Frontend slot/zone model and `slotRegistry` API surface | Phase 4 entirely                                      |
| #7 | `storagePath()` and Flysystem convention for plugins    | Finalize `AbstractHawkiPlugin::storagePath()` default |
| #8 | User custom translation label overrides source          | Finish translation loader stack (Phase 1.8)           |

See `_architecture/hawk-ixdlab-docs/hawki/plugins/07-open-questions.md` for the full Q&A.

## Where the live extension points are today

The current extension points (registries, container tags, filter events, health-check listeners, `DecoratorTrait`) are the stable surface the v3 plugin system will build on. They are usable today without waiting for v3 — see [Extending HAWKI](../200-Concepts/220-Extending-HAWKI.md).
