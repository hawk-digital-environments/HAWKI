# JSON:API Conventions

HAWKI exposes its primary REST API as a [JSON:API](https://jsonapi.org/format/) server built on [`laravel-json-api/laravel`](https://laraveljsonapi.io/). All endpoints are under `/api/hawki/v1`. Responses follow the standard compound-document format with `data`, `included`, `meta`, and `links` keys.

## Three API surfaces — do not conflate

HAWKI has accumulated three distinct API mechanisms:

| Mechanism                     | Base path          | Auth                                       | Purpose                                                                                           |
|-------------------------------|--------------------|--------------------------------------------|---------------------------------------------------------------------------------------------------|
| **JSON:API v1** (this page)   | `/api/hawki/v1`    | Sanctum session cookie or bearer token      | Main data API for the Svelte frontend and external apps                                           |
| **Legacy streaming endpoint** | `POST /api/ai-req` | Sanctum bearer token                       | Pre-v2 AI request endpoint; still present, subject to deprecation                                 |
| **Personal access tokens**    | any                | Sanctum bearer token (`app:token` command) | Grants a named user a long-lived token for programmatic access; separate from ext-app integration |

## Authorization — `authorizable(): false`

Most schemas return `false` from `authorizable()`. This disables the JSON:API library's built-in policy-based authorization gate. HAWKI authorizes at the middleware layer via `UserContext` and `UsageContext` before the request reaches the schema layer (see [Request Contexts](../200-Concepts/120-Request-Contexts.md)). A second authorization check in the schema would be redundant.

Row-level access uses Eloquent query scopes — `BelongsToUserScope` on `user-keychain-values`, `RoomAccessScope` on `rooms` — rather than schema-level policies.

## Relationships and includes

Schemas declare `HasMany` / `BelongsTo` relations that clients traverse via the standard `?include=` query parameter (compound documents). Examples:

- `ai-providers` → `hasMany('models')` → include as `?include=models`
- `ai-tools` → `hasOne('server')`, `hasMany('models')`
- `mcp-servers` → `hasMany('tools')`

## Pagination

`PagePagination` is the standard paginator. Default page sizes are set per resource in the schema class. Clients request a specific page size via `page[size]` / `page[number]` up to the resource's configured maximum.

## Filters

Most schemas have empty `filters()` methods — filter support is largely unimplemented. Do not assume a resource supports filtering unless the schema declares it. The two filters that exist today: `AiToolAssignedToModelFilter` and `AiToolStatusFilter` on `ai-tools`.

## Custom actions

Non-standard endpoints are registered as custom actions on a resource. Naming convention: `POST .../actions/{action-name}`. Examples:

- `POST /api/hawki/v1/user-keychain-values/actions/batch-update` — upsert/remove/clean key blobs
- `GET /api/hawki/v1/user-keychain-values/actions/validator` — returns the public key for passkey verification
- `POST /api/hawki/v1/migrations/actions/apply` — mark a pending frontend migration as applied

## Disabling contextual scopes per request — `no_scope`

The `no_scope[resource-type]=scopeKey` query parameter disables [contextual scopes](../200-Concepts/140-Contextual-Scopes.md) on a resource for a single request. Implemented by `ApiDataScopeContextSettingMiddleware`.

### Syntax

| Value | Effect |
|---|---|
| `no_scope[ai-models]=active_filter` | Disables one scope on the `ai-models` resource. |
| `no_scope[ai-models]=active_filter,usage_type_filter` | Disables a comma-separated list of scopes. |
| `no_scope[ai-models]=*` | Disables every contextual scope on that resource. |
| `no_scope[ai-models]=*&no_scope[rooms]=RoomAccessScope` | Combines across resources. |

### Errors

Unknown resource types or scope keys return `400` with a fuzzy "did you mean …?" suggestion based on Levenshtein distance. A resource type that is not bound to an Eloquent model, or a model that does not use `HasContextualScopesTrait`, also returns `400`.

### Auth

The `no_scope` parameter works with both auth mechanisms:

- The **stateful session cookie** used by the Svelte frontend.
- The **Sanctum bearer token** used by external apps and personal access tokens.

Whatever auth path you are on, the contextual scopes' `disablingGuard` closures still run — the parameter only opens the door; the guard decides whether you may walk through it.

### How the middleware resolves the JSON:API server

The middleware uses `ServiceLocator` to resolve the JSON:API `Server` binding. The `Server` is bound by a later middleware in the stack, so injecting it via constructor would fail when the stack is resolved. `ServiceLocator` defers resolution to request time. See [ServiceLocator](../200-Concepts/110-Dependency-Injection/100-ServiceLocator.md).

## `ServiceLocatorTrait` in API resources

API resource and schema classes cannot use constructor injection because `laravel-json-api/laravel` instantiates them outside the container's normal resolution chain. They use `ServiceLocatorTrait` instead. Permitted **only** in JSON:API resource and schema classes — see [ServiceLocator](../200-Concepts/110-Dependency-Injection/100-ServiceLocator.md) for the test pattern.

## Backwards compatibility

`ApiRequestMigrator` translates v2-era request formats from older frontend clients before they reach the resource layer. Transparent to new code; mention it only so developers encountering it understand its purpose.

## SyncLog meta slot

Every mutating JSON:API response automatically receives a `_hawki_sync_log` key in its `meta` object. The slot exists and is populated today, but the data it will carry — incremental sync deltas for WebSocket subscribers — is part of the larger SyncLog system, currently disabled. See [Roadmap — Plugin System](../700-Roadmap/100-Plugin-System.md).

## Resource inventory

The full list of registered schemas lives in `app/JsonApi/V1/Server.php`. Open that file to see what is exposed; the inventory does not belong in docs.

## Deprecated routes

Some older routes forward to the new JSON:API surface via a deprecation middleware:

```php
Route::post('/api/link-preview')
    ->middleware('deprecated:/api/hawki/v1/...');
```

If your client receives a deprecation header, update the URL to the `/api/hawki/v1/` equivalent.
