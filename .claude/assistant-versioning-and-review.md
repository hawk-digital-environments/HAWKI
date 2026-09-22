# Idea: Assistant versioning and review workflow

**Status:** Planned (not implemented). **Date:** 2026-09-21

**Companion docs:** `conversation-user-settings.md` (the per-conversation settings that **pin the
assistant version** used in a chat — see "Version pinning contract" in Part 4),
`user-notifications.md` (review submission/decision notifications ride on that system),
`user-favorites.md` / `user-settings-and-config-schema.md` (same JSON:API + domain-layer planning
style). HAWKI backend conventions live in the `hawki-backend` skill and
`_documentation/500-Backend/`.

---

## The goal

Replace the current single-row assistant lifecycle (`release_stage` + one 1:1 review row on the
`assistants` table) with **immutable published versions plus one editable draft**:

- The `assistants` table becomes the **stable identity** (handle, ownership, live pointer). The
  actual content lives in `assistant_versions` rows — version **0 is always the draft**, the only
  row the author can modify.
- Releases to a **public** stage must pass a **review** (open → in progress → approved / denied),
  with **PR-style flags** on individual fields and threaded comments between reviewer and author.
  Private releases publish **immediately** — no review row, no reviewer queue.
- Publishing a change **never takes the currently live version offline** — the live version stays
  untouched until the review is approved, and only then does the draft row become the next version.
- The review lifecycle is a formal state machine built on
  [`spatie/laravel-model-states`](https://raw.githubusercontent.com/spatie/laravel-model-states/refs/heads/main/docs/01-introduction.md),
  and all side effects (file copies, RAG reconciliation, UI state) run through HAWKI domain events
  and auto-discovered listeners.

**Explicitly out of scope (here):** notifications (submission/decision notifications are
designed to ride on the planned `user-notifications.md` system, as listeners on the review
lifecycle events — they are not built in this feature); a reusable generic review/flag component
for other domains; the plugin-system integration of assistants; UI wireframes (this doc defines
behaviour, not design).

---

## Current state and why it breaks

Branch: `feature/lernki/assistants`.

| Piece | Location | Problem |
|---|---|---|
| Single content row | `assistants` table (`database/migrations/2026_08_28_000001_create_assistants.php`) | Working copy and live version are the **same row**. `assistant_versions` is only a change log (decimal version + note + `changed_keys`) — no content snapshot, so a previous version cannot be restored. |
| Publish handler | `AssistantService::release()` (`app/Services/Assistant/AssistantService.php`) | `release()` with a non-public target **demotes immediately** (draft/private path). The Publish page's radio is initialised from the current stage, so picking "Keep as draft" for a live assistant takes it offline instantly. |
| Denial demotes the whole assistant | `AssistantReviewService::deny()` → `revokeRelease()` | A denial pushes the assistant to `PRIVATE` — the *entire* state, not just the pending change. Since every edit of a public assistant reopens the review (`AssistantResetReviewOnUpdate`), a stale pending review answered with `NEEDS_REVISION`/`DENIED` sends the previously-live assistant offline. |
| Edits hit the live row | `AssistantController::updated()` + autosave PATCH | Content PATCHes mutate the live row directly; for a same-stage public target `release()` is a no-op, so the reopened review never gates the change. |
| 1:1 review | `assistant_reviews.assistant_id` is `->unique()` | Only one review row per assistant, continuously reopened/reset (`updateOrCreateForAssistant`), so no review history and no per-field feedback. |
| Hand-rolled transitions | `AssistantService` / `AssistantReviewService` if-chains | No formal state machine; status enum `AssistantReviewStatus` (`pending/approved/denied/needs_revision`) mixes "who may act" with lifecycle state. |

Useful facts that survive the redesign:

- `ai_convs.assistant_handle` links conversations to assistants **by handle** — already
  identity-based, no change needed.
- RAG datasets are keyed `config('rag.dataset_prefix') . $assistant->id` — stays valid because
  the identity keeps the current `assistants.id` (see Part 1).
- `assistant_versions` **already has** the unique index `(assistant_id, version)` — the new draft
  invariant can reuse it.
- Avatars are DB rows (`assistant_avatars`: name + CSS icon), not files — copying a draft is
  trivial for them.

---

## Decided behaviour (confirmed 2026-09-21)

| Question | Decision |
|---|---|
| Where do versions live? | Content rows live in `assistant_versions` (repurposed from change log to **content snapshots**); `assistants` is the stable identity. This is the normalised form of the original sketch (handle + version + warning table): handle → `assistants.handle`, version/warning/content → `assistant_versions`. The sketch's combined unique index already exists as `assistant_versions_assistant_id_version_unique`. |
| Why not version rows in `assistants`? | The `assistants.id` must stay stable: favorites, shares, feedback, user prompts, policies, RAG dataset ids, and the builder URL all reference "the assistant". If content rows carried the public id, every draft re-creation would change it. Keeping `assistants` as identity means **zero re-keying** for all cross-version tables. |
| Draft invariant | Version `0` is always the draft; at most one row with `version = 0` per assistant (enforced by the existing `(assistant_id, version)` unique index). |
| Creating a new version | Releases to a **public** stage (`organizational`/`federated`) require a review; the submission carries the intended `release_stage`, and the approved version is published at that stage. Releases to `private` **skip the review entirely** — the draft is re-versioned immediately (same mechanics as approval, no review row); a `private` release makes the assistant available to **the author only**, no broader exposure, which is why it needs no review. The reviewer queue only ever sees public-stage submissions. |
| Draft editability | The draft is locked while a review for the assistant is `open` or `in_progress` (derived query — no separate lock column, single source of truth). `denied`, `canceled`, `approved` or "no review" = editable. (`approved` never coexists with a draft: on approval the draft row *becomes* a released version.) |
| Cancelling a review | Only the author, and only while the review is still `open` (the reviewer has not engaged). Flags cannot exist yet on an `open` review (reviewers create flags only during `in_progress`), so cancellation has nothing to resolve. `in_progress` reviews cannot be cancelled by the author — the reviewer must `deny`. |
| Approval | Allowed only when the assistant has **no unresolved flags** (all flags have `resolved_review_id` set). On approval the draft row is re-versioned to `max(version) + 1` (row, children and files keep their ids — no copy needed at approval time) and becomes the live version. |
| Denial | Review status `denied` + a global `message` explaining why. Flags stay unresolved; message + flags are shown to the author, who edits the draft (now unlocked) and submits a **new** review row. Resubmission never reopens the old row. |
| Version inheritance | **None.** A new draft can be created from *any* released version in the history; version numbers are per assistant and monotonically increasing. |
| Draft replacement | Creating a draft while one exists replaces it (full cascade delete of the old draft). Blocked while a review is `open`/`in_progress` — the author must cancel (open) or wait for the reviewer (in_progress) first. |
| File copying | Creating a draft from a version copies **all files assigned to that version on disk** (new uuids, 4-level sharding, `.meta.json` sidecars) — a carbon copy. Discarding a draft deletes its copied files, attachment rows and RAG documents. |
| Unchanged files | Accepted cost: draft creation always clones and re-ingests everything, even when content is unchanged (e.g. a pure stage escalation). No hash-based skip in v1 — a future centralized "which file is attached to which element" registry can dedupe copies later. |
| Warning field | `warning` on a released version is **set by the author, not the reviewer**. Authors cannot delete or unpublish a released version — the warning is their tool to alert users of known problems (e.g. "Has an issue with outdated sources!"). Author- and admin-editable on any released version **without** a review round; end-user-visible in the store and (per the pinning contract) in the composer of conversations pinned to that version. |
| Stage changes on released versions | **There is no unpublish and no stage demotion at all — not even for admins.** The lifecycle is forward-migration only: a problematic version is superseded by a new version or flagged via the author's warning; rolling back would strand conversations pinned to the removed version. The only removal is deleting the **whole assistant** (existing `DELETE /assistants/{id}`), which cascades versions, files and RAG documents. Upward escalation goes through the normal draft→review cycle (submitting an unchanged draft is the explicit path). |
| Notifications | Review submission notifies the reviewers; decisions (approve/deny) notify the author — through the planned `user-notifications.md` system, as listeners on `AssistantReviewSubmittedEvent` / `AssistantReviewApprovedEvent` / `AssistantReviewDeniedEvent`. They land when that system ships. |
| Handle immutability | Handles can never be reassigned, period. `assistants.handle` is set once and stays — renaming means creating a new assistant. |
| Starter prompts | `assistant_user_prompts` (serialized as `starterPrompts`) are author-defined content — the builder edits them and remix copies them. They therefore follow the **version row** like every other content relation: a new version ships its own prompt set. |
| State machine | `spatie/laravel-model-states` on `assistant_reviews.status` (new install). Models stay data descriptors: the package only enforces allowed transitions + column bookkeeping; all side effects live in services + listeners (HAWKI event-driven style). |
| Old change log | `assistant_versions.text`/`changed_keys` (change-log concept) are dropped. The author's release note is stored per version as `release_note` (set at submission). Version-to-version diffs are computed from the snapshots themselves. |
| Existing dev data | The feature never shipped — there is no production data to preserve. The original create-migration is **edited in place** and the dev database is rebuilt with `migrate:fresh` (see Part 6). No data-migration command, no compatibility layer. |
| Flag resolution | Only the reviewer resolves flags (`resolved_review_id` = review that judged the fix, typically the next review cycle). Unresolved flags block approval, nothing else. |

---

## Part 1 — Data model (schema + migrations)

### `assistants` — the identity (modified)

Keeps its current `id` (this is what makes favorites/shares/feedback/RAG/URLs survive untouched).

| Column | Change |
|---|---|
| `handle` | stays, unique nullable, **immutable once set** (rename = new assistant). |
| `creator_id`, `organization_id` | stay — ownership is identity-level. |
| `remixed_creator_id`, `remixed_assistant_id` | stay, `remixed_assistant_id` now points to the *source identity*; remix always clones the source's **live version**. |
| `live_assistant_version_id` | **new**, FK → `assistant_versions.id`, nullable, unique (NULL = never published). Once set it only ever moves **forward** (approval or private release) — there is no unpublish (see decision table). The one row that is "the assistant" for conversations and the store. |
| `release_stage`, `requested_release_stage` | **removed** (stage moves to the version row / review row). |
| `name`, `system_prompt`, `greeting`, `description`, `detail_description`, `allow_remix`, `category_id`, `allow_model_select`, `model`, `max_tokens`, `temp`, `top_p`, `capabilities` | **moved to `assistant_versions`** — content is per version. |

### `assistant_versions` — content snapshots (repurposed)

| Column | Change |
|---|---|
| `assistant_id` | stays, FK cascade → `assistants`. |
| `version` | `decimal(8,1)` → **unsigned int, default 0**. `0` = draft, released versions start at `1`. |
| `warning` | **new**, text nullable — per-version notice (see decision table). |
| `release_note` | **new**, text nullable — the author's note submitted with the review. |
| `release_stage` | **new** (moved), string. `draft` on version-0 rows; the approved stage on released rows. |
| `published_at` | **new**, timestamp nullable. |
| `text`, `changed_keys` | **removed** (change-log concept dies). |
| content columns | moved from `assistants` (see above). |
| indexes | existing unique `(assistant_id, version)` stays — it *is* the "one draft per assistant" invariant. |

### `assistant_reviews` (reworked — history, not 1:1)

| Column | Change |
|---|---|
| `assistant_id` | FK cascade → `assistants` (**identity**), **unique constraint dropped** → plain index. Review history is preserved across cycles. |
| `status` | state machine column: `open` → `in_progress` → `approved` \| `denied`; plus terminal `canceled`. Default `open`. |
| `requested_release_stage` | **new** (replaces `assistants.requested_release_stage`) — the stage the author asks to publish at. |
| `message` | rename of `reason` — the reviewer's global denial message (or short approval note). |
| `reviewer_id`, `reviewed_at`, `submitted_at` (new), timestamps | stay / add. |

### `assistant_review_flags` (new)

| Column | Content |
|---|---|
| `assistant_id` | FK → `assistants` (identity — flags survive draft re-creation, which is what powers the "this field had issues in the past" hint). |
| `created_review_id` | FK → `assistant_reviews` — review during which the flag was raised. |
| `resolved_review_id` | FK → `assistant_reviews`, **nullable** — the review that judged the fix. NULL = unresolved. |
| `field` | string — assistant attribute key the flag refers to (`name`, `description`, …). |
| `value_on_create` | text nullable — snapshot of the field's value when the flag was created; the UI diffs it against the current draft value. |
| `message` | text — the reviewer's explanation. |
| indexes | `(assistant_id, resolved_review_id)` for "unresolved flags of assistant X"; FK indexes. |

### `assistant_review_flag_comments` (new)

| Column | Content |
|---|---|
| `flag_id` | FK cascade → `assistant_review_flags`. |
| `user_id` | FK → `users` (author vs. reviewer attribution). |
| `message` | text. |
| `created_at` | ordering (standard timestamps). |

### Which relations follow which row

| Follows the **version row** (copied with the draft, re-pointed to `assistant_versions.id`) | Follows the **identity** (`assistants.id`, unchanged FKs) |
|---|---|
| `assistant_attachments` (+ files on disk, RAG columns), `assistant_tools` (pivot), `assistant_tag` (pivot), `assistant_setting_values`, `assistant_avatars`, `assistant_user_prompts` (starter prompts — author content), `assistant_reviews`, `assistant_review_flags` | `assistant_favorite_users`, `assistant_shared_users`, `assistant_feedback`, `ai_convs` (by handle), RAG dataset id |

All schema changes go **directly into the original create migration** — the feature branch has
no deployed data, so historic-migration rules don't apply here (see Part 6).

---

## Part 2 — State machine (`spatie/laravel-model-states`)

Install: `bin/env composer php require spatie/laravel-model-states` (not currently in
`composer.json`).

State classes live in `app/Services/Assistant/States/` (domain-owned, not the package default
`App\States`):

```
AssistantReviewStatusState        (abstract, extends Spatie\ModelStates\State)
├── OpenState                     default on submission
├── InProgressState
├── ApprovedState                 terminal
├── DeniedState                   terminal
└── CanceledState                 terminal
```

Allowed transitions (declared with the package's transition attributes):

| From | To | Trigger | Guard |
|---|---|---|---|
| `open` | `in_progress` | reviewer opens the review | reviewer/admin only |
| `open` | `canceled` | author cancels | author/creator only; no flags can exist (see decision table) |
| `in_progress` | `approved` | reviewer approves | **no unresolved flags for the assistant** |
| `in_progress` | `denied` | reviewer denies | `message` required |

Everything else is illegal — the package throws, the service converts to a domain exception
(`App\Services\Assistant\Exceptions\…`). The old `AssistantReviewStatus` enum is replaced by
these states (the enum's `needs_revision` case has no equivalent: a denied review *is* the
revision request, carried by message + flags).

Private releases never enter this machine — `release()` with a non-public target re-versions
the draft directly (Part 3), so no `open` review row exists for them and the reviewers' queue
only ever contains public-stage submissions.

Model wiring: `AssistantReview` uses the package's `HasStates` and registers the `status` column.
**Models stay data descriptors** — transition classes only flip the state; version assignment,
live-pointer switch, events and file work happen in the services/listeners below.

The **draft lock is derived, not stored**: `AssistantRepository::isDraftLocked(identity)` =
exists review with `assistant_id` and `status in (open, in_progress)`. Every draft-mutating path
(builder PATCH, attachment upload, draft discard/create) guards through it.

---

## Part 3 — Backend domain layer (`app/Services/Assistant/`)

All `#[Singleton]` + `@api` + `readonly`, DB access only through repositories, side effects only
through events — per HAWKI/laravel-skill rules.

| Piece | Location | Content |
|---|---|---|
| Draft lifecycle | `AssistantDraftService` (new) | `createFromVersion(Assistant $identity, int $sourceVersion): AssistantVersion` — in one transaction: lock identity, assert no draft under review, delete an existing draft (cascade, see listeners), clone the source version row + content children (`attachments` get **new uuids**, `rag_*` reset; tools/tags/settings/avatar/starter-prompt rows copied), then dispatch `AssistantDraftCreatedEvent`. `discard(Assistant $identity)` — deletes draft row + children, dispatches `AssistantDraftDiscardedEvent`. |
| Release | `AssistantService::release(Assistant $identity, AssistantReleaseStage $target, ?string $note)` (keeps today's method + action name, new semantics) | Validates draft exists + unlocked; sets `release_note` on the draft row. **Public** target → creates the review row (`open`, `requested_release_stage`, `submitted_at`), dispatches `AssistantReviewSubmittedEvent`. **Private** target → runs the approval mechanics inline: re-version draft to `max+1`, set stage, switch live pointer, dispatch `AssistantVersionPublishedEvent`. The version-assignment logic is one shared private method used by both this path and `AssistantReviewService::approve()`. |
| Review lifecycle | `AssistantReviewService` (rework) | `open(AssistantReview)` → `in_progress` (records `reviewer_id`) · `cancel(AssistantReview)` (author, `open` only) · `deny(AssistantReview, string $message)` · `approve(AssistantReview)` — guard via `AssistantReviewFlagRepository::hasUnresolvedFlags()`, then inside one transaction: state → `approved`, **re-version the draft row** (`version = max(version)+1`, `release_stage = requested`, `published_at` via `CarbonClockInterface`), switch `assistants.live_assistant_version_id`, dispatch `AssistantVersionPublishedEvent`. |
| Flags & comments | `AssistantReviewService` (cont.) | `addFlag(review, field, message)` — reviewer, only while `in_progress`; snapshots `value_on_create` from the current draft. `resolveFlag(flag, review)` — reviewer; sets `resolved_review_id`. `addComment(flag, user, message)`. `setWarning(AssistantVersion, ?string)` — **author** of the identity or admin, on released versions, no review round (decision table). |
| Repositories | `app/Services/Assistant/Repositories/` | Rework `AssistantReviewRepository` (no `updateOrCreateForAssistant` reopen-hack — history append), new `AssistantReviewFlagRepository` (`hasUnresolvedFlags`, `forAssistant`, `unresolvedForAssistant`), `AssistantDraftRepository` (clone/cascade helpers). `AssistantRepository` gains `isDraftLocked()`, `liveVersion()`, `setLiveVersion()`. |
| Storage | `app/Services/Storage/` | New `copy(StoredFileIdentifier $source): StoredFileIdentifier` on `AbstractFileStorage`/`FileStorageService` — copies blob + `.meta.json` sidecar + `output/` extracts to a **new uuid** under the same category. |

### Events (new set, `app/Services/Assistant/Events/`)

`AssistantReviewSubmittedEvent`, `AssistantReviewOpenedEvent`, `AssistantReviewApprovedEvent`
(carries identity + new live version), `AssistantReviewDeniedEvent`,
`AssistantReviewCanceledEvent`, `AssistantDraftCreatedEvent`, `AssistantDraftDiscardedEvent`,
`AssistantReviewFlagCreatedEvent`, `AssistantReviewFlagResolvedEvent`.

Removed: `AssistantReleaseStageChangedEvent` (replaced by `AssistantVersionPublishedEvent`;
with no demote path there is no unpublished-event).

### Listeners (auto-discovered via `app/Services/*/Listeners`)

| Listener | On | Work |
|---|---|---|
| `CopyAssistantFilesOnDraftCreation` | `AssistantDraftCreatedEvent` | Physical file copy for each cloned attachment (`FileStorageService::copy()`), updates the cloned rows' uuids. Runs **after** the transaction commits (queued listener or `afterCommit`) — file work must not block the DB transaction, and failures are logged + retried, never rolled back. |
| `DeleteAssistantDraftCascade` | `AssistantDraftDiscardedEvent` | Deletes the draft's files; attachment-row deletion flows through the existing observer → `DeleteStoredFileOnAssistantAttachmentDeletion`, `CancelPendingRagIngestionOnAssistantAttachmentDeletion`, `DeleteRagDocumentOnAssistantAttachmentDeletion` listeners (reuse, don't duplicate). |
| `ReconcileRagOnPublish` | `AssistantVersionPublishedEvent` | The dataset stays keyed by identity id (unchanged!); document source ids are the attachment uuids. Deletes documents of attachments no longer on the live version, ingests missing documents of the live version. |
| `AssistantCreateInitialVersion`, `AssistantUpdatedVersion`, `AssistantResetReviewOnUpdate`, `AssistantReleaseStatus` | — | **Deleted** — superseded by explicit version snapshots, the derived draft lock, and the review lifecycle. |

---

## Part 4 — JSON:API surface

| Resource / route | Content |
|---|---|
| `assistants` (identity, reworked schema) | Attributes: `handle`, creator/org relations, `live_assistant_version_id`; relationships: `live-version`, `draft` (the version-0 row or null), `reviews`, favorites/feedback as today. Store listing = `GET /assistants?include=live-version` with `indexQuery()` filtering to identities whose live version has a public stage. The builder URL keeps the stable identity id. |
| `assistant_versions` (reworked, becomes the editable content resource) | All content fields + `version`, `warning`, `release_stage`, `release_note`, `published_at`. Writable surface: content fields only on **draft rows** (`draftLocked` virtual attribute exposes the lock state); `warning` author/admin-writable on **released** rows. Everything else `readOnly`. Relationships: attachments, tools, tags, settings, avatar, user prompts. `AssistantVersionsController` replaces the generic update path for drafts; `updated()` hook validates the draft is unlocked (422 with the lock reason otherwise) — replaces the `changedKeys`/version-bump logic. |
| `assistant_reviews` (rework) | Read for author + reviewer; history per assistant. Dedicated **actions** replace today's semantic PATCH of `status`: `POST …/actions/open`, `actions/approve`, `actions/deny` (message required), `actions/cancel`. |
| `assistant_review_flags` (new) | Reviewer-writable while the creating review is `in_progress`: `POST` (field, message), `POST …/{id}/actions/resolve`. Includes `value_on_create` + current draft value for diff rendering. |
| `assistant_review_flag_comments` (new) | `POST` only (author + reviewer), ordered by `created_at`. |
| Actions on the identity | `POST assistants/{id}/actions/release` **keeps its URL** but routes internally by target stage: public → review submission, private → immediate publish. New: `actions/create-draft` (body: `source_version`), `actions/discard-draft`. **There is no unpublish/demote action** — the only removal is the existing `DELETE /assistants/{id}` (whole assistant, cascades versions, files and RAG documents). |

Policies follow the existing split: author/creator + org-admin for draft actions
(`AssistantPolicy` on the identity), reviewer/privileged users for review actions (the
`AssistantReviewSchema::indexQuery` privileged-scoping logic is reused). `authorizable(): false`
stays — middleware + scopes handle access, per HAWKI convention.

### Version pinning contract (for `conversation-user-settings.md`)

- The conversation settings field for the active assistant must pin **identity + version**
  (e.g. an `assistant_version_id`, or `{handle, version}`), not just the assistant: first
  selection pins the live version *at that moment*, and the conversation keeps using it across
  later releases.
- On chat load the composer compares pinned vs. live: pinned version carries a `warning` →
  notice ("something is wrong with this version"); live version number > pinned → "a newer
  version of this assistant is available, switch?" prompt.
- `ai_convs.assistant_handle` keeps linking conversations to assistants by handle; resolving the
  pinned *version* is a settings concern, no schema change here. This doc's only obligations:
  keep `warning` and version numbers queryable per identity, and never mutate a released row
  except its `warning`.

---

## Part 5 — Frontend impact (`resources/js/plugins/assistants/`)

| Area | Change |
|---|---|
| Builder (`BuilderContext.svelte.ts`) | Autosave PATCHes the **draft version** resource. New states: "no draft yet" (CTA: create draft from live version), "locked — under review", "denied — fix flags & resubmit". `requestRelease()` posts `actions/release`; the response carries either the created review (public) or the published version (private). |
| Publish page (`publish.svelte`, `ReleaseStage.svelte`) | Becomes the release + review status page: target stage selector, release note, review status timeline, denial message, unresolved flag list with field diff (`value_on_create` vs current), comment threads. Selecting a private target publishes immediately (no review UI shown); selecting a public target submits for review. |
| Admin review UI (**new**, `resources/js/plugins/admin/…` or assistants plugin module) | Review queue (`assistant-reviews?filter[status]=open,in_progress`), review detail with field diffs, flag creation, flag resolution, comments, approve/deny with message, cancel (author side is on the publish page). |
| Version timeline (`VersionTimeline.svelte`) | Real versions now — full snapshots, release notes, `warning` badge + author-side warning editor (set/clear on any released version, no review round), "create draft from this version" action per released version. |
| Clients & types (`assistantsClient.ts`, `types/assistant/*`) | New `Flag.ts`, `FlagComment.ts`, reworked `Review.ts`, `Version.ts`; `ReleaseMode.ts` semantics change (release action routes public→review, private→instant). Zod schemas mirror the new wire format. |
| Translations | All new strings through `resources/language/assistants_{de,en}*.json` — no hardcoded strings. |

Note for the implementer: this is a **breaking change** of the assistants wire format — the
plugin's schemas and stores change in lockstep with the backend, no compat layer.

---

## Part 6 — Schema rework in the create migration

The assistant feature has **not shipped** — there is no production data to preserve. Do not stack
alter-migrations or a data-migration command on top; restructure the original
`database/migrations/2026_08_28_000001_create_assistants.php` **in place**, then rebuild the dev
database (`bin/env artisan migrate:fresh --seed`). Everything below refers to that one file.

Concrete edits, table by table:

1. **`assistants`** (currently l.21–67): remove `release_stage`, `requested_release_stage` and
   every content column (`name`, `system_prompt`, `greeting`, `description`,
   `detail_description`, `allow_remix`, `category_id`, `allow_model_select`, `model`,
   `max_tokens`, `temp`, `top_p`, `capabilities` — they move to `assistant_versions`); add
   `live_assistant_version_id` **after** the `assistant_versions` table is created (Laravel
   orders `Schema::create` calls, so either move the `assistant_versions` block above
   `assistants` or add the FK column with a follow-up `Schema::table()` call in the same
   migration). Keep `handle` (unique nullable), `creator_id`, `organization_id`,
   `remixed_creator_id`, `remixed_assistant_id`.
2. **`assistant_versions`** (currently l.69–82): drop `text` and `changed_keys`; change
   `version` from `decimal(8,1)` to `unsignedInteger()->default(0)`; add the moved content
   columns + `warning` (text, nullable), `release_note` (text, nullable), `release_stage`
   (string, default `'draft'`), `published_at` (timestamp, nullable). The existing
   unique `(assistant_id, version)` stays unchanged — it is the one-draft invariant.
3. **`assistant_reviews`** (currently l.127–141): remove `->unique()` on `assistant_id` (add a
   plain index instead — reviews become history); rename `reason` → `message`; change the
   `status` default from `'pending'` to `'open'`; add `requested_release_stage` (string,
   nullable) and `submitted_at` (timestamp, nullable).
4. **New tables in the same migration** (place them after `assistant_reviews`):
   `assistant_review_flags` and `assistant_review_flag_comments` with the columns and indexes
   from Part 1.
5. **Re-point content children** — change the FK targets from the `assistants` default to
   `assistant_versions`: `assistant_attachments`, `assistant_tools`, `assistant_tag`,
   `assistant_setting_values`, `assistant_avatars` (its unique stays, now per version row),
   `assistant_user_prompts`. Leave `assistant_favorite_users`, `assistant_shared_users`,
   `assistant_feedback` pointing at `assistants`.

Follow-up code edits the schema change forces (checklist for the implementing party):

- **Factories/seeders** — `AssistantFactory` now seeds the identity + a version row (and usually
  a live pointer + a draft); check the tool seeder still passes (it already needed a fix once).
- **`AssistantObserver`** (`app/Observers/AssistantObserver.php`) — the `creating` hook that
  force-fills `creator_id`/`organization_id` splits: creator/org go on the identity, the version
  row creation moves to `AssistantDraftService` / the store flow; the attachment-cascade
  `deleting` hook stays but must cascade the draft's files through the existing
  attachment-deletion events.
- **`AssistantPolicy` + child policies** — permission checks move from "edit the assistant" to
  "edit the identity's draft", and the review/flag actions get their own checks (Part 4).
- **`composer.json`** — `bin/env composer php require spatie/laravel-model-states`, then wire
  the states in `app/Services/Assistant/States/` (Part 2).
- Re-run `bin/env test php all` — the reworked suites (Part 7) replace the old release/review
  expectations; the JSON:API fixtures in `tests/Feature/Api/Assistant/Fixtures/Assistant.php`
  build identities + version rows instead of single content rows.

---

## Part 7 — Testing

Per HAWKI conventions (`tests/Unit/…`, `tests/Feature/Api/…`, `$sut`, `testIt…`,
`static::assertSame()`, coverage attributes):

| Suite | Content |
|---|---|
| Unit — states | `AssistantReviewStatusStateTest`: every allowed transition; every illegal one throws with the mapped domain exception message. |
| Unit — `AssistantDraftService` | Draft creation clones all content children (incl. starter prompts) + resets `rag_*`; replacing a draft cascades; blocked while review `open`/`in_progress`. |
| Unit — `AssistantService` | `release()` with a private target re-versions the draft immediately (no review row, live pointer switched); with a public target creates an `open` review and leaves the live version untouched; version numbering (`max+1`, never reuses, per identity). |
| Unit — `AssistantReviewService` | Lock derivation; cancel only in `open`; approve blocked by unresolved flags; deny requires message; flag snapshot timing (`value_on_create`); reviewer-only resolution. |
| Unit — storage copy | `FileStorageService::copy()` preserves extension/`.meta.json`/extracts and issues a new uuid. |
| Feature — JSON:API | Rework `AssistantReleaseTest` (keeps its name, mirrors the action: locked draft 422, public target → review created `open`, private target → instant publish), `AssistantReviewTest` (action endpoints, flag/comment lifecycle), new `AssistantDraftActionsTest`, `AssistantWarningTest` (author can set/clear on released rows, non-authors cannot), `AssistantVersionPublishTest` (live version never changes while review is open — **the regression test for the original bug**). |
| Update | `AssistantUpdateTest` (now PATCHes the draft version), `AssistantRemixTest` (remix from live version), `AssistantChildResourcePolicyTest`, `AssistantIncludeTest`, fixture trait `Fixtures/Assistant.php`. |

---

## Open question

| Question | Status |
|---|---|
| **Reviewer pool** — who counts as a reviewer (org admins today? a dedicated role?) | Open, tracked with the `user-notifications.md` plan (it needs the same answer for routing submission notifications). The privileged-scoping logic in `AssistantReviewSchema::indexQuery` is the seam where the answer lands. |

Decided in review on 2026-09-21 (moved into the decision table): private releases skip review and publish immediately (author-only visibility) · unchanged files are always cloned + re-ingested (no hash-skip in v1) · the warning is author-set, editable without a review round · **no unpublish/demotion exists — forward-migration only, whole-assistant deletion aside** · review notifications ride on the planned user-notifications system · handles are never reassigned · starter prompts (`assistant_user_prompts`) are version content.
