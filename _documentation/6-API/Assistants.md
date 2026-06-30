# Assistants API

## Overview

Assistants are user-created AI assistants. Each assistant is owned by its `creator`, belongs to an `organization`, and progresses through release stages (`draft` → `private` → `organizational` → `federated`). External services like setting values and tool configurations are exposed as standalone JSON:API resources linked back to the parent assistant. Visibility of the assistant and of each individual relationship is **tiered** (creator / org admin / shared user / public viewer) — see [Authorization Reference](#authorization-reference).

All endpoints return JSON:API documents. For machine-readable request/response examples including schemas, request bodies, and filter parameters, refer to the OpenAPI specification at `public/docs/openapi.json`.

## Authentication

Every assistant endpoint requires a valid Sanctum API token:

```
Authorization: Bearer <token>
Accept: application/vnd.api+json
Content-Type: application/vnd.api+json
```

Unauthenticated requests receive `401 Unauthorized`. Unauthorized operations receive `403 Forbidden` — this includes read attempts on relationships or child resources the caller's tier does not cover.

## Resource Endpoints

Standard CRUD for the `assistants` resource:

- **List Assistants**: `GET /api/assistants`
  - Supports filtering by `filter[category][text]`, `filter[name]`, `filter[release_stage]`, `filter[handle]`, and `filter[is_favorite]`.
  - Supports pagination (`page[number]`, `page[size]`), sparse fieldsets (`fields[assistants]`), and inclusion (`include=creator,category,assistant_user_prompts,…`). Sensitive includes (`assistant_setting_values`, `assistant_feedback`, `assistant_review`, `ai_tools`) narrow the collection to assistants the caller is authorized to read them for — see [Authorization Reference](#authorization-reference).
  - Returns: paginated array of assistant resource objects.

- **Create Assistant**: `POST /api/assistants`
  - Body: `{ "data": { "type": "assistants", "attributes": { "name": "…", … } } }`
  - `release_stage` defaults to `draft`. The `creator` is set to the authenticated user.
  - Returns: `201` with the created assistant resource.

- **Get Assistant**: `GET /api/assistants/{id}`
  - Supports `?include=creator,category,assistant_avatar,assistant_user_prompts,assistant_setting_values,assistant_tags,assistant_feedback,assistant_review,ai_tools,organization,versions,…`. Sensitive includes are tier-gated per-assistant (see [Authorization Reference](#authorization-reference)); unauthorized includes yield `403`.
  - Returns: single assistant resource object, or `404` if not found / not visible.

- **Update Assistant**: `PATCH /api/assistants/{id}`
  - Owner-only. Supports all writable attributes including `release_stage` (changing to `organizational`/`federated` creates a pending review).
  - Body: `{ "data": { "type": "assistants", "id": "1", "attributes": { … }, "relationships": { "ai_tools": …, "shared_users": … } } }`
  - Returns: `200` with the updated resource.

- **Delete Assistant**: `DELETE /api/assistants/{id}`
  - Owner-only.
  - Returns: `204 No Content`.

## Relationships

Relationship endpoints manage **linkages** — they attach, detach, or replace associations to independently-existing resources by their `type` and `id`. The related resources themselves are never created or destroyed via these operations.

### Shared Users (owner-only)

Share an assistant with other users. Shared users can view `private` and `draft` assistants.

- **List**: `GET /api/assistants/{id}/relationships/shared-users`
- **Attach**: `POST /api/assistants/{id}/relationships/shared-users`
  - Body: `{ "data": [ { "type": "users", "id": "1" }, … ] }` — appends to the relationship.
- **Detach**: `DELETE /api/assistants/{id}/relationships/shared-users`
  - Body: `{ "data": [ { "type": "users", "id": "1" }, … ] }` — removes from the relationship.
- **Replace**: `PATCH /api/assistants/{id}/relationships/shared-users`
  - Body: `{ "data": [ … ] }` — replaces the entire relationship set.

### AI Tools (read: creator/shared/admin · edit: creator/admin)

Manage the global `ai-tools` assigned to an assistant. Tools are globally defined via `mcp-servers`; these endpoints control which tools the assistant can use.

- **List**: `GET /api/assistants/{id}/relationships/ai-tools` — readable by the creator, any shared user, or an org admin (collaborator tier).
- **Attach**: `POST /api/assistants/{id}/relationships/ai-tools` — creator or org admin (privileged tier).
  - Body: `{ "data": [ { "type": "ai-tools", "id": "1" }, … ] }`
- **Detach**: `DELETE /api/assistants/{id}/relationships/ai-tools` — creator or org admin.
- **Replace**: `PATCH /api/assistants/{id}/relationships/ai-tools` — creator or org admin.

Tools can also be attached inline via the **Update Assistant** endpoint by passing `relationships.ai_tools.data` in the PATCH body (replace semantics, owner-only).

### Read-Only Relationships

These relationships are available for reading via the related and relationship endpoints, but cannot be modified through them directly:

| Relationship | Related (`GET /api/assistants/{id}/…`) | Relationship (`GET …/relationships/…`) | Writable via |
|---|---|---|---|
| `category` | `/api/assistants/{id}/category` | `/relationships/category` | Inline PATCH |
| `creator` | `/api/assistants/{id}/creator` | `/relationships/creator` | — |
| `remix_creator` | `/api/assistants/{id}/remix-creator` | `/relationships/remix-creator` | — |
| `remixed_assistant` | `/api/assistants/{id}/remixed-assistant` | `/relationships/remixed-assistant` | — |
| `versions` | `/api/assistants/{id}/versions` | `/relationships/versions` | — (server-managed) |
| `organization` | `/api/assistants/{id}/organization` | `/relationships/organization` | — |

## Child Resources (standalone)

These resources are **owned children** — they only exist in the context of a parent assistant and are deleted when the assistant is deleted. They are exposed as standalone JSON:API resources and are also readable through the parent's relationship / `?include=` paths. Read access is tiered — see [Authorization Reference](#authorization-reference).

### Assistant Avatars — `/api/assistant-avatars`

A 1:1 CSS icon (`name` + `icon_css`) for an assistant. Each assistant has at most one avatar.

- **List** (scoped to avatars of assistants you can view): `GET /api/assistant-avatars` — Public tier.
- **Show**: `GET /api/assistant-avatars/{id}` — anyone who can view the owning assistant.
- **Create**: `POST /api/assistant-avatars` — Owner-only (one avatar per assistant; a second returns `422`).
  - Body: `{ "data": { "type": "assistant-avatars", "attributes": { "name": "🧠", "icon_css": "background: linear-gradient(...);" }, "relationships": { "assistant": { "data": { "type": "assistants", "id": "1" } } } } }`
- **Update / Delete**: `PATCH` / `DELETE /api/assistant-avatars/{id}` — Owner-only.
- Readable inline via `GET /api/assistants/{id}?include=assistant_avatar` and `GET /api/assistants/{id}/assistant-avatar` — Public tier.

### Setting Values — `/api/assistant-setting-values`

Configuration values keyed by setting definition IDs (`setting_id`). Managed per-value (no bulk upsert).

- **List** (scoped to assistants you may manage): `GET /api/assistant-setting-values` — Privileged tier.
- **Show**: `GET /api/assistant-setting-values/{id}` — Privileged tier.
- **Create**: `POST /api/assistant-setting-values` — Owner-only.
  - Body: `{ "data": { "type": "assistant-setting-values", "attributes": { "value": "professional" }, "relationships": { "assistant": { "data": { "type": "assistants", "id": "1" } }, "setting": { "data": { "type": "assistant-settings", "id": "1" } } } } }`
  - Returns: `201` with the created resource.
- **Update**: `PATCH /api/assistant-setting-values/{id}` — Owner-only.
- **Delete**: `DELETE /api/assistant-setting-values/{id}` — Owner-only. Returns `204`.
- Also readable via `GET /api/assistants/{id}/assistant-setting-values` and `?include=assistant_setting_values` — Privileged tier.

### User Prompts — `/api/assistant-user-prompts`

Inline text prompts that guide the assistant's behavior. No list endpoint; create and delete only.

- **Create**: `POST /api/assistant-user-prompts` — Owner-only.
  - Body: `{ "data": { "type": "assistant-user-prompts", "attributes": { "text": "First prompt" }, "relationships": { "assistant": { "data": { "type": "assistants", "id": "1" } } } } }`
  - Returns: `201` with the created resource (including its `id`).
- **Delete**: `DELETE /api/assistant-user-prompts/{id}` — Owner-only. Returns `204`.
- Readable via `GET /api/assistants/{id}/assistant-user-prompts` and `?include=assistant_user_prompts` — Public tier.

### Tags — `/api/assistant-tags`

A global, shared library of unique label records (`text` is unique). Many-to-many with assistants via the `assistant_tag` pivot — no duplication; the same tag can be attached to any number of assistants.

- **List** (any authenticated user): `GET /api/assistant-tags` — every tag record.
- **Show**: `GET /api/assistant-tags/{id}` — any authenticated user.
- **Create** (any authenticated user): `POST /api/assistant-tags` — adds a new label to the global pool.
  - Body: `{ "data": { "type": "assistant-tags", "attributes": { "text": "php" } } }`
  - Returns: `201` with the created resource. Rejects duplicate `text` (`422`).
- **Attach / detach / replace** on an assistant (creator or org admin): `POST` / `DELETE` / `PATCH /api/assistants/{id}/relationships/assistant-tags` — privileged tier (same as `ai_tools`).
- Readable via `GET /api/assistants/{id}/assistant-tags` and `?include=assistant_tags` — Public tier.

### Feedback — `/api/assistant-feedback`

Textual feedback on an assistant. The author (`user_id`) is automatically set to the authenticated user. Create-only; no list endpoint.

- **Create**: `POST /api/assistant-feedback` — any viewer who can `view` the assistant.
  - Body: `{ "data": { "type": "assistant-feedback", "attributes": { "text": "Great assistant!" }, "relationships": { "assistant": { "data": { "type": "assistants", "id": "1" } } } } }`
  - Returns: `201` with the created resource.
- Readable via `GET /api/assistants/{id}/assistant-feedback` and `?include=assistant_feedback` — Privileged tier.

### Reviews — `/api/assistant-reviews`

A 1:1 review record for each assistant, created automatically on release to `organizational`/`federated`.

- **Index** (admin queue): `GET /api/assistant-reviews` — org admin.
- **Update**: `PATCH /api/assistant-reviews/{id}` — org admin.
  - Body: `{ "data": { "type": "assistant-reviews", "id": "1", "attributes": { "status": "approved" } } }`
  - Denying (`{ "status": "denied", "reason": "…" }`) resets the assistant's `release_stage` to `private`.
  - Returns: `200` with the updated review resource.
- Readable via `GET /api/assistants/{id}/assistant-review` and `?include=assistant_review` — Owner or org admin.

## Self-Service Endpoints

### Favorite (any viewer, self)

Bookmark an assistant for the authenticated user. No request body — the caller is always the subject.

- **Add**: `POST /api/assistants/{id}/favorite`
  - Idempotent (re-favoriting has no effect).
  - Returns: `200` with the assistant resource (`is_favorite` = `true`).
- **Remove**: `DELETE /api/assistants/{id}/favorite`
  - Idempotent (unfavoriting an unfavorited assistant has no effect).
  - Returns: `200` with the assistant resource (`is_favorite` = `false`).

## Custom Actions

Endpoints that perform specialized operations beyond standard CRUD:

- **Remix**: `POST /api/assistants/{id}/actions/remix`
  - Creates a deep clone of the assistant (name, prompts, settings, tools, versions, attachments) owned by the caller. The clone starts in `private` release stage.
  - Requires `allow_remix = true` on the source assistant.
  - Returns: `201` with the newly created assistant resource.

- **Chat Test**: `POST /api/assistants/{id}/actions/chat-test`
  - Live testing of the assistant's chat behaviour via a streaming connection.
  - Body: `{ "input": [ { "role": "user", "content": "Hello" } ], "model": "gpt-4" }`
  - Returns: `200` with a `text/event-stream` SSE stream. See the OpenAPI spec for the full list of SSE event types and examples.

## Schema

The client schema endpoint provides metadata for form generation and client-side validation:

- **Client Schema**: `GET /api/assistants/schema`
  - Returns: field types, validation constraints, writable paths, relationship cardinalities, and discovered actions. Includes `writable_on` arrays for every writable attribute and relationship, listing all available `{method, path}` pairs.

## Workflows

### Creating and Publishing an Assistant

1. **Create** an assistant with `POST /api/assistants` — starts in `draft` or `private`.
2. **Configure** settings per value via `POST /api/assistant-setting-values`.
3. **Add prompts** via `POST /api/assistant-user-prompts`.
4. **Attach tools** via `POST /api/assistants/{id}/relationships/ai-tools` (creator or org admin).
5. **Tag** by creating a label (`POST /api/assistant-tags`) and attaching it via `POST /api/assistants/{id}/relationships/assistant-tags` (creator or org admin). Existing tags are reused, not duplicated.
6. **Share** via `POST /api/assistants/{id}/relationships/shared-users` (owner-only).
7. **Release** by updating `release_stage` to `organizational` or `federated`: `PATCH /api/assistants/{id}` with `{ "attributes": { "release_stage": "organizational" } }`. This triggers a pending review for org-admin approval.

### Sharing an Assistant

- To **share**: `POST /api/assistants/{id}/relationships/shared-users` with a list of user identifiers (owner-only).
- To **unshare**: `DELETE … /relationships/shared-users` with the user identifiers to remove (owner-only).
- To **list** current shares: `GET … /relationships/shared-users` (owner-only).
- Shared users can view `private` and `draft` assistants.

### Reviewing an Assistant

1. An org-admin lists pending reviews via `GET /api/assistant-reviews` (admin queue).
2. Open the relevant assistant's review: `GET /api/assistants/{id}/assistant-review` (creator or org admin).
3. Approve or deny: `PATCH /api/assistant-reviews/{id}` with `{ "data": { "type": "assistant-reviews", "id": "1", "attributes": { "status": "approved" } } }` (org admin only).

## Authorization Reference

Access is built from a small set of actor definitions and three composite tiers.

### Actors

- **Owner** — the assistant's `creator`.
- **Org admin** — a user with `role = admin` in the assistant's `organization` (`organization_user` pivot). *If the assistant has no `organization`, this tier cannot apply — only the Owner qualifies for privileged/collaborator checks.*
- **Shared user** — a user in the assistant's `sharedUsers`.
- **Org member** — a user with `role = member` in the assistant's organization (not admin, not shared).
- **Public viewer** — any authenticated user who can see the assistant only because its `release_stage` is `organizational` or `federated`.

### Composite tiers

| Tier | Who is included | Backing code |
|---|---|---|
| **Public (P)** | Owner ∪ shared user ∪ public viewer (everyone who passes `view`) | `AssistantRepository::isVisibleTo` |
| **Collaborator (C)** | Owner ∪ shared user ∪ org admin (public viewers excluded) | `AssistantRepository::canCollaborate` |
| **Privileged (M)** | Owner ∪ org admin of this assistant's organization | `AssistantRepository::isPrivileged` |
| **Owner-only** | Owner (creator) only | `AssistantPolicy::update` |

**Visibility prerequisite (`view`):** `organizational`/`federated` → any authenticated user; `draft`/`private` → owner or shared user only. Every operation below first requires `view`.

### Assistant resource operations

| Operation | Endpoint | Required tier |
|---|---|---|
| List visible | `GET /api/assistants` | Any authenticated (scoped to visible) |
| Show | `GET /api/assistants/{id}` | Public (P) |
| Create | `POST /api/assistants` | Any authenticated |
| Update | `PATCH /api/assistants/{id}` | Owner-only |
| Delete | `DELETE /api/assistants/{id}` | Owner-only |
| Remix | `POST /api/assistants/{id}/actions/remix` | Any authenticated + `allow_remix = true` |
| Favorite add/remove | `POST|DELETE /api/assistants/{id}/actions/favorite` | Public (P), self |
| Chat test | `POST /api/assistants/{id}/actions/chat-test` | Public (P) |

### Relationship linkage (`/relationships/...`)

| Relationship | Read | Attach / Detach / Replace |
|---|---|---|
| `shared-users` | Owner-only | Owner-only |
| `ai-tools` | Collaborator (C) | Privileged (M) |

### Relationship reads (related URL + `?include=`)

| Relationship | Reachable via | Read tier |
|---|---|---|
| `category`, `creator`, `remix_creator`, `remixed_assistant`, `versions`, `organization` | related URL + include | Public (P) |
| `assistant_avatar` | related URL + include | Public (P) |
| `assistant_user_prompts` | related URL + include | Public (P) |
| `assistant_tags` | related URL + include | Public (P) |
| `ai_tools` | related URL + include | Collaborator (C) |
| `assistant_setting_values` | related URL + include | Privileged (M) |
| `assistant_review` | related URL + include | Privileged (M) |
| `assistant_feedback` | related URL + include | Privileged (M) |
| `shared_users` | related URL + relationship URL | Owner-only |

> A sensitive include on `GET /api/assistants/{id}?include=…` is authorized per-assistant (same tier). On the **list** endpoint `GET /api/assistants?include=…`, requesting a sensitive relationship **narrows the collection** to assistants the caller is privileged (or, for `ai_tools`, collaborator) for, so the data is not leaked for assistants only visible at the public tier.

### Standalone child resources

| Resource | Read (index / show) | Write |
|---|---|---|
| `assistant-avatars` | Public (P) — scoped to assistants you can view | create / update / delete: Owner-only (1:1) |
| `assistant-setting-values` | Privileged (M) | create / update / delete: Owner-only |
| `assistant-user-prompts` | *(no read endpoint)* | create / delete: Owner-only |
| `assistant-tags` | Public (P) | create: any authenticated user (unique `text`); attach / detach on assistant: Privileged (M) |
| `assistant-feedback` | *(no read endpoint)* | create: Public (P) — any viewer may submit |
| `assistant-reviews` | index: org admin · show: Owner ∪ org admin | update: org admin |
