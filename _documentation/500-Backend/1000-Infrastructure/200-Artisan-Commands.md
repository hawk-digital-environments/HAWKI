# Artisan Command Reference

All commands below are custom HAWKI commands. Run them inside Docker via `bin/env`:

```
bin/env artisan <command> [options]
```

---

## AI Provider & Model Commands

| Command | Alias | Description |
|---|---|---|
| `ai:check-status` | — | Polls all AI providers and MCP servers; writes online/offline status to DB via `ModelStatusUpdater` and `McpServerStatusUpdater` |
| `ai:models:list` | — | Lists configured models; supports filtering options and `--json` output |
| `ai:config:sync` | `ai:models:sync` | Reads static config files (`config/model_providers.php`, `config/model_lists/`) and upserts providers + models to DB; `--force` bypasses change-detection hash |

---

## AI Tool Commands

| Command | Description |
|---|---|
| `ai:tools:sync` | Syncs function tool definitions from `config/tools.php` to DB via `FunctionToolSyncer`; also syncs MCP server definitions |
| `ai:tools:assign` | Interactively assigns tools to models |
| `ai:tools:configure` | Interactively configure settings for a specific tool |
| `ai:tools:list` | Lists all registered tools with their status |

---

## MCP Server Commands

| Command | Description |
|---|---|
| `ai:tools:mcp:add` | Register a new MCP server |
| `ai:tools:mcp:remove` | Remove an MCP server registration |

---

## External App Commands

| Command | Class | Description |
|---|---|---|
| `ext-app:create` | `ExtAppCreateCommand` | Register an external application; prints API token + RSA private key once — store them immediately, they are not shown again |
| `ext-app:list` | `ExtAppListCommand` | List all registered external applications |
| `ext-app:remove` | `ExtAppRemoveCommand` | Remove an external application and its system user |

---

## Personal Access Token Commands

:::note[Distinct from ext-app tokens]
These commands manage individual user Sanctum tokens (`ApiTokenService`). They are a separate
mechanism from the ext-app OAuth-like flow documented in
[External App Integration](../800-Encryption-and-Security/200-External-Apps.md).
:::

| Command | Class | Description |
|---|---|---|
| `app:token` | `CreateSanctumTokenForUser` | Create a Sanctum personal access token for a user (prompts for user lookup by username, email, or ID) |
| `app:token --revoke` | `CreateSanctumTokenForUser` | Revoke a specific token for a user (lists existing tokens then prompts for the token ID) |

---

## User Management Commands

| Command | Class | Description |
|---|---|---|
| `app:removeuser` | `Removeuser` | Remove a user and all their associated data from the database (irreversible) |
| `hawki:update-avatar {path}` | `UpdateHawkiAvatar` | Update the system AI user's avatar from a local file path |
| `migrate:avatars` | `MigrateAvatars` | Migrate legacy avatar files to the current `StoredFileIdentifier` format; supports `--dry-run`, `--force`, `--cleanup`, `--user`, `--room`, `--type` |

---

## Usage Analytics Commands

| Command | Class | Description |
|---|---|---|
| `usage:summarize-monthly` | `MonthlyUsageSummary` | Aggregate and prune the `usage_records` table; should run on a monthly cron schedule |
| `usage:top-users` | `TopTokenUsers` | Diagnostic: list top token consumers, filterable by model and month |
| `app:fetch-user-records` | `FetchUserRecords` | Developer diagnostic: dump raw usage totals (internal use only) |

---

## Announcement Commands

| Command | Class | Description |
|---|---|---|
| `announcement:make {title}` | `AnnouncementMake` | Scaffold per-language Markdown announcement files under `resources/announcements/` |
| `announcement:publish` | `AnnouncementPublish` | Persist an announcement to the database (interactive prompts for type, scheduling, targeting) |

---

## File Storage Commands

| Command | Class | Description |
|---|---|---|
| `filestorage:cleanup` | `CleanupFileStorage` | Delete old attachments and expired temporary uploads (6-month retention by default) |
| `filestorage:converter:types:list` | `FileStorageConverterTypesList` | Print accepted MIME types and file extensions for the currently configured file converter |
| `check:storage {--filesystem=}` | `CheckStorageConnection` | Smoke-test a storage backend by writing, reading, and deleting a test file; use `--filesystem=` to target a specific disk |

---

## Frontend Migration Commands

| Command | Class | Description |
|---|---|---|
| `make:frontend-migration <name>` | `MakeFrontendMigrationCommand` | Scaffold a paired PHP + TypeScript frontend migration file set |

---

## Development / Diagnostic Commands

| Command | Class | Description |
|---|---|---|
| `dev:ai:update-lite-llm-static-data` | `UpdateLiteLlmStaticDataCommand` | Refresh bundled LiteLLM static data files in `resources/static_llm_data/`; intended for local development use only, not production |

---

## Deprecated Commands

| Command | Class | Status | Notes |
|---|---|---|---|
| `app:list-gwdg` | `CheckGWDGModels` | **@deprecated** — scheduled for removal in v3.0 | Pings the GWDG provider API and prints model status. Superseded by `ai:check-status`. Emits a log warning on every run. |

---

## [v3 Feature] Planned Commands

The following commands are planned for HAWKI 3.0 and do not exist yet:

| Command | Purpose |
|---|---|
| `hawki:plugin:list` | Operator-visible list of installed plugins with name, version, and status |
| `make:hawki:plugin` | Interactive plugin scaffolding wizard |
| `hawki:plugins:composer:post-update` | Internal: runs `PluginPublisher` after `composer update` (hidden, wired via Composer hooks) |
| `hawki:plugins:composer:uninstall` | Internal: cleans up plugin assets on removal (hidden, wired via Composer hooks) |
