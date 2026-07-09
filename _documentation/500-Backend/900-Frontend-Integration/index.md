# Frontend Integration Overview

HAWKI's backend is in a structural transition: it is shifting from a Blade-rendered MVC
application toward a pure API server for a Svelte 5 SPA. Both worlds coexist in the current
codebase. New backend work must target the API layer; Blade is read-only except for the
page shell.

This section covers the PHP-side mechanisms that connect the two layers. For the Svelte side —
boot stages, stores, component mounting — see the **Frontend** documentation section.

---

## Connection Bootstrap

The primary handshake between the backend and the frontend is `GET /api/hawki/v1/connections/hawki`.
`ConnectionFactory` (`App\Services\Frontend\Connection\ConnectionFactory`) assembles the
`Connection` value object for this endpoint. It carries:

- Current locale identifier (from `LocaleService`)
- Count of pending frontend migrations
- Version string
- User identity (`Userinfo`) and connection type

The `hawki-core` public config block — populated by `PublicConfigRegistry` — delivers all
remaining bootstrapping data in a parallel `GET /api/hawki/v1/configs` call:

| Key | Source |
|---|---|
| `locale` | `LocaleService::getCurrentLocale()` |
| `crypto_salt` | `SaltProvider` (all five typed salts) |
| `storage_files` | Allowed MIME types and size limits from `config/filesystems.php` |
| `security` | Passkey UX settings from `config/hawki.php` |
| `websocket` | Reverb host/port/scheme from `WebsocketTransferConfig` |
| `migrations_to_apply` | Count of pending migrations for the current user |
| `ai_handle` | `@hawki` mention handle from `AI_MENTION_HANDLE` env (`config/hawki.php aiHandle`) |

For the full bootstrap sequence, diagram, and `ConnectionType` enum, see
[Connection Bootstrap](../400-Connection-Bootstrap.md).

:::note[Translation labels are separate]
The connection payload carries only the current locale identifier. The frontend fetches all
translation strings via a separate call to
`GET /api/hawki/v1/translation-labels/{locale}` at startup. See
[Translation System](050-Translation-System.md).
:::

---

## TranslationServiceProvider

`App\Providers\TranslationServiceProvider` replaces Laravel's built-in translation provider.
It wires `CustomTranslator` as the `translator` binding so that `trans()` / `__()` continue to
work unchanged, while also enabling:

1. Merging HAWKI's own `resources/lang/*.json` files on top of Laravel's fallback messages.
2. Exposing all labels via the `translation-labels` JSON:API resource.
3. Integration with `LocaleService`'s locale-resolution chain.

The reason for the override is explained in detail in
[Custom Infrastructure → Pattern 3](../100-Architecture/250-Custom-Infrastructure.md).

---

## LocaleService

`App\Services\Translation\LocaleService` is a `#[Singleton]` that owns the locale-resolution
chain. Resolution order on each request:

1. Session key `language` (persisted from a previous `setCurrentLocale($locale, persist: true)` call)
2. `lastLanguage_cookie` cookie
3. `config('app.locale')` default

Call `LocaleService::getCurrentLocale()` to get the active `Locale` value object. Call
`setCurrentLocale($locale, persist: true)` to store the user's preference across requests.

The `Accept-Language` header is **not** part of the current resolution chain — HAWKI uses the
session and cookie chain instead.

---

## Svelte Snippet Bridge

The `<x-svelte>` Blade component (backed by `App\Services\Frontend\View\SvelteComponent`) emits
a `<svelte-snippet>` custom HTML element. On the browser side, `svelteSnippetLoader.ts`
discovers these elements and mounts the matching Svelte component into each one. This is the
primary mechanism for embedding Svelte UI into Blade page shells.

`LegacySharedContent.svelte` is automatically injected once per page as a page-level singleton;
it provides global UI like the notification toaster.

`OldUiBridge` is the Svelte-to-legacy-JS compatibility shim. It is a temporary construct and
will be removed when the full SPA migration is complete. New Svelte components must not
introduce new dependencies on `OldUiBridge`.

---

## EarlyFrontendBridge

`App\Services\Frontend\View\EarlyFrontendBridge` is a Blade component
(`<x-early-frontend-bridge />`) placed as early in the `<head>` as possible. It renders an
inline `<script>` that sets up `window.waitUntilReady` and `window.waitUntilBootstrap` as queue
stubs. Third-party scripts injected into the page head can call these functions before the HAWKI
JS bundle loads; the queued callbacks are handed to the real implementations once the bundle
initialises.

---

## AssetCacheBustingUrlGenerator

Non-Vite assets (fonts, legacy icons, etc.) are served with a content-hash query parameter
appended by `AssetCacheBustingUrlGenerator`. Vite-processed assets are excluded because Vite
already produces content-hashed filenames.

---

## Related Articles

- [Translation System](050-Translation-System.md) — locale resolution, adding keys, JSON:API resource
- [Frontend Migrations](100-Frontend-Migrations.md) — two-file migration pattern, run types
- [Connection Bootstrap](../400-Connection-Bootstrap.md) — full payload spec and boot diagram
