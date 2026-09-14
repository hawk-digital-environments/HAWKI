# Admin Panel: Scope

Status: draft scope, 2026-09-10. Branch context: `feature/max/admin-panel`.

This document scopes an Admin Panel for HAWKI covering: Models, MCP, Providers, Users, RBAC, Announcements, Usage/Stats, Health Monitor, System Settings (env-backed settings) and Employeetype-to-Role assignment. It is grounded in the current state of the repository (inventoried 2026-09-10) and lists, per area, what exists, what must be built, and the decisions that are still open.

---

## 1. Summary

**Where we stand.** HAWKI has no admin panel and no RBAC. "Admin" is the literal string `admin` in the `users.employeetype` column (`App\Services\Users\UserCondition::isAdmin`). That single check already gates admin-only JSON:API fields (provider API keys, MCP keys, tool internals, user emails), the `ExtAppPolicy`, and the ability to disable data-access scopes (`SystemServiceProvider` → `ScopeContext`). Everything an operator manages today is done via `.env` + `config/*.php` files synced into the database by artisan commands, or via ~15 artisan commands (announcements, MCP servers, tools, tokens, user removal, usage reports).

**The good news.** Most admin *data* already lives in the database with the right shape: `ai_providers` (incl. encrypted `api_key`), `ai_models`, `ai_tools`, `ai_model_tools`, `mcp_servers`, `system_models`, `system_prompts`, `announcements`, `usage_records`. The JSON:API layer (`app/JsonApi/V1`) exposes all of these read-only with admin-only field visibility already wired. The new Svelte frontend has a plugin/module system, a router with meta-guards, a typed JSON:API client with create/update/delete helpers, and a solid UI primitive set. The code base also already *announces* this work: `ConfigFileSync/README.md` calls the file sync "temporary, to be replaced by a database-only solution", and the configuration docs promise "the admin backend coming in v2.6".

**What the Admin Panel is, architecturally.**

1. An **RBAC foundation** (roles, code-defined permissions, role↔user, employeetype→role mapping) replacing the `employeetype === 'admin'` check.
2. **Write operations on the existing JSON:API resources**, gated by permission-aware policies. No legacy controllers are touched (per `CLAUDE.md`).
3. A new **`admin` frontend plugin** (`resources/js/plugins/admin/`) with one module and one page per area, guarded by a new `permission` route meta.
4. A handful of **new backend concepts** where nothing exists yet: durable usage aggregates, a settings overlay for env-backed config, announcement content in the DB, a richer health/status endpoint.

**Biggest decision.** Models/Providers/MCP: the file→DB sync currently *overwrites and disables* DB rows from `config/model_providers.php` on every sync. An admin panel that edits these rows only makes sense if the DB becomes the source of truth and the file sync becomes an import (see §4.1). This is the one decision that shapes the most code and should be made first.

---

## 2. Current-state inventory (condensed)

Details and file references are in §4 per area. Cross-cutting facts:

| Topic | Current state |
|---|---|
| Admin identity | `users.employeetype === 'admin'` (`UserCondition::isAdmin`). No `is_admin`, no roles/permissions tables. |
| `employeetype` source | Set at login from LDAP (`LDAP_ATTR_EMPLOYEETYPE`), Shibboleth (`SHIBBOLETH_EMPLOYEETYPE_VAR`), OIDC (`OIDC_EMPLOYEETYPE_VAR`) or test users, via `AuthenticatedUserInfo::$employeeType`. Docblock: "will, in the future, also be used for permission management". |
| Policies | `app/Policies/*`. Admin helpers exist in `CommonPolicyChecksTrait` (`isAdmin`, `isAdminResponse`, `isAdminOr…`). AI resource policies only implement `viewAny`/`view`. `UserPolicy::update` = self only. |
| Data scopes | `HasContextualScopesTrait` + `ScopeContext`; admins may disable access scopes (e.g. `KnownUsersAccessScope`) to see all users. Already the right hook for admin listing. |
| JSON:API | `/api/hawki/v1`, Laravel JSON:API. All config resources `only('index','show')` or `readOnly()`. `users` has `index, show, update` (self). Non-Eloquent resources exist as a pattern (`configs`, `ai-model-flags`, `ai-tool-capabilities`). |
| Frontend | Kernel + plugins (`core`, `auth`). Modules give title/icon/routes/sidebar. Router base `/new`, meta guards `public | server-session | crypto-ready`. `RestApi` has `createResource/updateResource/deleteResource` + action helpers, Zod schemas per resource, pagination/filter/sort query builder. |
| Frontend user info | `connections` resource: `id, name, username, email, avatar, bio, hash`. No permissions, no admin flag. |
| UI primitives | Button, Input, Textarea, SingleSelect, Switch, Slider, Tabs, Dialog/ConfirmDialog/InfoDialog, DropdownMenu, Badge, StatusDot, Alert, Toast, Loader, RadialProgress, Page/PageHeaderBar, Sidebar. **Missing:** DataTable, form/validation layer, MultiSelect/Combobox, DateTimePicker, JSON/code editor, charts. |
| Tests | Backend: PHPUnit feature tests incl. `ApiV1EndpointsTest`, `AnnouncementsApiTest`. Frontend: node test runner (`bin/env npm run test:auth`, `test:search`). No Vitest/Playwright. |
| Production config | `config:cache` at container start; `.env` injected via `env_file`. Env edits need a restart; the app cannot write its own `.env`. |

Side findings worth fixing regardless (docs contradict code):

- `_documentation/200-Configuration/200-Model-Configuration-Variables.md` says provider keys are "never stored in the database"; they are (encrypted, `ai_providers.api_key`).
- `_documentation/600-Frontend/600-Advanced/200-Routing.md` says routing is not wired; `RoutingExtension` builds the `/new` router today.
- `usage:summarize-monthly` deletes raw usage rows but the summary persistence is a no-op (`UsageAnalyzerService`), and the command is not scheduled. Data loss if someone runs it.

---

## 3. Cross-cutting foundations

These must exist before any single area is useful. They are the "Phase 0" of §6.

### 3.1 RBAC data model and services (backend)

**Permissions are code, roles are data.** Permissions are string constants defined in PHP (a `Permission` enum or registry, e.g. `admin.access`, `users.view`, `users.manage`, `roles.manage`, `models.manage`, `providers.manage`, `mcp.manage`, `announcements.manage`, `usage.view`, `usage.view-per-user`, `health.view`, `settings.manage`). A registry (same pattern as `AiModelFlagRegistry` / `PublicConfigRegistry`) exposes them with title/description labels so the UI can render them. Roles are DB rows holding a set of permissions.

New tables:

| Table | Columns | Notes |
|---|---|---|
| `roles` | `id, slug (unique), name, description, is_system (bool), created_at, updated_at` | `is_system` protects built-ins (`admin`, `user`) from deletion. |
| `role_permissions` | `role_id, permission (string)` | Unique pair. Unknown permission strings are ignored at read time, so removing a permission from code does not break. |
| `role_user` | `role_id, user_id, source (enum: manual, employeetype), created_at` | `source` distinguishes admin-assigned from mapping-derived rows. |
| `employee_type_role_mappings` | `id, employee_type (string, unique), role_id, created_at, updated_at` | Exact-match on the incoming `employeetype`. Pattern matching (glob/regex) is a possible later extension; see open question. |

Services:

- `PermissionService` (or a `HasRoles` user trait): `hasPermission(User, string): bool`, `permissionsOf(User): string[]`. Cached per request.
- `UserCondition::isAdmin()` becomes `hasPermission($user, 'admin.access')`. All existing call sites (policies, schema `hidden()` conditions, `ScopeContext` guard) keep working unchanged. This is the single most valuable seam in the whole project: one function, already used everywhere it matters.
- `EmployeeTypeRoleSyncer`: runs on every successful login (hook into `UserRepository` / registration flow where `employeetype` is written). It replaces all `role_user` rows with `source = employeetype` for that user according to the current mapping, and never touches `source = manual` rows.
- Migration: create built-in roles `admin` (all permissions) and `user` (none). Grant `admin` (source `manual`) to every user whose `employeetype === 'admin'` so nothing regresses. Also seed a mapping `admin → admin` so the existing convention keeps working for fresh logins.
- Artisan `rbac:grant {username} {role}` / `rbac:revoke` for bootstrapping the first admin without a UI, mirroring the existing `app:token` style.

Policies: add `create/update/delete` to every AI/announcement/user policy, each delegating to a permission. Keep the `CommonPolicyChecksTrait` helpers, add `hasPermissionResponse(...)`.

### 3.2 Exposing permissions to the frontend

Add a read-only `permissions: string[]` attribute to the `connections` resource (the current-user document the frontend already loads at boot) and mirror it in `connections.schema.ts`. Add `app.can(permission)` / `useCan()` in the kernel (`kernel/auth`). No separate request needed.

### 3.3 Admin plugin skeleton (frontend)

- `resources/js/plugins/admin/admin.plugin.ts`, discovered automatically. Plugin routes get the `/plugins/admin` prefix from `getPluginRoutePrefix`, so the panel lives at `/new/plugins/admin/...`.
- One module `admin:panel` with `title()` ("Administration"), an icon, a `sidebar()` listing the sections the current user may see, and one `lazyRoute` per section. Module selector shows it only when `admin.access` is present (the module selector needs a `visible(app)` hook or the plugin skips registering the module when the permission is absent; the latter is simpler and works because `modules()` runs after config/connection are available in `boot`, so verify timing, or register always and hide in the selector).
- New route meta `permission: 'users.view'` handled by a `PermissionMiddleware` registered alongside `authMetaGuards` in `RoutingExtension`. Denied → render a "forbidden" route result (`RouteResultBody`), not a redirect to login.
- Translations: `admin.*` keys in `ui_de_DE.json` / `ui_en_US.json`.

### 3.4 Shared admin UI primitives (frontend, `components/ui/`)

Build once, reuse in all sections. Candidates, in priority order:

1. **DataTable** (columns, sort, server pagination via `_pagination`, row actions, empty state, loading). Every section needs it.
2. **Form layer**: `Field` wrapper (label, description, error), `useForm` that maps JSON:API `errors[]` (`source.pointer`) to fields, dirty tracking, submit state. Zod resource schemas already exist and can validate client-side.
3. **MultiSelect / Combobox** (roles on a user, models on a tool, tool assignment on a model).
4. **DateTimePicker** (announcement start/expiry).
5. **JsonEditor** (a Textarea with JSON validation and pretty-print is enough for v1; a real editor is out of scope).
6. **KeyValueList** for settings and provider `additional_config`.
7. **StatCard / simple bar & line chart** for usage and health. Follow the `dataviz` skill when implementing.

### 3.5 Backend write pattern for JSON:API

Per resource: enable `store/update/destroy` in `routes/api.php`, add a `*Request` with validation rules, extend the policy, and mark non-writable fields `readOnly()` in the schema. Actions (`->actions(...)`) for non-CRUD verbs (test connection, discover tools, publish, re-sync). Tests extend `ApiV1EndpointsTest`-style feature tests with an admin and a non-admin user for every new route (403 paths included).

---

## 4. Scope per area

Each area: what exists, what to build (backend / frontend), permissions, open questions, size (S/M/L/XL relative to each other).

### 4.1 Models and Providers

**Exists.**
`ai_providers` (`provider_id, name, active, api_url, model_status_url, adapter_key, api_key (encrypted), settings`), `ai_models` (`active, status, demand, model_type, model_id, label, documentation_url, deprecation_date, input, output, parameters, native_capabilities, settings, limits, pricing, flags`), `ai_model_descriptions` (per locale), `ai_model_usage_rules` (`usage_type`: main app vs. ext app), `system_models` (which model does title generation etc.), `system_prompts` (per locale/type). Adapters registered by key in `AiServiceProvider` (OpenAI, Azure, Ollama, Gemini, GWDG, OpenAI-like, OpenRouter, Anthropic, Bedrock, …). `ai:check-status` (scheduled every 15 min) refreshes `status`. JSON:API: all read-only; admin-only fields hidden for non-admins. Frontend: `core` has a read-only `/models` page and `AiModelStore`.

Source of truth today: `.env` → `config/model_providers.php` + `config/model_lists/*.php` → `ai:config:sync` → DB. The syncer **overwrites** provider fields (incl. API key and `active`), upserts models, and **disables models not listed** in the file. `SyncActionDetector` skips unchanged configs by hash. The sync also runs inside migrations.

**Decision required (blocks this area).** Choose one:

- **A. DB is the source of truth (recommended, matches `ConfigFileSync/README.md`).** File sync becomes an explicit *import* (`ai:config:import`, plus an "Import from config files" button). Add `managed_by` (`file | admin`) or reuse the existing `added_by_file` pattern on providers and models; the import never overwrites rows that an admin has edited unless `--force`. Migrations stop running the sync. The `.env` provider keys remain a convenient way to seed a fresh install.
- **B. Files stay the source of truth.** Panel is read-only for providers/models plus a few safe toggles (`active`, descriptions, flags, tool assignments) that the sync learns not to overwrite. Less work, but a half-admin panel that contradicts the stated direction.

The rest of this section assumes **A**.

**Build, backend.**
- `ai-providers`: `store/update/destroy`. Writable: `name, active, adapter_key, api_url, model_status_url, api_key (write-only: never echoed back, show "set/not set"), additional_config, settings`. Action `actions/test-connection` (ping via adapter). Action `actions/discover-models` (adapter `listModels()`, returns candidates without persisting). `provider_id` slug generated on create.
- `ai-models`: `store/update/destroy`. Writable: `active, label, model_type, documentation_url, deprecation_date, input, output, parameters, native_capabilities, settings, limits, pricing, flags, demand`, relationship `tools` (writable many-to-many over `ai_model_tools`), `usage_rules` as an attribute array. Action `actions/refresh-metadata` (re-fetch live metadata like the syncer does today). `model_id` immutable after create (FK from `system_models`).
- `ai-model-descriptions`: `store/update/destroy`.
- `system-models`: `update` (pick a model per `model_type × usage_type`). Validation: model exists, active, has matching usage rule (reuse `SystemModelSyncer` rules).
- `system-prompts`: `update` (edit prompt text per locale). Optional `store` for new locales.
- Refactor `ModelAndProviderSyncer` into an import service with a conflict policy. Keep `ai:config:sync` as a deprecated alias.
- Extend the `models` frontend store invalidation: after a write, the chat's `AiModelStore` must reload (emit an event or refetch).

**Build, frontend.**
- Providers list (status dot from model statuses, active switch, adapter, key set?), provider form, "test connection", "discover models" dialog with multi-select to add models.
- Models list (filter by provider/active/status, sort by label), model form with tabs: General, Capabilities/Flags, Parameters & Limits (JSON editors), Descriptions (per locale), Tools (assignment), Usage (main/ext app).
- System models page (one select per slot) and System prompts page (per locale textarea, markdown preview).

**Permissions.** `providers.manage`, `models.manage` (system models/prompts fall under `models.manage`).

**Open questions.**
- Model flags and tool capabilities are code registries. Editing *definitions* is out of scope; assigning them is in scope.
- Should ext-app default models (`default_models_ext_app`) become part of `system_models` UI? They already are rows (`usage_type`), so yes, as a second column.

**Size.** XL (largest area; the import refactor is most of it).

### 4.2 MCP servers and tools

**Exists.**
`mcp_servers` (`type sse|http|stdio, url, server_label, description, require_approval, api_key (encrypted), status, timeouts, additional_config, added_by_file`), `ai_tools` (`type mcp|function, name, class_name, mcp_server_id, mcp_name, mcp_config, description, capability, mapped_capability, active, added_by_file`), `ai_model_tools`. Services: `McpClientFactory`, `HawkiMcpClient` (`listTools`), `McpToolSyncer` (discover → upsert → delete missing), `McpServerStatusUpdater`. Artisan: `ai:tools:mcp:add|configure|remove`, `ai:tools:sync`, `ai:tools:assign`, `ai:tools:configure`. Config file `config/tools.php` is *deployment-only* by its own comments. JSON:API: `mcp-servers`, `ai-tools`, `ai-tool-capabilities` read-only.

**Build, backend.**
- `mcp-servers`: `store/update/destroy`. Actions: `actions/test` (ping + protocol/version), `actions/discover-tools` (run `McpToolSyncer` for this server, return diff). Destroy cascades tools (mirror `RemoveMcpServer`).
- `ai-tools`: `update` (`active, description, mapped_capability, settings`), relationship `models` writable. Function tools remain code-defined (`config/tools.php` `available_tools`); only their activation/assignment is editable.
- Extract the logic from the `ai:tools:mcp:*` commands into services so CLI and API share one path (they mostly already call services; verify per command).
- `McpServerSyncer` from `config/tools.php`: same import semantics as §4.1 (respect `added_by_file`, which already exists here).

**Build, frontend.**
- MCP servers list (status, tool count, type), server form (type-dependent fields: URL/headers for http/sse, args/env for stdio; API key write-only), "Test" and "Discover tools" with a result dialog.
- Tools list (filter by server/type/capability/active), tool detail with model assignment (multi-select of models) and capability mapping.
- On model form (§4.1): tools tab is the inverse view of the same pivot.

**Permissions.** `mcp.manage` (covers tools).

**Open questions.** Should `require_approval` become user-facing later (per-conversation approval)? Not in scope, but the field is edited here.

**Size.** L.

### 4.3 Users

**Exists.**
`users` (`name, email, username, publicKey, employeetype, avatar_id, bio, locale, isRemoved, registration_fingerprint`). `KnownUsersAccessScope` restricts listing to co-members; admins can disable it via `ScopeContext`. JSON:API `users`: `index, show, update(self)`, admin-visible `email`/`employee_type`, no filters, page pagination. Artisan `app:removeuser` (deletes user data), `app:token` (Sanctum tokens), `ALLOW_USER_TOKEN_CREATION`. E2E encryption: admins can never read chat content; "reset profile" exists as a self action.

**Build, backend.**
- `users` index for admins: disable the access scope when the requester has `users.view` (via `ScopeContext`), add filters `search` (name/username/email), `employee_type`, `role`, `removed`, sort by `name/created_at`. Expose `roles` relationship (read; writable with `roles.manage`), `employee_type`, `is_removed`, `created_at`, `last_login_at` (new column, set at login; cheap and very useful).
- Admin `update`: `is_removed` (deactivate/reactivate), `name`? (comes from IdP, probably read-only), manual roles.
- Actions: `actions/remove-data` (wrap `Removeuser` logic; destructive, confirm), `actions/revoke-tokens`, `actions/tokens` (list). Optional: `actions/reset-profile` for another user is *not* offered; it destroys keychain data and must remain a self action.
- Migration for `last_login_at`.

**Build, frontend.**
- Users table with search, filters, role chips, removed badge. User detail drawer/page: profile summary, roles (manual vs. derived from employeetype, the derived ones shown but not editable here), tokens, danger zone (deactivate, delete data).

**Permissions.** `users.view`, `users.manage`, `roles.manage` (assign roles to users).

**Open questions.**
- Bulk operations (multi-select → assign role)? Suggest v2.
- GDPR: is "remove user data" from the panel acceptable, or CLI-only with 2-person rule? Propose: available in panel with confirm dialog that requires typing the username.

**Size.** M.

### 4.4 RBAC (roles and permissions UI) and Employeetype → Role assignment

**Exists.** Nothing beyond the `employeetype === 'admin'` check (§3.1 describes the new model). Known employee types are observable as `SELECT DISTINCT employeetype FROM users`.

**Build, backend.**
- New JSON:API resources: `roles` (`slug, name, description, is_system, permissions[], user_count`), `permissions` (non-Eloquent registry: `key, title_label, description_label, group`), `employee-type-role-mappings` (`employee_type, role` relationship). Action `roles/{id}/actions/preview-users` optional.
- `users` action `actions/employee-types` (or a filter meta) returning distinct observed employeetypes with counts, so the mapping UI can offer real values.
- `EmployeeTypeRoleSyncer` on login (§3.1). Also an action `employee-type-role-mappings/actions/apply-now` to re-sync all users immediately after editing mappings (queued job for large installs).
- Guardrails: cannot delete `is_system` roles; cannot remove `admin.access` from the last admin; cannot remove your own `admin.access` (lockout protection).

**Build, frontend.**
- Roles page: list, role form with permission checkboxes grouped by area, member count.
- Employeetype → Role page: table of observed employee types (with user counts) × assigned role; "unmapped" types highlighted; explain that mapping applies at next login plus an "Apply now" button.
- User detail shows derived vs. manual roles (§4.3).

**Permissions.** `roles.manage`.

**Open questions.**
- One role per employeetype (simple) or many? Propose many-to-many is cheap; start with the table allowing multiple rows per employeetype.
- Exact match vs. patterns. Shibboleth/OIDC may deliver multi-valued attributes (`isMemberOf`, `roles`). Today `employeetype` is a single string, so exact match. Multi-value support would require changing `AuthenticatedUserInfo`; flag as v2.

**Size.** M (backend), M (frontend).

### 4.5 Announcements

**Exists.**
`announcements` (`title, view, type policy|news|system|event|info, is_forced, is_global, target_users (json ids), anchor, starts_at, expires_at`), pivot `announcement_user` (`seen_at, accepted_at, locale, content_hash`). Content is **Markdown on disk**: `resources/announcements/{view}/{locale}.md` resolved by `AnnouncementContentResolver`. Created via `announcement:make` + `announcement:publish`. JSON:API user-scoped read-only + `seen/accept` actions; `seen_count` exposed. `RegistrationPolicyService` ties the `policy` type to registration consent; a health listener checks a policy exists.

**Build, backend.**
- Move content to DB: `announcement_contents` (`announcement_id, locale, content (markdown), content_hash`). `AnnouncementContentResolver` reads DB first, falls back to files (keeps existing announcements and the migration-seeded ones working). A one-off command imports existing files.
- `announcements` admin write: `store/update/destroy`, plus `contents` as a relationship or nested attribute. Add `target_roles (json role ids)` beside `target_users` and extend the visibility query (`UserAnnouncementRepository`, `AnnouncementService`). Actions: `actions/preview` (render markdown for a locale), `actions/stats` (seen/accepted counts, per locale). Consider `is_draft` (or `starts_at = null` meaning draft) so admins can save before publishing.
- Guard: a forced `policy` announcement change re-triggers consent via `content_hash` (already how consent tracking works; confirm that editing content bumps the hash).

**Build, frontend.**
- Announcements list (type badge, active/scheduled/expired, seen/accepted counts), editor with per-locale markdown tabs and preview, targeting (all / roles / users), schedule, forced switch. Reuse the existing `Announcements.svelte` renderer for preview.

**Permissions.** `announcements.manage`.

**Open questions.** Keep the on-disk files as a fallback forever, or remove after import in a later release?

**Size.** M.

### 4.6 Usage / Stats

**Exists.**
`usage_records` (`user_id, room_id (always null today), prompt_tokens, completion_tokens, type private|group|api, model, created_at`) written by `UsageAnalyzerService::submitUsageRecord` from `StreamController`. `usage:top-users`, `app:fetch-user-records` (debug), `usage:summarize-monthly` (aggregates then **deletes raw rows; persistence is a no-op**; not scheduled). No API.

**Build, backend.**
- Fix retention first: new `usage_summaries` table (`period (date, month), user_id nullable, model, type, prompt_tokens, completion_tokens, request_count`). Make the summarizer persist, then delete raw rows older than N months (configurable, default 3). Schedule it monthly. This is a bug fix independent of the panel but the panel depends on it for history.
- Also record `ai_model_id`/provider and `room_id` where known, so stats can group by provider.
- Read-only non-Eloquent JSON:API resource `usage-stats` with query params (`from, to, group_by (day|month|model|provider|type|user), model, user`) returning series. Implemented as an action or a repository over both raw and summary tables (union by period). Per-user breakdown requires `usage.view-per-user`.
- Overview numbers: active users (users with usage in period), requests, tokens, top models.

**Build, frontend.**
- Dashboard: period picker, stat cards (requests, tokens in/out, active users), line chart tokens over time, bar chart by model, table by model/provider, optional table by user (permission-gated, with a privacy notice).

**Permissions.** `usage.view`, `usage.view-per-user`.

**Open questions.** Cost estimates using `ai_models.pricing`? The data exists; suggest showing "estimated cost" only when pricing is set for the model. Data-protection sign-off for per-user views.

**Size.** M (plus S for the retention fix).

### 4.7 Health Monitor

**Exists.**
`GET /health` (unauthenticated, for Docker): DB, cache, Redis, storage, registration-policy check; quick vs. deep via `HealthTimer`. Model/MCP `status` columns refreshed every 15 min by `ai:check-status`. Queue worker, scheduler and Reverb run as separate containers; no Horizon. Version in `config/hawki_version.json`.

**Build, backend.**
- Non-Eloquent JSON:API resource `system-health` (show only, admin): deep `HealthChecker` results, provider/model/MCP status summary (counts online/offline/unknown, list of offline), queue stats (`jobs` size per queue, `failed_jobs` count and last 20), scheduler heartbeat (write a cache key from a scheduled `schedule:run` hook every minute; report "last seen"), storage disk info, app/PHP/Laravel versions, `APP_ENV`, `APP_DEBUG` warning, config cached yes/no.
- Actions: `actions/check-ai-status` (run `ai:check-status` now, queued), `actions/retry-failed-job/{id}`, `actions/flush-failed-jobs`.

**Build, frontend.**
- Health page: status tiles per check, AI status table with per-model status dots, queue/failed jobs panel, versions box, "Run checks now" button, auto-refresh every 30 s while open.

**Permissions.** `health.view` (and `health.manage` for retry/flush, or fold into `settings.manage`).

**Size.** S–M.

### 4.8 System Settings (env-backed settings)

**Exists.**
~150 env keys documented in `.env.example`, consumed by `config/*.php`. Public subset exposed to the frontend via `PublicConfigRegistry` → `configs/public`. Production runs `config:cache`; `.env` is injected read-only by Docker `env_file`. There is no settings table.

**Constraint.** The application cannot and should not edit `.env` (multi-container, read-only, needs restart, secrets). "Env file settings" therefore means: **a DB overlay for an allow-listed set of keys, plus a read-only, secret-masked view of the effective configuration.**

**Build, backend.**
- `settings` table (`key (unique), value (json), updated_by, updated_at`) and a `SettingDefinitionRegistry` in code: for each editable key, its config path, type (bool/int/string/enum/list/secret), validation, label/description, and `requires_restart: false` (only runtime-safe keys are allowed). A service provider `boot()` step reads the table (cached) and applies `config()->set(path, value)` before anything consumes it. Env stays the default; the DB overrides.
- Initial allow-list (runtime-safe, non-infrastructure): `AI_MENTION_HANDLE`, `ACCESSIBILITY_STATEMENT_URL`, `IMPRINT_LOCATION`, passkey security flags, `ALLOW_EXTERNAL_COMMUNICATION`, `ALLOW_USER_TOKEN_CREATION`, `ALLOW_EXTERNAL_APPS*`, `MAX_FILE_SIZE`, `MAX_AVATAR_FILE_SIZE`, `MAX_ATTACHMENT_FILES`, `ALLOWED_*_MIME_TYPES`, `REMOVE_FILES_AFTER_MONTHS`, `FILE_CONVERTER` + converter URLs/keys (secret), `CHECK_TOOL_STATUS`, `DB_BACKUP_INTERVAL*`, `SESSION_LIFETIME`? (check whether session config is read per request; if cached at boot it still works since the overlay runs at boot). Explicitly excluded: `APP_KEY`, salts, DB/Redis/Mail/Reverb connection settings, auth provider settings (LDAP/OIDC/Shibboleth) in v1.
- JSON:API `settings` resource: `index` (definitions merged with current effective value, source `env|database|default`, secrets masked), `update` (validate by definition), `destroy` (reset to env/default). Second resource `environment` (show only): the full effective config for the documented `.env.example` keys, secrets masked, for diagnostics.
- Cache invalidation on write (settings cache key), and a note in the UI when a key is overridden by DB while a different value sits in `.env`.

**Build, frontend.**
- Settings page grouped by section from the registry, each row: label, description, control by type, source badge (env / database / default), reset button. Secrets show "set" with a replace field. Read-only "Environment" tab listing everything else, masked.

**Permissions.** `settings.manage`, `settings.view` for the read-only environment tab.

**Open questions.**
- Which keys are truly runtime-safe must be verified per key (some are read once by service providers into singletons). Each key added to the allow-list needs a test that a DB override takes effect.
- Maintenance mode toggle (`php artisan down`) as an action here? Cheap and useful; propose yes, with a confirm.

**Size.** M (registry + overlay S–M, UI S, verification per key adds up).

---

## 5. Non-functional requirements

- **Authorization on every write path**, tested for admin and non-admin (403). Field-level visibility keeps using `hidden(UserCondition::isNonAdmin(...))`, which becomes permission-based automatically.
- **Audit log** (recommended, small): `admin_audit_log` (`user_id, action, resource_type, resource_id, changes (json, secrets redacted), ip, created_at`) written from a JSON:API listener / model observer for all admin resources. Shown as a table in the panel later; write-only in v1. Without it, key rotations and role changes are untraceable.
- **Secrets never round-trip**: API keys are write-only attributes; responses carry `api_key_set: bool`.
- **Accessibility**: apply the `a11y` skill to every new component (tables, dialogs, live regions for save/error).
- **i18n**: all strings via translator; German and English.
- **Docs**: update `_documentation/200-Configuration` (source-of-truth change), add `_documentation/500-Backend/…/RBAC.md`, a "Administration" user guide, and the `_changelog/next.md` entries (including an upgrade note for the RBAC migration and the file-sync behaviour change).
- **Frontend tests**: node runner suites for `PermissionMiddleware`, `useCan`, DataTable and form helpers, mirroring `tests/js/auth`.

---

## 6. Phasing

Each phase is shippable on its own and unlocks the next.

| Phase | Content | Depends on | Size |
|---|---|---|---|
| 0. Foundations | RBAC tables/services, `isAdmin` → permissions, `permissions` on `connections`, `rbac:grant`, `admin` plugin skeleton + `permission` route guard, DataTable + form layer, audit log table, write-pattern reference implementation on one small resource | – | L |
| 1. People | Users (list/detail/deactivate), Roles, Employeetype→Role mapping, Health monitor (read-only) | 0 | L |
| 2. AI config | Decision A/B on file sync; Providers, Models, Descriptions, System models/prompts, import service | 0 | XL |
| 3. Tools | MCP servers, tools, model↔tool assignment | 2 (shares model form) | L |
| 4. Comms & insight | Announcements (DB content, role targeting), Usage retention fix + stats dashboard | 0, 1 (roles for targeting) | L |
| 5. Settings | Settings registry/overlay, settings + environment pages, maintenance mode | 0 | M |

Suggested order for maximum early value with minimum risk: 0 → 1 → 5 → 4 → 2 → 3, unless the file-sync decision is made quickly, in which case 2 should follow 1 since it is the headline feature.

---

## 7. Out of scope (explicitly)

- Editing legacy Blade/`public/js` UI or adding legacy routes (CLAUDE.md).
- Per-user or per-role model access control and quotas (no data model; a natural v2 once roles exist: `ai_model_usage_rules` could gain a `role_id`).
- Editing model flag or tool capability *definitions* (code registries).
- Function-tool creation from the UI (PHP classes).
- Third-party/runtime plugin installation (kernel does not support it yet).
- Writing `.env` or restarting services from the panel.
- Multi-valued employeetype attributes (single string today).
- Reading chat content, rooms or messages (E2E encrypted; not possible by design).

---

## 8. Open decisions (need an answer before the affected phase)

1. **File sync vs. DB as source of truth for providers/models/MCP** (§4.1 A/B). Recommendation: A.
2. **Where the panel lives**: as a plugin under `/new/plugins/admin` (default route namespacing) vs. registering the module in `core` to get `/new/admin`. Recommendation: separate `admin` plugin; accept the `/plugins/admin` prefix or add a `routePrefix` override to the plugin contract if the URL matters.
3. **Announcement content storage**: DB with file fallback (recommended) vs. keep files.
4. **Per-user usage visibility**: separate permission and data-protection sign-off (recommended) vs. not offering it.
5. **User data deletion in the panel** vs. CLI-only.
6. **Settings allow-list v1**: confirm the proposed key set in §4.8.
7. **Audit log in Phase 0** (recommended) vs. later.

---

## 9. Key files (entry points for implementation)

Backend
- `app/Services/Users/UserCondition.php` (the admin seam)
- `app/Policies/Traits/CommonPolicyChecksTrait.php`, `app/Policies/*`
- `app/Providers/SystemServiceProvider.php` (ScopeContext guard), `app/Models/Scopes/KnownUsersAccessScope.php`
- `app/Services/Auth/Value/AuthenticatedUserInfo.php`, `app/Services/Users/Repositories/UserRepository.php` (login hook for role sync)
- `routes/api.php`, `app/JsonApi/V1/*`, `app/Http/Controllers/Api/V1/*`
- `app/Services/Ai/ConfigFileSync/*` (to become import), `app/Services/Ai/Tools/Mcp/*`, `app/Console/Commands/Ai/Tools/*`
- `app/Services/Announcements/*`, `app/Services/Ai/UsageAnalyzerService.php`, `app/Services/System/Health/HealthChecker.php`
- `app/Services/Config/Registries/PublicConfigRegistry.php` (pattern for settings registry)

Frontend
- `resources/js/kernel/plugins/types.ts`, `resources/js/plugins/core/core.plugin.ts`, `resources/js/plugins/core/modules/chat/ChatModule.ts` (module template)
- `resources/js/kernel/routing/RoutingExtension.ts`, `resources/js/kernel/routing/middlewares/AuthMiddleware.ts` (guard template)
- `resources/js/kernel/api/RestApi.ts`, `resources/js/app/schemas/resources/connections.schema.ts`
- `resources/js/components/ui/*`, `resources/css/tokens/*`
- `resources/language/ui_de_DE.json`, `ui_en_US.json`
