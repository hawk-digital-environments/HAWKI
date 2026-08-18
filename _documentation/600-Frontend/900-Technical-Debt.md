# Frontend Technical Debt

The violations register for the frontend. Audience-tagged so a reader knows whether a debt affects them. Each entry links to the relevant code or doc; domain pages link here rather than restating the debt.

Mirror of the backend register at [`../500-Backend/900-Technical-Debt.md`](../500-Backend/900-Technical-Debt.md).

---

## Deprecated / transitional scaffolding (scheduled for removal)

### Snippet system

**Status:** `:::danger` Deprecated. Scheduled for removal in the next release.

The `<svelte-snippet>` custom element, `SnippetExtension` (`app.snippets`), `LegacyToastExtension` (`app.toast`), and `legacyInitializeSnippetApps` exist only for pages that lack a `#hawki-app` mount point. The SPA shell (`ShellExtension` + `RouterView`) replaces them.

- Do not write new snippets. New pages mount via the SPA shell.
- Do not read from `app.snippets` or `app.toast` in new code.

**Audience:** contributors. Plugin authors should not register snippets.

### Legacy UI bridge

**Status:** `:::danger` Deprecated. Scheduled for removal in the next release.

`OldUiBridge`, `OldUiMessageHistory`, the `window.*` globals published by `provideLegacyGlobals()`, and the `dependencyLoader` exist only to bridge the new Svelte layer to the old vanilla-JS UI. New Svelte code must not read from `window.*` — import the real modules and use the hooks.

- The composer's only transport (`OldUiBridgeTransport`) depends on `OldUiBridge.triggerSendMessage()`. That coupling goes away when the routed chat transport lands.

**Audience:** contributors.

### `buildStorageFileUrl`

**Status:** `:::danger` Deprecated. Use `UriBuilder.storageFileUri` instead.

`$lib/utils/storageFileProxy.ts` is a legacy wrapper, published to legacy code as `window.buildStorageFileUrl`. New code should reach the URI builder through `useApp().uriBuilder` or `useRestApi()`'s `uriBuilder`.

**Audience:** contributors.

### Migration helpers

**Status:** `:::danger` Deprecated. Use `useApp().migration.*` instead.

`kernel/migrations/helpers.ts` exports `applyMigrations(runType)` and `hasPendingMigrations()`, both deprecated in favour of `useApp().migration.apply(runType)` / `useApp().migration.hasPending`. They remain only to support legacy code (`window.applyMigrations`).

**Audience:** contributors.

---

## Unsolved design problems

### Frontend migration rollback

**Status:** Open. No clean solution yet.

Frontend migrations run **deferred** — only after the user logs in (and, for `after_passkey`, after they unlock). If a plugin that registered a migration is later uninstalled, its migrations would need to roll back, but the migration source may no longer be present. The only way to handle this would be to serialize the PHP and JS migration code into the database so the rollback survives the plugin's removal — but that means a rollback could fail when the code it references has since changed.

For this reason **migrations are restricted to core plugins** (`HawkiCorePlugin`, not `HawkiPlugin`). Do not introduce migration registration on third-party plugins until the rollback story is settled.

- See [Concepts → Frontend Migrations](200-Concepts/180-Frontend-Migrations.md).

**Audience:** plugin authors, architects.

---

## Marked for refactoring

### `ClientExtension`

**Status:** `@todo` — not settled.

`ClientExtension` exposes a large surface (`app.client`, `app.restApi`, `app.uriBuilder`, `app.connection`, `app.authenticatedConnection`, `app.connectionWithUserInfo`, `app.linkPreviewApi`). The shape is flagged for further refactoring — don't over-rely on its exact structure yet.

**Audience:** contributors reaching the client surface directly.

### Routing base path hardcoded

**Status:** Temporary. Resolved in the next release.

`RoutingExtension.ready()` builds the router with a hardcoded `basePath: '/new'` to keep the SPA and legacy Blade UI separate during migration. This should be read from config once the SPA is the primary path.

- See [Concepts → Modules & Routing](200-Concepts/120-App-and-Kernel/120-Routing-and-Shell.md).

**Audience:** contributors.

### `tailwind-dummy.css`

`resources/css/tailwind-dummy.css` is an empty file referenced only by the shadcn `components.json` config. It serves no runtime purpose and can be removed once the shadcn config no longer expects it.

**Audience:** contributors touching the CSS/shadcn setup.

---

## Plugin system (not yet implemented)

Third-party (runtime-installed) plugins are not yet supported. `PluginExtension.autoRegisterInstalledPlugins()` is a no-op placeholder. Every plugin today is a built-in core plugin discovered via `import.meta.glob`.

**Audience:** plugin authors (watch this space).
