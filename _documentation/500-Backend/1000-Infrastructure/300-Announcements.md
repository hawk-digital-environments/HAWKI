# Announcement System

HAWKI's announcement system lets administrators publish notices to users — policy updates,
maintenance windows, or welcome messages — with flexible targeting, scheduling, and display
control.

:::warning[Known technical debt in AnnouncementService]
`App\Services\Announcements\AnnouncementService` currently uses `Auth::user()` (facade call),
`Session::put()` (session access from a service), and `now()` (direct time construction) — all
of which violate HAWKI's coding standards for services. This is a confirmed deviation tracked
in the [Technical Debt Register](../100-Architecture/300-Technical-Debt.md). Do not copy these
patterns; follow the standard (constructor-injected `CarbonClockInterface`, no facades in services,
no session access) in any new code you write in this area.
:::

## Two UIs, two consumption paths

HAWKI currently ships two frontends, and they consume announcements differently:

- **Legacy UI** (`/chat`, Blade + `public/js/*`): session-based flow through
  `AnnouncementService` — described in *Forced Display*, *Scheduling* and *Per-User Tracking*
  below. Being replaced; do not extend.
- **New Svelte frontend** (`/new/*`): fetches the `announcements` JSON:API resource —
  described in *JSON:API Resource* and *Ordering & Display Behavior*. All new announcement
  work targets this path.

Both read the same `announcements` / `announcement_user` tables and share the CLI below.

---

## Creating an Announcement

The quickest way is the CLI. `announcement:make` scaffolds the per-locale markdown content
files, `announcement:publish` creates the database entry:

```bash
# 1. Scaffold content files under resources/announcements/{slug}/{lang}.md
php artisan announcement:make "Privacy Policy Update"

# 2. Create the database entry referencing that folder as the `view`
php artisan announcement:publish \
    "Privacy Policy Update" privacy-policy-update \
    --type=policy --force=true --global=true \
    --start="2025-09-01 00:00:00" --expire="2025-09-30 23:59:59"
```

Both commands prompt interactively for anything not passed as arguments/options and fall
back to safe defaults when run non-interactively (`--no-interaction`). `announcement:publish`
supports `{title?} {view?}` plus `--type=` (`policy`, `news`, `system`, `event`, `info`),
`--force=`, `--global=`, `--users=` (repeat per user id or comma-join; only used when
`--global=false`), `--anchor=` (a frontend event name such as `FileUpload`), `--start=` and
`--expire=` (`Y-m-d H:i:s`).

Programmatically, `AnnouncementService::createAnnouncement()` takes the same parameters:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `title` | `string` | required | Display title of the announcement |
| `view` | `string` | required | Name of the Markdown content folder under `resources/announcements/` (e.g. `terms_update`) |
| `type` | `string` | `'info'` | Display type; typically `'info'` or `'policy'` |
| `isForced` | `bool` | `false` | If true, inject into session and display immediately regardless of UI state |
| `isGlobal` | `bool` | `true` | If true, show to all users; if false, restrict to `targetUsers` |
| `targetUsers` | `array\|null` | `null` | Array of user IDs to target when `isGlobal` is false |
| `anchor` | `string\|null` | `null` | Frontend event name (e.g. `FileUpload`) — show the announcement when that interaction first happens instead of on load |
| `startsAt` | `string\|null` | `null` | ISO 8601 datetime string; null = active immediately |
| `expiresAt` | `string\|null` | `null` | ISO 8601 datetime string; null = never expires |

The `Announcement` model is stored in the `announcements` table. `target_users` is cast to an
array; `starts_at` and `expires_at` are cast to `datetime`.

---

## Markdown Content Files

Announcement content is written as Markdown files — one per configured locale, named by
its locale id. The folder name must equal the announcement's `view`:

```
resources/announcements/
└── privacy_update/       ← matches the `view` parameter
    ├── de_DE.md
    └── en_US.md
```

The `announcements` read path (`UserAnnouncementRepository::resolveContent()`) picks
the file for the current locale, falling back to the default locale, and returns the
Markdown string for the frontend to render. The first markdown heading — any level,
`#` through `######` — doubles as the display title; `[CONFIRM](label)` /
`[DECLINE](label)` tags customize the dialog buttons.

---

## Global vs Targeted Announcements

- **Global** (`is_global = true`): displayed to every user.
- **Targeted** (`is_global = false`): the `target_users` column holds a JSON array of user IDs.
  `AnnouncementService::validateUserAccess($user, $announcement)` checks whether the user's ID
  appears in this list.

The `announcement_user` pivot table tracks each user's interaction with each announcement.

---

## Forced Display (legacy UI)

In the legacy UI (`/chat`), when `is_forced` is `true` and `anchor` is `null`, the
announcement is injected into the session under the `force_announcements` key by
`AnnouncementService::getUserAnnouncements()`. The legacy frontend reads this key from
the session and displays the announcements immediately, regardless of what the user is
currently doing in the UI.

Anchored announcements mean different things per UI: the **legacy UI** attaches them to
a specific UI element instead of a modal (forced anchored ones are not injected into the
session), while the **new frontend** treats `anchor` as a frontend event name (e.g.
`FileUpload`) that triggers the announcement when that interaction first happens — see
[Ordering & Display Behavior](#ordering--display-behavior).

---

## Scheduling

`starts_at` and `expires_at` define the display window. `getActiveAnnouncements()` filters to
announcements where:

- `starts_at` is null **or** `starts_at <= now()`
- `expires_at` is null **or** `expires_at >= now()`

Expired announcements remain in the database for audit purposes. The `fetchLatestPolicy()`
convenience method returns the most recently active announcement of type `policy`.

Note the difference between the UIs: the legacy path (`unreadAnnouncements()`) excludes
expired announcements entirely, while the JSON:API path still lists them with
`is_active: false` so the new frontend can render the history.

---

## Per-User Tracking (legacy UI)

The `announcement_user` pivot table (managed by `AnnouncementUser`) records:

| Column | Type | Meaning |
|---|---|---|
| `user_id` | int | The user |
| `announcement_id` | int | The announcement |
| `seen_at` | datetime | When the user first saw the announcement |
| `accepted_at` | datetime | When the user explicitly accepted (for policy announcements) |

These columns are exposed via the `users()` BelongsToMany relationship on `Announcement` (with
`->withPivot(['seen_at', 'accepted_at'])`).

Convenience methods on the `User` model (legacy path — the JSON:API path writes the same
pivot via PATCH, see above):
- `User::markAnnouncementAsSeen(int $id)` — records `seen_at`
- `User::markAnnouncementAsAccepted(int $id)` — records `accepted_at`
- `User::unreadAnnouncements()` — returns announcements that are active and not yet
  **accepted** (expired ones excluded entirely)

`AnnouncementService` wraps these calls with access validation before updating.

---

## Artisan Commands

| Command | Purpose |
|---|---|
| `announcement:make {title}` | Scaffold per-locale Markdown files under `resources/announcements/{slug}/` |
| `announcement:publish` | Create the database entry (interactive prompts for missing options) |

See [Artisan Commands](200-Artisan-Commands.md) for full command reference.

---

## JSON:API Resource

Announcements are exposed to the **new Svelte frontend** (`/new/*`) via the `announcements`
JSON:API resource at `/api/hawki/v1/announcements`. The frontend fetches this resource to
display the announcement dialog on load (and on anchored interactions) plus the news page
("Aktuelles") history.

### PATCH semantics

The only writable state is the per-user acknowledgement (`seen_at` / `accepted_at` in the
`announcement_user` pivot). Clients send write-only boolean transition attributes; the
server stamps the timestamps, so clients can never forge them:

```json
{
    "data": {
        "type": "announcements",
        "id": "12",
        "attributes": {"seen": true, "accepted": true}
    }
}
```

- `seen: true` sets `seen_at`, `accepted: true` sets `accepted_at` (both may occur in one
  request; `accepted` is applied last).
- At least one of the two is required; non-boolean values are rejected with `422`.
- Announcements invisible to the current user are rejected with `404`.

The write path is `AnnouncementController` (`Actions\Update`) →
`App\JsonApi\V1\Announcements\AnnouncementRepository` (`HasCrudCapability`) →
`Capabilities\CrudAnnouncement` → service-layer
`App\Services\Announcements\Repositories\UserAnnouncementRepository`, guarded by
`AnnouncementPolicy::update()`.

---

## Ordering & Display Behavior

### API ordering contract

List responses are ordered **newest first** by `starts_at`, with undated announcements
(`starts_at IS NULL`) sorted **last** and the `id` as a stable tiebreaker for equal
timestamps (`UserAnnouncementRepository::queryVisibleForUser()`; covered by a feature
test). The frontend dialog queue relies on this order — changing it is a breaking change
for the UI, not an implementation detail.

### Frontend dialog queue

On load, the frontend fetches the list and queues the *pending* announcements — active,
not yet accepted, without an anchor — for display in the `AnnouncementDialog`, one at a
time:

- **Forced announcements render first** (in API order, i.e. newest first), followed by
  only the **newest** unforced pending one. Older unforced news does not block: it stays
  readable on the announcements page ("Aktuelles"). Note the consequence: once the newest
  one is accepted, the next-oldest unaccepted news item resurfaces on a later visit.
- **Confirm** accepts (`accepted_at` stamped) and advances the queue. For unforced
  announcements, Confirm is also the only permanent dismiss.
- **Dismiss** (Esc, outside click, Decline on unforced items) only removes the dialog for
  the current session — the announcement reappears on the next load until accepted.
- **Decline on a forced announcement** offers logout instead — forced consent cannot be
  skipped.
- Anchored announcements (e.g. `anchor = FileUpload`) follow the same rules, but are
  triggered by the anchored interaction (attaching a file in the chat composer) instead of
  page load.
- Displaying an announcement in the dialog stamps `seen_at` (fire-and-forget PATCH).

### Where types surface

| Type | Dialog | Announcements page |
|---|---|---|
| `policy`, `system` | yes (typically forced or anchored) | hidden |
| `news`, `event`, `info` | yes, while pending (newest only) | listed |
