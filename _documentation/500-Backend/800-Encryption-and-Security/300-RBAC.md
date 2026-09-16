# RBAC and Administration

`App\Services\Admin\Permission` is the permission registry. Spatie Permission stores roles and their grants in `roles`, `permissions`, `role_has_permissions`, and `model_has_roles`. `PermissionService` reads those relationships and filters grants through the registry; disabled/removed users receive none. Direct user permissions are not enabled in HAWKI. Permission checks query current assignments rather than trusting browser state. `connections/hawki` supplies permissions for navigation through `app.can()` and `useCan()`.

Admin endpoints are registered in the v1 JSON:API resource block in `routes/api.php`, under `/api/hawki/v1/admin-{section}`. Each resource has its own schema in `App\JsonApi\V1\Admin`, controller in `App\Http\Controllers\Api\V1\Admin` and repository in `App\Services\Admin\Repositories`. Repositories own field definitions, validation, persistence, relation serialization and version calculation. `ResourceCatalog` lists resource permissions. JSON:API route binding authorizes panel access and the resource permission before loading an admin record. Controllers also authorize before reads and writes, including inside mutation transactions.

The paths below are relative to `/api/hawki/v1`. See [Admin section collections](../300-JSON-API.md#admin-section-collections) for request and response examples.

| Request | Operation |
| --- | --- |
| `GET /admin-providers` | Read JSON:API resources in `data` and editor/pagination metadata in `meta` |
| `POST /admin-providers` | Create from `data.type` and `data.attributes`, returning the resource with `201` |
| `PATCH /admin-providers/{id}` | Update from `data.type`, matching `data.id` and `data.attributes`, with a quoted version in `If-Match` |
| `DELETE /admin-providers/{id}` | Delete with a quoted version in `If-Match`, returning `204` |
| `POST /admin-providers/{id}/actions/discover` | Discover models for that provider |
| `GET /admin-users/{id}/actions/tokens` | List tokens for that user |

The same collection and item paths apply to models, system-models, mcp, announcements, roles and mappings. Users support create/update, tools support update, and settings support update/reset by key. Usage, health and environment have read endpoints; health also has explicit operational action routes. Unsupported operations have no registered route. Settings use their existing allow-list validation and do not require record versions.

Pagination uses `page[number]` and `page[size]`, sorting uses `sort`, and search uses `filter[search]`. `filter[where][column]=value` selects a value from a listed column. POST and PATCH require `Content-Type: application/vnd.api+json`. Top-level `section`, `id`, `values` and `version` cannot dispatch writes. The resource type and item identifier must match the route; mismatches return `409`.

`ResourceRepository` shares pagination and serialization mechanics. `ConfigurationRepository` shares value-object casting and persistence hooks. Each concrete repository implements its own resource rules. `RoleGuard` enforces grant limits and lockout protection for user, role and mapping changes, including the `rbac:grant` command.

Role mutations and administrative writes use a common database lock. Versions also include mutable role/tool assignment relations. Collection resources carry the token in `meta.version`; successful creates and updates also return it as a quoted ETag. Missing or stale `If-Match` tokens return `412` for versioned writes. Reads and saved-resource responses omit credentials and expose replacement indicators such as `api_key_set`. Audit changes redact secret fields and large/private text. `admin_audit_log` records actor, action, resource, time and request IP. The audit table is not currently exposed in the UI.

`RoleAssignmentService` keeps manual and employee-type assignments in `role_user` and projects their distinct user/role pairs into Spatie. Every application assignment writer uses this service under `RoleGuard`. Nested changes are checked together at the outer mutation boundary. `EmployeeTypeRoleSyncer` replaces derived role assignments at login without touching manual assignments or removing the last administrator. `rbac:grant` provides bootstrap/recovery access and supports `--revoke`.

Administrative user scope bypass requires `users.view`. The global fallback for bypassing other data scopes requires the full permission set, so merely opening the panel or listing users cannot bypass unrelated resource scopes. Administrative configuration reads have their own authorized query path.

`SystemSettings` applies an explicit config-path allow-list before providers boot and before queued jobs. It retains deployment defaults for resets in long-lived workers. New entries require validation and a runtime-consumer review; never add infrastructure credentials as ordinary values.

## Spatie storage and authorization

`App\Models\User` uses `HasRoles`. Users and roles explicitly use the `web` permission guard, including requests authenticated by Sanctum bearer tokens. `roles.name` stores the stable identifier previously stored in `slug`; `roles.display_name` stores the visible name. The admin API still exposes `slug`, `name`, `description`, `is_system`, and `permissions`. Filtering, sorting, role options, and edit versions use the same public field names.

`AuthServiceProvider` registers HAWKI's Gate callback in place of Spatie's default callback. It checks current account eligibility before granting a registered permission. Other abilities continue to resource policies, so administrator grants do not bypass conversation ownership. `User::hasPermissionTo()` applies the same role-only rules. Authorization queries current membership and grants instead of trusting loaded model relations or worker-local permission caches. `PermissionService` is a scoped binding that memoizes each resolution against the `User` instance it was given, so a request checking many abilities reads eligibility and grants once. The memo never outlives a request or queued job, `RoleGuard::mutate()` drops it after every grant change, and the few writers that change `isRemoved` outside the guard call `forget()`. Code that must observe another request's revocation while still running passes a freshly loaded `User`; `ToolAuthorization` reloads the actor before every dispatch for that reason. `assignedPermissionsOf()` deliberately includes disabled targets' grants for takeover-prevention checks.

Use `RoleAssignmentService` for membership changes and the guarded admin repositories for role edits. Calling Spatie assignment methods directly skips source reconciliation and HAWKI's administrative safeguards. Bootstrap and recovery use `bin/env php artisan rbac:grant username role-slug`, with `--revoke` to remove a manual assignment.

## Migration and rollback

A single migration, `2026_09_10_120000_create_administration_tables`, creates the Spatie tables (`roles` with HAWKI's `display_name`, `description` and `is_system` columns, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`), the assignment-source table `role_user`, `employee_type_role_mappings`, the admin tables, and the admin columns on `users` and the AI configuration tables. It seeds the built-in `admin` and `user` roles, registers every permission name frozen inside the migration, grants the administrator role every registered permission including the tool and AI-capability names, and gives every user with `employeetype = admin` a manual administrator assignment. Because MySQL commits DDL outside the transaction, `up()` and `down()` guard every schema step and use ignore-on-conflict inserts, so a run that failed midway can simply be repeated.

Run `bin/env php artisan migrate --force`, rebuild any deployment config cache, then restart workers. `down()` removes every table and column the migration added, including all role memberships; roll back application code and schema together while traffic and workers are paused.

## Verification

Use an isolated migrated test database, never the local application database. The examples below name the isolated database used for this implementation. The concurrency test uses two PHP processes and requires MySQL with no pre-existing role assignments.

```bash
# Open a shell in the app container first; bin/env does not forward host environment variables.
bin/env ssh
APP_ENV=testing DB_HOST=127.0.0.1 DB_DATABASE=hawki_spatie_test_20260915 CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync BACKUP_DISABLED=1 php vendor/bin/phpunit tests/Feature/SpatiePermissionsTest.php tests/Feature/SpatiePermissionMigrationTest.php tests/Feature/SpatieRoleConcurrencyTest.php
APP_ENV=testing DB_HOST=127.0.0.1 DB_DATABASE=hawki_spatie_test_20260915 CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync BACKUP_DISABLED=1 php vendor/bin/phpunit tests/Feature/AdminPanelTest.php tests/Feature/ToolAuthorizationTest.php
exit
bin/env npm run test:admin
bin/env npm run test:admin:types
bin/env npm run test:auth
bin/env npm run check
bin/env npm run build
```

## Tool access

Tool administration still requires `admin.access` and `mcp.manage`. Tool use requires separate grants, defined in `ToolAccessRules`:

| Approved access rule | Required role permissions |
| --- | --- |
| `unavailable` | Nobody may use the tool |
| `web_search` | `tools.use`, `ai.capabilities.web_search.use` |
| `web_fetch` | `tools.use`, `ai.capabilities.web_fetch.use` |
| `image_generation` | `tools.use`, `ai.capabilities.image_generation.use` |
| `internal_search` | `tools.use`, `tools.internal_search.use` |

Every newly discovered tool and every existing tool starts with `access_rule=unavailable`. The migration grants the built-in administrator every registered permission, including tool and capability permissions; tools remain unavailable until an administrator publishes an access rule.

For an existing installation, an operator explicitly bootstraps each grant the administrator should be able to delegate:

```bash
bin/env php artisan rbac:grant-tool-access admin web_search
# Optional, when these features are intended for this installation:
bin/env php artisan rbac:grant-tool-access admin web_fetch
bin/env php artisan rbac:grant-tool-access admin image_generation
bin/env php artisan rbac:grant-tool-access admin internal_search
```

The command accepts a stable role slug, runs under the common role mutation lock, and writes an audit entry. It does not change any tool's access rule. In Administration, grant the desired permissions to a user role, assign that role manually or through an employee-type mapping, then select the approved access rule for each intended tool. Keep its enabled state and model assignment configured separately. Native web search and native web fetch become available to a granted user only on a model that explicitly supports that native capability with tool calling and native capabilities enabled; they do not need an MCP tool access rule.

Changing a tool rule requires `admin.access`, `mcp.manage`, and `roles.manage`, plus the grants associated with both its old and new rule. An administrator cannot replace a more restricted rule with `unavailable` or a weaker rule unless they could delegate its existing grants. Ordinary configuration edits retain their existing permission requirements. Conditional writes and auditing apply to rule edits.

### Public contracts

`admin-roles.meta.permission_catalog` contains `{name, group, title_label, description_label, grantable}`. Groups are `administration` and `tools`. `admin-tools` adds `attributes.access_rule` and `meta.access_rules`, whose entries contain `{name, title_label, description_label, permissions, grantable}`. User and mapping collections provide `meta.role_catalog` with `{id, name, slug, is_system, title_label}` for display labels, where `title_label` is a translation key for built-in roles and `null` otherwise; `roles` remains manual membership and `mapped_roles` remains derived membership.

Public tool queries filter authorization before pagination, relationship linkage, and includes. Offline tools remain discoverable with their status. Capability resources include `native_model_ids: string[]`, using model resource IDs, not model slugs. The native list records authorization and configuration eligibility, and retains temporarily offline models so the frontend can display availability separately. A permitted MCP implementation does not confer permission to select its provider-native counterpart.

### Execution boundaries

`AgentRequestContext.actorId` captures the authenticated initiating user in trusted factory code. Tool strings, settings, and arguments cannot select that identity. Group work captures the already-created agent before the response; the HAWKI bot remains only the response author. The supported execution APIs are HAWKI `send()` and `sendStreaming()`, including the existing group shutdown callback. Inherited Laravel AI `prompt()`, `stream()`, `queue()`, and broadcast methods, and serialization/deserialization of complete HAWKI agents, are rejected with `TOOL_ACCESS_DENIED`. They cannot bypass the trusted context or reconstruct its process-local authorization state. Future queue support must store validated input plus the initiating actor ID, then reconstruct a fresh HAWKI context and tool selection in the worker before using a supported send method. Missing actors cannot use tools.

`ToolAuthorization` reloads the actor, grants, tool, model, provider, usage assignment, and MCP configuration. PHP tools have a separate path and need no MCP server. Every name, explicit capability, automatic, and native resolution path uses the service. Automatic selection skips unauthorized candidates. When no implementation is usable at all, meaning no candidate tool and no permitted native implementation, the selection is reported as `TOOL_UNAVAILABLE`; `TOOL_ACCESS_DENIED` is reported only when every path failed for a missing grant. The `AuthorizedTool` wrapper checks again immediately before local execution, including the original and mapped capability columns. MCP clients are reused only while endpoint, transport, credentials, options and timeouts remain unchanged; replacing configuration replaces the cached client. Provider drivers constructed with explicit configuration bypass the framework cache keyed by adapter name, keeping providers and changed credentials separate. `AuthorizedTextGateway` checks every provider request, including continuation steps and native configurations. A provider driver that cannot be wrapped in `AuthorizedTextGateway` is refused outright rather than used unguarded. Each native resolution registers its own tool instance, so a shared instance returned by the public filter event cannot carry another request's capability or actor. If the SDK catches a local denial as a tool error, `ToolExecutionState` stops further calls and prevents reporting the turn as successful. Tool argument validation and resource policies continue inside the implementation.

HTTP denial returns `403` with `{code: "TOOL_ACCESS_DENIED", message}`. Unavailable selections return `422` with `TOOL_UNAVAILABLE`. Tool authorization failures during a stream use `{type: "error", content, isDone: true, code}` and end the turn. Ordinary provider error chunks carry no tool code, are delivered in place, and do not terminate the stream. Selection resolution happens before a stream opens; later revocation terminates the stream with the same code. The frontend refreshes authorization once and does not replay the operation. Remote provider-native operations already running cannot be recalled.

Rollback must restore application code and schema together. Returning to an older backend removes tool enforcement; do not leave a frontend advertising tool restrictions while such a backend serves traffic.
