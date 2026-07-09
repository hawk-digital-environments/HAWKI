# Announcement System

HAWKI's announcement system lets administrators publish notices to users — policy updates,
maintenance windows, or welcome messages — with flexible targeting, scheduling, and display
control.

:::warning[Known technical debt in AnnouncementService]
`App\Services\Announcements\AnnouncementService` currently uses `Auth::user()` (facade call),
`Session::put()` (session access from a service), and `now()` (direct time construction) — all
of which violate HAWKI's coding standards for services. This is a confirmed deviation tracked
in the [Technical Debt Register](../100-Architecture/300-Technical-Debt.md). Do not copy these
patterns; follow the standard (constructor-injected `ClockInterface`, no facades in services,
no session access) in any new code you write in this area.
:::

---

## Creating an Announcement

Call `AnnouncementService::createAnnouncement()` with these parameters:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `title` | `string` | required | Display title of the announcement |
| `view` | `string` | required | Path to the Markdown content folder under `resources/announcements/` (e.g. `terms_update`) |
| `type` | `string` | `'info'` | Display type; typically `'info'` or `'policy'` |
| `isForced` | `bool` | `false` | If true, inject into session and display immediately regardless of UI state |
| `isGlobal` | `bool` | `true` | If true, show to all users; if false, restrict to `targetUsers` |
| `targetUsers` | `array\|null` | `null` | Array of user IDs to target when `isGlobal` is false |
| `anchor` | `string\|null` | `null` | UI element selector — attach the announcement to a specific element instead of displaying as a modal |
| `startsAt` | `string\|null` | `null` | ISO 8601 datetime string; null = active immediately |
| `expiresAt` | `string\|null` | `null` | ISO 8601 datetime string; null = never expires |

Example:

```php
$service->createAnnouncement(
    title: 'Privacy Policy Update',
    view: 'privacy_update',
    type: 'policy',
    isForced: true,
    isGlobal: true,
    startsAt: '2025-09-01T00:00:00Z',
    expiresAt: '2025-09-30T23:59:59Z'
);
```

The `Announcement` model is stored in the `announcements` table. `target_users` is cast to an
array; `starts_at` and `expires_at` are cast to `datetime`.

---

## Markdown Content Files

Announcement content is written as Markdown files. Place one file per supported locale:

```
resources/announcements/
└── privacy_update/       ← matches the `view` parameter
    ├── de.md
    └── en.md
```

`AnnouncementService::renderAnnouncement($announcement)` resolves the correct language file
from the current locale and returns the Markdown string for the frontend to render.

---

## Global vs Targeted Announcements

- **Global** (`is_global = true`): displayed to every user.
- **Targeted** (`is_global = false`): the `target_users` column holds a JSON array of user IDs.
  `AnnouncementService::validateUserAccess($user, $announcement)` checks whether the user's ID
  appears in this list.

The `announcement_user` pivot table tracks each user's interaction with each announcement.

---

## Forced Display

When `is_forced` is `true` and `anchor` is `null`, the announcement is injected into the session
under the `force_announcements` key by `AnnouncementService::getUserAnnouncements()`. The
frontend reads this key from the session and displays the announcements immediately, regardless
of what the user is currently doing in the UI.

Anchored announcements (`anchor` is set) are attached to a specific UI element rather than
displayed as a modal. Forced anchored announcements are not injected into the session.

---

## Scheduling

`starts_at` and `expires_at` define the display window. `getActiveAnnouncements()` filters to
announcements where:

- `starts_at` is null **or** `starts_at <= now()`
- `expires_at` is null **or** `expires_at >= now()`

Expired announcements remain in the database for audit purposes. The `fetchLatestPolicy()`
convenience method returns the most recently active announcement of type `policy`.

---

## Per-User Tracking

The `announcement_user` pivot table (managed by `AnnouncementUser`) records:

| Column | Type | Meaning |
|---|---|---|
| `user_id` | int | The user |
| `announcement_id` | int | The announcement |
| `seen_at` | datetime | When the user first saw the announcement |
| `accepted_at` | datetime | When the user explicitly accepted (for policy announcements) |

These columns are exposed via the `users()` BelongsToMany relationship on `Announcement` (with
`->withPivot(['seen_at', 'accepted_at'])`).

Convenience methods on the `User` model:
- `User::markAnnouncementAsSeen(int $id)` — records `seen_at`
- `User::markAnnouncementAsAccepted(int $id)` — records `accepted_at`
- `User::unreadAnnouncements()` — returns announcements that are active and not yet seen

`AnnouncementService` wraps these calls with access validation before updating.

---

## Artisan Commands

| Command | Purpose |
|---|---|
| `announcement:make {title}` | Scaffold per-locale Markdown files under `resources/announcements/` |
| `announcement:publish` | Interactively persist an announcement to the database |

See [Artisan Commands](200-Artisan-Commands.md) for full command reference.

---

## JSON:API Resource

Announcements are exposed via the `announcements` JSON:API resource at
`/api/hawki/v1/announcements`. The frontend fetches this resource to display announcement
banners and policy acceptance dialogs.
