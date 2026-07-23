# Translation System

:::note[Why HAWKI overrides the translator]
For the architectural reasons behind the `TranslationServiceProvider` override (the three
problems Laravel's built-in translator doesn't solve), see
[Custom Infrastructure → Pattern 3](../100-Architecture/250-Custom-Infrastructure.md). This
article covers the operational details: how labels reach the frontend, how to add a key, and
what `LocaleService` exposes.
:::

---

## How Translation Labels Reach the Frontend

Labels are **not** embedded in the connection bootstrap payload. The flow is:

1. The connection payload (`GET /api/hawki/v1/connections/hawki`) delivers the current locale
   identifier (e.g. `"de"`) as part of the `Connection` value object.
2. The frontend reads this identifier from the connection.
3. At startup the frontend makes a separate call:
   `GET /api/hawki/v1/translation-labels/{locale}` — for example,
   `GET /api/hawki/v1/translation-labels/de`.
4. HAWKI returns a flat JSON:API document containing all known translation strings for that
   locale, served by the virtual `TranslationLabels` resource.

Both `trans()` / `__()` (used server-side in Blade) and the JSON:API resource draw from the
same underlying `CustomTranslator` data source. There is no duplication.

---

## LocaleService

**Class:** `App\Services\Translation\LocaleService` (`#[Singleton]`)

`LocaleService` is the single authority on which locale is active for the current request.

### Resolution chain

On first access, `getCurrentLocale()` resolves the active locale by checking in order:

| Priority | Source | Detail |
|---|---|---|
| 1 | Session | Key `language` (set by `setCurrentLocale($locale, persist: true)`) |
| 2 | Cookie | Key `lastLanguage_cookie` (120-day lifetime) |
| 3 | Default | `config('app.locale')` |

`Accept-Language` header negotiation is **not** part of the current chain. HAWKI uses the
session/cookie chain for consistency across requests from the same user.

### Key methods

| Method | Returns | Purpose |
|---|---|---|
| `getCurrentLocale()` | `Locale` | Active locale for this request |
| `setCurrentLocale($locale, persist: bool)` | void | Switch locale; optionally persist to session + cookie |
| `getAvailableLocales()` | `Locale[]` | All configured and active locales |
| `getDefaultLocale()` | `Locale` | The fallback locale (`config('app.locale')`) |
| `getMostLikelyLocale($locale)` | `Locale` | Resolve a string/Locale to the best match; falls back to current locale |
| `getLocale($id)` | `Locale\|null` | Look up a specific locale by ID string |

`Locale` is a value object in `App\Services\Translation\Value\Locale`. It carries the `lang`
string (e.g. `"de"`) and is also used as the `AsLocale` Eloquent cast target.

---

## TranslationLabels JSON:API Resource

The `translation-labels` resource is a **virtual, non-Eloquent** resource — it has no backing
Eloquent model. The schema uses `GET /api/hawki/v1/translation-labels/{locale}` as the only
endpoint; there are no `index`, `create`, `update`, or `delete` operations.

The response is a flat JSON:API document where each attribute is a translation key and its value
is the translated string. Both Laravel's own framework messages and HAWKI's custom overrides
from `resources/lang/*.json` are merged and returned together — custom keys win over Laravel's
defaults.

---

## Adding a New Translation Key

1. Drop a `.json` file (or add a key to an existing one) under `resources/lang/{locale}/`.
   The filename groups related keys by feature; no single monolithic file is required.

   ```
   resources/lang/
   ├── de/
   │   ├── chat.json       ← existing file for chat strings
   │   └── my_feature.json ← new file
   └── en/
       ├── chat.json
       └── my_feature.json
   ```

2. Add matching keys to every supported locale file. Missing keys fall through to Laravel's
   built-in messages; if those also don't exist, the key string itself is returned.

3. No class changes or cache clears are needed in development. On production, a config cache
   clear (`php artisan config:clear`) may be required if you are serving from a cached config.

4. Use `__('my_feature.some_key')` or `trans('my_feature.some_key')` in server-side Blade.
   On the frontend, the `__()` helper from `resources/js/utils/translator.ts` reads from the
   fetched label map.

---

## CustomTranslator and LaravelTranslationLoaderAdapter

`App\Services\Translation\CustomTranslator` is registered as the `translator` binding in the
container, replacing Laravel's default `Translator`. The swap is transparent: all Laravel
helper functions and facade calls continue to work unchanged.

`App\Services\Translation\LaravelTranslationLoaderAdapter` handles the file-loading layer. It
reads HAWKI's `resources/lang/` files and merges them on top of Laravel's own message files
using the same key-wins-last strategy. You do not interact with either class directly — they
are framework wiring.

---

## Frontend Consumer

On the frontend, `resources/js/utils/translator.ts` exposes the `__()` helper that the Svelte
components call for all localised strings. It reads from the label map fetched via
`GET /api/hawki/v1/translation-labels/{locale}` at boot time. For Svelte component usage
conventions, see the frontend **Translation** page in the Frontend documentation section.
