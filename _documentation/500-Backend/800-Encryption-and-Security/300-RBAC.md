# RBAC and Administration

`App\Services\Admin\Permission` is the permission registry. `PermissionService` derives permissions from `roles`, `role_permissions` and `role_user`; disabled/removed users receive none. Permission checks query current assignments rather than trusting browser state. `connections/hawki` supplies permissions for navigation through `app.can()` and `useCan()`.

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

`EmployeeTypeRoleSyncer` replaces derived role assignments at login without touching manual assignments. The migration grants existing employee-type administrators a manual built-in admin role. `rbac:grant` provides bootstrap/recovery access and supports `--revoke`.

Administrative user scope bypass requires `users.view`. The global fallback for bypassing other data scopes requires the full permission set, so merely opening the panel or listing users cannot bypass unrelated resource scopes. Administrative configuration reads have their own authorized query path.

`SystemSettings` applies an explicit config-path allow-list before providers boot and before queued jobs. It retains deployment defaults for resets in long-lived workers. New entries require validation and a runtime-consumer review; never add infrastructure credentials as ordinary values.

## Verification

```bash
bin/env artisan test --compact tests/Feature/AdminPanelTest.php
bin/env npm run test:admin
bin/env npm run test:admin:types
bin/env npm run test:auth
bin/env npm run check
bin/env npm run build
```
