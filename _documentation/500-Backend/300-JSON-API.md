# JSON:API

HAWKI exposes its primary REST API as a [JSON:API](https://jsonapi.org/) server built on [`laravel-json-api/laravel`](https://laraveljsonapi.io/) (^5.2).

All endpoints are under:

```
/api/hawki/v1
```

Responses follow the standard JSON:API compound-document format with `data`, `included`, `meta`, and `links` keys. Filtering, sorting, and pagination behaviour varies per resource.

---

## Disambiguation: three separate API surfaces

HAWKI has accumulated three distinct API mechanisms. Conflating them causes confusion:

| Mechanism                     | Base path          | Auth                                       | Purpose                                                                                           |
|-------------------------------|--------------------|--------------------------------------------|---------------------------------------------------------------------------------------------------|
| **JSON:API v1** (this page)   | `/api/hawki/v1`    | Sanctum session cookie                     | Main data API for the Svelte frontend and external apps                                           |
| **Legacy streaming endpoint** | `POST /api/ai-req` | Sanctum bearer token                       | Pre-v2 AI request endpoint; still present, subject to deprecation                                 |
| **Personal access tokens**    | any                | Sanctum bearer token (`app:token` command) | Grants a named user a long-lived token for programmatic access; separate from ext-app integration |

:::warning[Old documentation]
The pre-v2 API documentation at `_documentation/3-architecture/11-HAWKI_API.md` conflates the legacy endpoint with personal access tokens. Do not treat that file as current for the JSON:API v1 surface.
:::

### Deprecated route example

Some older routes forward to the new JSON:API surface via a deprecation middleware:

```php
Route::post('/api/link-preview')
    ->middleware('deprecated:/api/hawki/v1/...');
```

If your client receives a deprecation header, update the URL to the `/api/hawki/v1/` equivalent.

---

## Resource Schema Inventory

The JSON:API server registers the following 20 schemas (verified from `app/JsonApi/V1/Server.php`):

| Resource type           | Model / Source          | Notes                                                                                     |
|-------------------------|-------------------------|-------------------------------------------------------------------------------------------|
| `ai-model-descriptions` | `AiModelDescription`    | Locale-aware model descriptions                                                           |
| `ai-model-flags`        | `AiModelFlag`           | Flag metadata with UI labels                                                              |
| `ai-models`             | `AiModel`               | AI model records                                                                          |
| `ai-providers`          | `AiProvider`            | Configured AI providers                                                                   |
| `ai-tool-capabilities`  | `AiToolCapability`      | Tool capability declarations                                                              |
| `ai-tools`              | `AiTool`                | Registered function tools                                                                 |
| `attachments`           | `Attachment`            | File attachment records                                                                   |
| `configs`               | Virtual                 | `PublicConfigRegistry`-driven; all public config blocks                                   |
| `connections`           | Virtual                 | Connection bootstrap resource (see [Connection Bootstrap](./400-Connection-Bootstrap.md)) |
| `ext-apps`              | `ExtApp`                | External application registrations                                                        |
| `mcp-servers`           | `McpServer`             | MCP server registrations                                                                  |
| `migrations`            | `FrontendMigration`     | Pending frontend migrations                                                               |
| `room-members`          | `Member` + `Invitation` | Combined membership/invitation resource                                                   |
| `room-messages`         | `Message`               | Group room messages                                                                       |
| `rooms`                 | `Room`                  | Group rooms                                                                               |
| `system-models`         | Virtual/Eloquent        | Maps `model_type` + `usage_type` to an `AiModel` via usage type overlay                   |
| `system-prompts`        | `SystemPrompt`          | Locale-aware system prompt definitions                                                    |
| `translation-labels`    | Virtual                 | Non-Eloquent; returns all labels for `{locale}` from `CustomTranslator`                   |
| `user-keychain-values`  | `UserKeychainValue`     | Encrypted key blobs per user                                                              |
| `users`                 | `User`                  | User records                                                                              |

:::note
`ai-convs` (private AI conversations) and `announcements` are currently served through the legacy web routes (`routes/web.php`), not through the JSON:API v1 server. They are not in the table above.
:::

---

## Virtual and Non-Eloquent Resources

### Admin section collections

The admin panel uses `/api/hawki/v1/admin-{section}` collections. `routes/api.php` registers them through the same `JsonApiRoute::server('v1')` resource and action declarations as the public API. Their schemas live in `App\JsonApi\V1\Admin` and use separate admin records so public model serialization keeps its existing schemas. They include disabled records, redacted credential indicators and editor fields. Every request requires `admin.access` and the section's permission. See [RBAC and administration](./800-Encryption-and-Security/300-RBAC.md) for permission and audit rules.

| Sections | Resource operations |
| --- | --- |
| `providers`, `models`, `system-models`, `mcp`, `roles`, `mappings`, `announcements` | List, create, update, delete |
| `users` | List, create, update |
| `tools` | List, update |
| `settings` | List, update, reset by deleting an override |
| `usage`, `health`, `environment` | List |

There are no individual GET routes. Writes return the saved resource; subsequent reads use the collection.

#### Collection responses and queries

`AdminCollection` and `AdminResource` build the JSON:API documents. Records have string identifiers and values in `attributes`. Each versioned record carries its conflict token in `data[].meta.version`. Collection `meta.fields` describes the editor fields; `meta.create` and `meta.delete` indicate available operations. Usage and health add report metadata.

Domain fields named `type`, such as announcement categories and MCP transports, use `attributes.kind`. The frontend converts `kind` back to `type` for editing and sends it as `kind` when saving. Resource identity remains in `data.type`.

Database collections accept these query parameters:

| Parameter | Meaning |
| --- | --- |
| `page[number]` | Page number starting at 1, default 1 |
| `page[size]` | Rows per page, 1–100, default 25 |
| `sort` | One stored column, with `-` for descending order; default `id` |
| `filter[search]` | Search supported text columns |
| `filter[where][column]` | Match a stored column value |

For example, `GET /api/hawki/v1/admin-models?page[number]=2&page[size]=25&sort=label&filter[where][provider_id]=7` reads the second page of one provider's models.

Pagination metadata uses `meta.page.currentPage`, `perPage`, `lastPage` and `total`. Links preserve the filters and include `self`, `first`, `last`, `prev` and `next`. Settings, environment, usage and health return their complete result without pagination metadata. Usage accepts `filter[from]` and `filter[to]` as `YYYY-MM-DD`, `filter[group_by]` as `day`, `month`, `model`, `provider`, `type` or `user`, and optional `filter[model]` and `filter[user]`. User-level reports require `usage.view-per-user`.

#### Creates, updates and deletes

POST and PATCH require `Content-Type: application/vnd.api+json`. Send a JSON:API resource document with a matching `data.type`; PATCH also requires a string `data.id` matching the URL. For example, creating a role uses `POST /api/hawki/v1/admin-roles` with:

```json
{
  "data": {
    "type": "admin-roles",
    "attributes": {
      "slug": "model-reviewers",
      "name": "Model reviewers",
      "permissions": ["admin.access", "models.manage"]
    }
  }
}
```

Creates return `201`; updates return `200`. Both return the saved resource under `data` using the collection's redaction rules. Secret values do not round-trip. Omit a secret or leave its replacement blank to preserve it.

To update or delete a versioned record, copy its `meta.version` token into `If-Match`, including double quotes. A PATCH to `/api/hawki/v1/admin-roles/17` therefore sends `If-Match: "<version>"` and includes `"id": "17"` inside `data`. Successful creates and updates return the current quoted token as an ETag. DELETE needs no body and returns `204`. Settings retain unversioned updates and resets.

| Status | Meaning |
| --- | --- |
| `409` | Resource type or identifier does not match the route |
| `412` | Missing, malformed or stale version token on a versioned write |
| `415` | POST/PATCH does not use the required content type |
| `422` | Invalid document, attributes or query values |

#### Custom actions and frontend integration

Actions have explicit routes such as `POST /admin-providers/{id}/actions/discover` and `GET /admin-users/{id}/actions/tokens`, relative to `/api/hawki/v1`. They use action-specific bodies and plain JSON responses, such as `{ "models": [...] }` or `{ "tokens": [...] }`. They do not use the resource write document or `If-Match` contract. Additional permissions apply to operations such as provider import and failed-job management.

The frontend registers `AdminRowSchema` for each collection and uses `restApi.getResourceCollection()`, `createResource()`, `updateResource()` and `deleteResource()`. Action helpers validate responses through the schemas in `admin/schemas/admin-actions.ts`. See [Building admin pages](../600-Frontend/600-Advanced/150-Admin-pages.md) for workspace callbacks, table composition and typed result dialogs.

### `configs`

The `configs` resource is not a hardcoded payload. It is assembled by `PublicConfigRegistry`, a singleton that tracks which `AbstractConfig` subclasses should appear in the response. Each registered config class declares a `publicKey()` string and a `toPublicArray(Request)` method.

Built-in config blocks (all under the `hawki-core` namespace):

| Public key      | Class               | Content                                                         |
|-----------------|---------------------|-----------------------------------------------------------------|
| `locale`        | `LocaleConfig`      | Default locale, list of available locales                       |
| `salts`         | `SaltConfig`        | Five named crypto salts (only delivered to authenticated users) |
| `security`      | `SecurityConfig`    | Passkey UX settings                                             |
| `transfer`      | `TransferConfig`    | App base URL and WebSocket connection details                   |
| `ai`            | `AiConfig`          | AI handle string, AI user display name and avatar               |
| `storage_files` | `FileStorageConfig` | Allowed MIME types, max file size                               |

Extension point: `$app->extend(PublicConfigRegistry::class, ...)` in `ServiceProvider::boot()` adds custom config blocks without touching core code.

### `translation-labels`

Backed by `CustomTranslator`, not an Eloquent model. The resource takes a `{locale}` path segment and returns all translation labels for that locale as a flat JSON:API document. The frontend fetches this resource separately from the connection bootstrap — the connection payload carries only the current locale identifier, not the labels themselves.

### `system-models`

Maps usage type overlays to model records via `UsageTypeOverlayScope`. Use this resource to resolve which `AiModel` is active for a given `model_type` + `usage_type` combination (e.g. which model answers private conversations).

---

## HAWKI JSON:API Conventions

### Authorization: `authorizable(): false`

Most schemas return `false` from `authorizable()`. This disables the JSON:API library's built-in policy-based authorization gate. The reason: HAWKI handles authorization at the middleware layer (via `UserContext` and `UsageContext`) before the request reaches the schema layer. Running a second authorization check in the schema would be redundant and would require every resource to declare policies even when access is controlled by broader middleware.

Resources that DO enforce row-level access use Eloquent query scopes (for example, `BelongsToUserScope` on `user-keychain-values`, `RoomAccessScope` on `rooms`) rather than schema-level policies.

### Relationships and Includes

Schemas declare `HasMany` / `BelongsTo` relations that clients traverse via the standard `?include=` query parameter (compound documents). Examples:

- `ai-providers` → `hasMany('models')` → include as `?include=models`
- `ai-tools` → `hasOne('server')`, `hasMany('models')`
- `mcp-servers` → `hasMany('tools')`

### Pagination

`PagePagination` is the standard paginator. Default page sizes are set per resource in the schema class. Clients may request a specific page size via the standard `page[size]` / `page[number]` query parameters up to the resource's configured maximum.

### Filters

Most schemas have empty `filters()` methods — filter support is largely unimplemented. Do not assume a resource supports filtering unless it is listed here. The two filters that exist today:

- `AiToolAssignedToModelFilter` — filter `ai-tools` to those assigned to a specific model
- `AiToolStatusFilter` — filter `ai-tools` by active/inactive status

### Custom Actions

Non-standard endpoints are registered as custom actions on a resource. Naming convention: `POST .../actions/{action-name}`. Examples from the current codebase:

- `POST /api/hawki/v1/user-keychain-values/actions/batch-update` — upsert/remove/clean key blobs; optionally updates the public key
- `GET /api/hawki/v1/user-keychain-values/actions/validator` — returns the public key for passkey verification
- `POST /api/hawki/v1/migrations/actions/apply` — mark a pending frontend migration as applied

### ServiceLocatorTrait in API Resources

API resource classes cannot use constructor injection because `laravel-json-api/laravel` instantiates them outside the container's normal resolution chain. Instead, they use `ServiceLocatorTrait` to resolve dependencies on demand.

`ServiceLocatorTrait` is permitted **only** in JSON:API resource and schema classes. If you find yourself reaching for it in a service or model, you are solving the wrong problem — use constructor injection instead. See [Custom Infrastructure Patterns](./100-Architecture/250-Custom-Infrastructure.md) for the full explanation.

In tests, inject controlled values with `$schema->setService(SomeClass::class, $mock)` before the assertion.

---

## SyncLog Meta Slot

Every mutating JSON:API response automatically receives a `_hawki_sync_log` key in its `meta` object. The slot exists and is populated today, but the data it will carry — incremental sync deltas for WebSocket subscribers — is part of a larger SyncLog system that is currently disabled. See [Plugin System Preview](./1000-Infrastructure/100-Plugin-System-Preview.md) for the design.

---

## Backwards Compatibility

`ApiRequestMigrator` translates v2-era request formats from older frontend clients before they reach the resource layer. It is transparent to new code; mention it here only so developers encountering it in the codebase understand its purpose.

:::info[Backwards Compatibility]
If you see `ApiRequestMigrator` in a controller or middleware stack, it exists to translate legacy request shapes from older frontend versions. It should not affect any new feature work.
:::
