# Announcements

HAWKI's announcement system lets administrators publish notices to users — policy updates, maintenance windows, welcome messages — with flexible targeting, scheduling, and display control.

## Creating an announcement

Call `AnnouncementService::createAnnouncement()`. Open the service class for the canonical parameter list; the load-bearing ones:

- `title` (required) — display title
- `view` (required) — path to the Markdown content folder under `resources/announcements/` (e.g. `terms_update`)
- `type` (default `'info'`) — typically `'info'` or `'policy'`
- `isForced` (default `false`) — inject into session and display immediately regardless of UI state
- `isGlobal` (default `true`) — show to all users; when `false`, restrict to `targetUsers`
- `targetUsers` (default `null`) — array of user IDs to target when `isGlobal` is `false`
- `anchor` (default `null`) — UI element selector; attach the announcement to a specific element instead of displaying as a modal
- `startsAt` / `expiresAt` (default `null`) — ISO 8601 datetime strings; `null` means active immediately / never expires

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

:::warning[Known technical debt in AnnouncementService]
`AnnouncementService` currently uses `Auth::user()` (facade call), `Session::put()` (session access from a service), and `now()` (direct time construction) — all of which violate HAWKI's coding standards for services. This is a confirmed deviation tracked in the [Technical Debt Register](../900-Technical-Debt.md). Do not copy these patterns; follow the standard (constructor-injected `CarbonClockInterface`, no facades in services, no session access) in any new code you write here.
:::

## Markdown content files

Announcement content is Markdown, one file per supported locale:

```
resources/announcements/
└── privacy_update/       ← matches the `view` parameter
    ├── de.md
    └── en.md
```

`AnnouncementService::renderAnnouncement($announcement)` resolves the correct language file from the current locale and returns the Markdown string for the frontend to render. See [Translations](./500-Frontend-Bridge/510-Translations.md) for the locale resolution chain.

## Global vs targeted

- **Global** (`is_global = true`): displayed to every user.
- **Targeted** (`is_global = false`): the `target_users` column holds a JSON array of user IDs. `AnnouncementService::validateUserAccess($user, $announcement)` checks whether the user's ID appears in this list.

The `announcement_user` pivot table tracks each user's interaction with each announcement: `seen_at` (when the user first saw it) and `accepted_at` (when the user explicitly accepted, for policy announcements).

## Forced display and anchoring

When `is_forced` is `true` and `anchor` is `null`, the announcement is injected into the session under the `force_announcements` key by `AnnouncementService::getUserAnnouncements()`. The frontend reads this key from the session and displays the announcements immediately, regardless of what the user is currently doing. Anchored announcements (`anchor` is set) are attached to a specific UI element rather than displayed as a modal. Forced anchored announcements are not injected into the session.

## Scheduling

`starts_at` and `expires_at` define the display window. `getActiveAnnouncements()` filters to announcements where `starts_at` is null or `<= now()`, and `expires_at` is null or `>= now()`. Expired announcements remain in the database for audit purposes. `fetchLatestPolicy()` returns the most recently active announcement of type `policy`.

## Per-user tracking

The `announcement_user` pivot (managed by `AnnouncementUser`) records `user_id`, `announcement_id`, `seen_at`, `accepted_at`. Convenience methods on the `User` model: `markAnnouncementAsSeen(int $id)`, `markAnnouncementAsAccepted(int $id)`, `unreadAnnouncements()` (returns active announcements not yet seen). `AnnouncementService` wraps these calls with access validation before updating.

## Artisan commands

`announcement:make {title}` (scaffold per-locale Markdown files under `resources/announcements/`) and `announcement:publish` (interactively persist an announcement to the database). See [Artisan Commands](../500-Reference/100-Artisan-Commands.md).

## JSON:API resource

Announcements are exposed via the `announcements` JSON:API resource at `/api/hawki/v1/announcements`. The frontend fetches this resource to display announcement banners and policy acceptance dialogs. Note: `announcements` is currently served through the legacy web routes (`routes/web.php`), not through the JSON:API v1 server.
