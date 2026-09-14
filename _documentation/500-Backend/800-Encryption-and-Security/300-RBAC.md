# RBAC and Administration

`App\Services\Admin\Permission` is the permission registry. `PermissionService` derives permissions from `roles`, `role_permissions` and `role_user`; disabled/removed users receive none. Permission checks query current assignments rather than trusting browser state. `connections/hawki` supplies permissions for navigation through `app.can()` and `useCan()`.

Admin endpoints are registered explicitly in `routes/admin.php`, under `/api/hawki/v1/admin`. Each resource has its own controller and repository in `App\Http\Controllers\Api\V1\Admin` and `App\Services\Admin\Repositories`. Repositories own field definitions, validation, persistence, relation serialization and version calculation. `ResourceCatalog` lists resource permissions. Controllers authorize panel access and the resource permission before reads and writes.

| Request | Operation |
| --- | --- |
| `GET /admin/providers` | Read paginated provider rows and editor metadata in `content` |
| `POST /admin/providers` | Create a provider with `{ "values": { ... } }`, returning `201` and its `id` |
| `PATCH /admin/providers/{id}` | Update with `{ "version": "...", "values": { ... } }` |
| `DELETE /admin/providers/{id}` | Delete with `{ "version": "..." }`, returning `204` |
| `POST /admin/providers/{id}/actions/discover` | Discover models for that provider |
| `GET /admin/users/{id}/actions/tokens` | List tokens for that user |

The same collection and item paths apply to models, system-models, mcp, announcements, roles and mappings. Users support create/update, tools support update, and settings support update/reset by key. Usage, health and environment have read endpoints; health also has explicit operational action routes. Unsupported operations have no registered route. Settings use their existing allow-list validation and do not require record versions.

Query controls remain under `filter`, for example `filter[page]=2&filter[size]=25&filter[search]=staff`; `filter[where][column]=value` selects a value from a listed column. Requests cannot select a resource or record through `section` or `id` body fields. The former `admin-sections` resource and its save/remove/run dispatch endpoints have been removed.

`ResourceRepository` shares pagination and serialization mechanics. `ConfigurationRepository` shares value-object casting and persistence hooks. Each concrete repository implements its own resource rules. `RoleGuard` enforces grant limits and lockout protection for user, role and mapping changes, including the `rbac:grant` command.

Role mutations and administrative writes use a common database lock. Versions also include mutable role/tool assignment relations. Credential attributes are omitted from reads; audit changes redact secret fields and large/private text. `admin_audit_log` records actor, action, resource, time and request IP. The audit table is not currently exposed in the UI.

`EmployeeTypeRoleSyncer` replaces derived role assignments at login without touching manual assignments. The migration grants existing employee-type administrators a manual built-in admin role. `rbac:grant` provides bootstrap/recovery access and supports `--revoke`.

Administrative user scope bypass requires `users.view`. The global fallback for bypassing other data scopes requires the full permission set, so merely opening the panel or listing users cannot bypass unrelated resource scopes. Administrative configuration reads have their own authorized query path.

`SystemSettings` applies an explicit config-path allow-list before providers boot and before queued jobs. It retains deployment defaults for resets in long-lived workers. New entries require validation and a runtime-consumer review; never add infrastructure credentials as ordinary values.

## Verification

```bash
bin/env artisan test --compact tests/Feature/AdminPanelTest.php
bin/env npm run test:admin
bin/env npm run test:auth
bin/env npm run check
bin/env npm run build
```
