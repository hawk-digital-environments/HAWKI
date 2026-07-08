# API Design & Usage Guidelines

## Overview

This document defines when to use each of three API patterns for the HAWKI API:

    1. Relationship endpoints
    2. Owned children (standalone resource + create-with-relationship)
    3. Custom actions.

Use the following rules when designing new endpoints or evaluating existing ones.

---

## Naming convention for assistant-scoped resources

Every resource that lives under an assistant carries the `assistant-` prefix on **all three** surfaces, so a resource is unambiguous globally:

- the resource **type**: `assistant-user-prompts`, `assistant-feedback`, `assistant-tags`, `assistant-setting-values`, `assistant-reviews`
- the **route** segment(s): `POST /assistant-user-prompts`, `GET /assistants/{id}/assistant-user-prompts`
- the **relationship name** on the parent and the `?include=` path: `assistant_user_prompts`

| Child | Type | Standalone write endpoint | Parent relationship |
|---|---|---|---|
| UserPrompt | `assistant-user-prompts` | `POST/DELETE /assistant-user-prompts(/{id})` | `assistant_user_prompts` |
| Feedback | `assistant-feedback` | `POST /assistant-feedback` | `assistant_feedback` |
| Tag | `assistant-tags` | `POST /assistant-tags` (label create, unique text) + `POST/DELETE /assistants/{id}/relationships/assistant-tags` (attach/detach) | `assistant_tags` |
| AssistantSettingValue | `assistant-setting-values` | `POST/PATCH/DELETE /assistant-setting-values(/{id})` | `assistant_setting_values` |
| Review (1:1) | `assistant-reviews` | `PATCH /assistant-reviews/{id}` | `assistant_review` |

The Eloquent relation method keeps its natural name (e.g. `user_prompts()`); only the JSON:API field name is prefixed, e.g. `HasMany::make('assistant_user_prompts', 'user_prompts')`.

Actions (`actions/remix`, `actions/chat-test`, `actions/favorite`) are not typed
resources — they are exempt from the naming convention.

---

## Decision Framework

### The Ownership Test

The single most reliable signal is the foreign key behavior in the database migration:

- **`cascadeOnDelete` on the child row** → the child is *owned* by the parent → **standalone resource + create-with-relationship** (Pattern 2).
- **`cascadeOnDelete` on a pivot/link table** → the *link* dies with the parent, but the target survives → **relationship endpoint** (Pattern 1).

### Create-with-relationship vs relationship endpoint

The frontend rule is unambiguous and the payload shape enforces it:

- **Am I creating new inline data?** → `POST /assistant-{child}` with `relationships.assistant` (create-with-relationship). The body carries attributes; the request creates the resource *and* links it atomically.
- **Am I linking something that already exists?** → `POST /assistants/{id}/relationships/{rel}` (linkage only, identifiers in the body, no attributes; supports single or bulk attach/detach/sync).

These are complementary, not redundant: you cannot send attributes to a relationship endpoint, and you cannot create-with-relationship without attributes. Relationship endpoints are **not** obsolete — `category`, `ai_tools`, and `shared_users` require them (independent, often bulk, not creatable here; `category` is seeded).

### Decision Table

| Question | If yes | If no |
|---|---|---|
| Does the child row have `cascadeOnDelete` on the parent FK? | standalone resource + create-with-relationship | → next question |
| Does the target exist independently of the parent? | relationship endpoint | → next question |
| Is this a resource create/update/delete at all? | think harder | custom action |

---

## Pattern 1: Relationship Endpoints

**`POST / DELETE / PATCH /{resource}/{id}/relationships/{rel}`**

Manage **linkages** to independently-existing resources.

### When to use
- The target resource (e.g., `User`, `AiTool`) has its own lifecycle outside the parent.
- The parent *references* the target; it does not *own* it.
- The database has a pivot/link table (e.g., `assistant_shared_users`, `assistant_tools`).

### Contract
- Body: `{ "data": [ { "type": "…", "id": "1" }, … ] }` — resource identifiers only.
- The target resource is **never created nor destroyed** by these operations.
- Authorization: parent's `update` policy, or dedicated `attach{Field}` / `detach{Field}` / `update{Field}` policy methods.

### Verb semantics by cardinality

| Cardinality | Attach / add | Remove / detach | Replace / sync |
|---|---|---|---|
| **To-one** (category) | — | `PATCH` with `data: null` | **`PATCH`** |
| **To-many** (ai_tools, shared_users) | **`POST`** | **`DELETE`** | **`PATCH`** |

To-one supports only `PATCH` — you **set** the linkage (to an id or `null`). `POST`/`DELETE` on a to-one relationship endpoint throws a `LogicException` (the `AttachRelationship` trait requires `->toMany()`).

### Examples
| Relationship | Endpoint | Target |
|---|---|---|
| `shared_users` | `POST /relationships/shared-users` | Users (independent) |
| `ai_tools` | `POST /relationships/ai-tools` | Global tool catalog |
| `category` | Inline PATCH only (to-one) | Global category taxonomy |

---

## Pattern 2: Owned children (standalone resource + create-with-relationship)

Owned children are created as **standalone top-level resources** with the parent supplied in `relationships`. This is the JSON:API spec-canonical way to "create + attach" in a single, atomic request (`#crud-creating`).

**`POST /assistant-user-prompts`** — create an owned child and link it to its parent in one request.

```json
{
  "data": {
    "type": "assistant-user-prompts",
    "attributes": { "text": "First prompt" },
    "relationships": {
      "assistant": { "data": { "type": "assistants", "id": "1" } }
    }
  }
}
→ 201 Created
```

**`DELETE /assistant-user-prompts/{id}`** — destroy the owned child.
**`PATCH /assistant-setting-values/{id}`** — update an owned child.
**`GET /assistants/{id}/assistant-user-prompts`** — list children via the parent relationship (`?include=assistant_user_prompts`).

### When to use
- The child table has a `cascadeOnDelete` FK to the parent (the child is *owned*).
- Creating the child requires inline data (e.g. `text`), not just linking an existing resource.
- The parent is known at creation time.

### Contract
- Body for create: `{ "data": { "type": "…", "attributes": { … }, "relationships": { "assistant": { "data": { … } } } } }`.
- The parent **must** be supplied in `relationships` (never as an `assistant_id` attribute — the spec says FK-style keys SHOULD NOT appear as attributes). A missing/nonexistent parent is rejected (422 / 404 respectively).
- `POST` creates + attaches atomically (the package fills the `BelongsTo` via `associate()` inside a DB transaction), returns `201`.
- Authorization is parent-aware: the caller must `update` (or `view`, for feedback) the referenced assistant.
- Standard CRUD lives on the standalone resource (`POST`/`PATCH`/`DELETE`); the parent relationship is read-only and is only used to *list/include* children.

### Implementation (laravel-json-api)
- The schema exposes a **writable** `BelongsTo::make('assistant')->type('assistants')`; `authorizable(): false` (authz is done in a controller `creating()`/`updating()`/`deleting()` hook against the parent).
- The request is a `ResourceRequest` with a rule for the `assistant` linkage.
- The package's `Store`/`Update`/`Destroy` pipeline does the work; no manual FK assignment.

### Why not a relationship endpoint for owned children?
`POST /assistants/{id}/relationships/assistant-user-prompts` is **linkage-only** (resource identifiers, no attributes) — it physically cannot create a child with inline data. Owned children therefore use the standalone create-with-relationship form. See Pattern 1 for what relationship endpoints *are* for.

### Special variants
- **`review` (1:1 upsert):** `PATCH /assistant-reviews/{id}` updates the single owned review record.
- **`setting_values` (formerly bulk):** now standard CRUD — one `POST`/`PATCH` per value. The `(assistant_id, setting_id)` uniqueness is enforced (422 on duplicate); a rapid burst of setting writes collapses into one version entry (see Versioning).

### Legacy form (being phased out)
The nested-URL form `POST /assistants/{id}/{child}` (parent encoded in the path) was the previous convention. New owned children use the standalone form above.

### Examples
| Child | Endpoint | Reason |
|---|---|---|
| `assistant-user-prompts` | `POST /assistant-user-prompts` (+`relationships.assistant`) | Inline text, owned by assistant |
| `assistant-feedback` | `POST /assistant-feedback` (+`relationships.assistant`) | Inline text, author = caller |
| `assistant-tags` | `POST /assistant-tags` then `POST /assistants/{id}/relationships/assistant-tags` | Shared global library; attach existing label |
| `assistant-setting-values` | `POST` / `PATCH /assistant-setting-values/{id}` | Per-value standard CRUD (was bulk upsert) |
| `assistant-review` | `PATCH /assistant-reviews/{id}` | 1:1 review record per assistant |

---

## Pattern 3: Custom Actions

**`POST /{resource}/{id}/actions/{name}`**

Non-resource operations that don't fit CRUD.

### When to use
- The operation **spawns a new top-level resource** (factory/derive).
- The operation is **not a resource CRUD** (SSE streaming, external trigger).
- The operation fundamentally **cannot be expressed** as standard create/update/delete.

### When NOT to use
- Changing an attribute → fold into `PATCH` of the resource.
- Creating an owned child → nested sub-resource.
- Managing linkages → relationship endpoint.
- The action only exists to trigger a side effect → move the side effect into the resource's lifecycle hooks.

### Examples
| Action | Endpoint | Reason |
|---|---|---|
| `remix` | `POST /actions/remix` | Deep-clones an assistant — spawns a *new* top-level resource |
| `chat-test` | `POST /actions/chat-test` | SSE streaming — not a resource operation |

---

## Special Cases

### Self-Service (favorite)
`POST /{resource}/{id}/actions/favorite` and `DELETE /{resource}/{id}/actions/favorite` with **no body**.
The subject is always the authenticated caller — sending an identifier is tautological.
A single toggle action with a boolean payload is ergonomically inferior to two stateless verbs.
Favorite is registered as an action because it is not CRUD on a typed resource (the
`assistant_favorite_users` pivot has no attributes and is never exposed as a resource).

### Folding into PATCH (release_stage)
`release_stage` was changed via `actions/release`. It is a writable attribute on the resource.
The only thing the action added was a side effect (review creation).
Solution: make the attribute writable via standard `PATCH`, and trigger the side effect in the `updated()` lifecycle hook.
No action needed.

---

## Action vs CRUD on Resource

### When to use CRUD on the resource

| Criteria | Mechanism | Example |
|---|---|---|
| Changing an attribute of the resource itself | inline `PATCH /assistants/{id}` | `release_stage` (was `actions/release`) |
| Creating/destroying an owned child | nested `POST/DELETE /assistants/{id}/{child}` | `user_prompts`, `feedback` (were all `actions/…`) |
| Creating/updating owned children | standalone resource + create-with-relationship | `assistant-user-prompts`, `assistant-feedback`, `assistant-setting-values` |
| Attaching/detaching a shared library label | relationship endpoint | `tags`, `ai_tools` (shared pool, reused across assistants) |
| Self-referential toggle | action `POST/DELETE` with no body | `favorite` (self-service toggle on assistant) |

Side effects (version bumps, review creation, event dispatch) are **not** a reason to keep an action — handle them in the controller's lifecycle hooks (`creating()`, `saved()`, `deleted()`) or model events.

### When to use an action

| Criteria | Example |
|---|---|
| The operation spawns a **new** top-level resource (factory/derive) | `actions/remix` — deep-clones an assistant |
| The operation is not a resource CRUD at all (SSE streaming) | `actions/chat-test` — streams chat events |
| The operation fundamentally can't be expressed as standard CRUD | (none left in HAWKI after the refactor) |

### Decision tree

```
Is this a CRUD operation?
 ├─ Creates/destroys a resource or changes an attribute? → CRUD on resource
 │   ├─ Changes an attribute of the resource itself? → inline PATCH
 │   ├─ Creates/destroys an owned child? → standalone resource + create-with-relationship
 │   ├─ Manages linkages to independent resources? → relationship endpoint
 │   └─ Self-service toggle? → action
 └─ Not CRUD (factory, streaming)? → action
```

### Real examples from the assistant API

| Operation | Before | After | Why |
|---|---|---|---|
| Change release_stage | `POST /actions/release` | `PATCH /assistants/{id}` (attribute) | Attribute update; side effect via `updated()` |
| Add prompt | `POST /actions/user-prompts` | `POST /assistant-user-prompts` (+`relationships.assistant`) | Owned child, create-with-relationship |
| Add feedback | `POST /actions/feedback` | `POST /assistant-feedback` (+`relationships.assistant`; author = caller) | Owned child, create-with-relationship |
| Add tag | (nested `POST /assistants/{id}/tags`) | `POST /assistant-tags` then `POST /assistants/{id}/relationships/assistant-tags` | Shared library label; create-once, attach existing |
| Configure settings | `POST /actions/settings` / bulk `POST /assistants/{id}/setting-values` | `POST`/`PATCH`/`DELETE /assistant-setting-values(/{id})` | Standard CRUD per value (bulk dropped) |
| Bookmark | `POST /actions/favorite` (bool toggle) | `POST/DELETE /assistants/{id}/actions/favorite` | Self-service, subject = caller |
| Clone assistant | `POST /actions/remix` | Kept as `actions/remix` | Factory — spawns a new top-level resource |
| Test chat | `POST /actions/chat-test` | Kept as `actions/chat-test` | SSE streaming, non-resource |
| Share assistant | (did not exist) | `POST /relationships/shared-users` | Manage linkages to independent Users |

---

## Examples Table (by model)

| Model | FK cascade | Pattern | Key endpoints | Reason |
|---|---|---|---|---|
| **UserPrompt** | ✅ `assistant_id` | create-with-relationship | `POST /assistant-user-prompts` (+rel.assistant) `DELETE /assistant-user-prompts/{id}` | Owned child, inline text creation |
| **Feedback** | ✅ `assistant_id` | create-with-relationship | `POST /assistant-feedback` (+rel.assistant) | Owned child, author = caller |
| **Tag** | ❌ pivot (`assistant_tag`) | shared library + relationship | `POST /assistant-tags` (unique text) + `POST/DELETE /assistants/{id}/relationships/assistant-tags` | Global label pool; reused, not duplicated |
| **AssistantSettingValue** | ✅ `assistant_id` | standard CRUD | `POST/PATCH/DELETE /assistant-setting-values(/{id})` | Per-value CRUD; uniqueness on (assistant, setting) |
| **Review** | ✅ `assistant_id` (1:1) | standalone update | `PATCH /assistant-reviews/{id}` `GET /assistants/{id}/assistant-review` | Single owned child per assistant |
| **User (shared)** | ❌ pivot | relationship | `POST /relationships/shared-users` `DELETE … /relationships/shared-users` | Independent User, shared via pivot |
| **AiTool** | ❌ pivot | relationship | `POST /relationships/ai-tools` `DELETE … /relationships/ai-tools` | Global tool catalog, shared via pivot |
| **Category** | ❌ BelongsTo | relationship (inline PATCH only) | Inline via `PATCH /assistants/{id}` | Global taxonomy, to-one reference; seeded, not creatable |
| **Favorite** | ❌ pivot | action | `POST /assistants/{id}/actions/favorite` `DELETE /assistants/{id}/actions/favorite` | Subject = caller, no body, two stateless verbs |
| **Version** | ✅ cascade | server-managed | Read-only via `GET /assistants/{id}/versions` | Append-only history; no client create/delete |
| **Remix** | n/a | action | `POST /actions/remix` | Factory — spawns a new top-level resource |
| **Chat-test** | n/a | action | `POST /actions/chat-test` | SSE streaming, non-resource |

---

## Anti-Patterns

- **Linkage-only relationship endpoint for owned children.** `POST /assistants/{id}/relationships/assistant-user-prompts` carries resource **identifiers only** — it cannot create a child with inline data (`text`, `value`). Owned children are created via the standalone resource with the parent in `relationships` (`POST /assistant-user-prompts` + `relationships.assistant`). The distinction is enforced by the payload: attributes → standalone POST; identifiers → relationship endpoint.

- **Putting the parent FK in `attributes`.** `assistant_id` (or any FK-style key) SHOULD NOT appear as an attribute — use the `relationships.assistant` linkage instead (JSON:API spec). The server fills the FK from the linkage.

- **Encoding the parent in the URL for owned children.** The nested form `POST /assistants/{id}/{child}` (parent in the path) is legacy. New owned children use the standalone form; the parent travels in the request body.

- **Nesting a global collection when you need a cross-resource admin list.** `GET /assistants/reviews` as a nested collection is ambiguous (does it list all reviews or only those of a specific assistant?). Use a top-level resource path like `GET /assistant-reviews` for cross-resource queries.

- **Using an action for standard CRUD.** If the operation creates, updates, or deletes a resource (or changes an attribute), it should be expressed as CRUD, not as an action. Actions are for factory operations, streaming, and genuinely non-resource behaviors.

- **Keeping an action solely for its side effects.** Version bumps, review creation, and event dispatch are lifecycle concerns — move them into controller hooks (`creating()`, `saved()`, `deleted()`) or model events. The API contract shouldn't change just because there's a side effect.

- **Boolean toggle payloads.** `{ "is_favorite": true }` is a single action with a stateful parameter. Replace with two stateless verbs: `POST` to add, `DELETE` to remove.

## Versioning

Owned-child writes (and any assistant update) dispatch an `AssistantUpdated` event, which records a `Version` row for non-draft/non-private assistants. To keep a burst of changes (e.g. saving several setting values, or repeated edits to the same field) from spawning one version per request, the version listener **debounces**: if the assistant's most recent version records the *same* changed keys and was created within a short window, the new version is skipped. Different keys always get their own version.
