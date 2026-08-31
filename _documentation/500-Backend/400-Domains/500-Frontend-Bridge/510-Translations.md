# Translations

The single home for `LocaleService`, the `translation-labels` resource, and how to add a translation key. `trans()` / `__()` (server-side) and the JSON:API resource both draw from the same `CustomTranslator` data source — there is no duplication.

## How labels reach the frontend

Labels are **not** embedded in the connection bootstrap payload. The flow:

1. The connection payload (`GET /api/hawki/v1/connections/hawki`) delivers the current locale identifier (e.g. `"de"`) as part of the `Connection` value object. See [Connection Bootstrap](../../300-HTTP-API/200-Connection-Bootstrap.md).
2. The frontend reads this identifier.
3. At startup the frontend makes a separate call: `GET /api/hawki/v1/translation-labels/{locale}` — for example, `GET /api/hawki/v1/translation-labels/de`.
4. HAWKI returns a flat JSON:API document containing all known translation strings for that locale, served by the virtual `TranslationLabels` resource.

## `LocaleService`

`App\Services\Translation\LocaleService` (`#[Singleton]`) is the single authority on which locale is active for the current request.

On first access, `getCurrentLocale()` resolves the active locale by checking, in order: session (key `language`, set by `setCurrentLocale($locale, persist: true)`), then cookie (key `lastLanguage_cookie`, 120-day lifetime), then default (`config('app.locale')`). `Accept-Language` header negotiation is **not** part of the chain — HAWKI uses the session/cookie chain for consistency across requests from the same user.

`Locale` is a value object in `App\Services\Translation\Value\Locale` (see [Value Objects](../../200-Concepts/150-Value-Objects.md)). It carries the `lang` string (e.g. `"de"`) and is also the `AsLocale` Eloquent cast target (see [Model Casts](../../200-Concepts/160-Model-Casts/index.md)). Open the `LocaleService` class for the canonical method list (`getCurrentLocale`, `setCurrentLocale`, `getAvailableLocales`, `getDefaultLocale`, `getMostLikelyLocale`, `getLocale`).

## `TranslationLabels` JSON:API resource

The `translation-labels` resource is a **virtual, non-Eloquent** resource — no backing Eloquent model. The schema uses `GET /api/hawki/v1/translation-labels/{locale}` as the only endpoint; there are no `index`, `create`, `update`, or `delete` operations.

The response is a flat JSON:API document where each attribute is a translation key and its value is the translated string. Both Laravel's own framework messages and HAWKI's custom overrides from `resources/lang/*.json` are merged and returned together — custom keys win over Laravel's defaults.

## Adding a translation key

Translation files live in `resources/language/`. Each file is a JSON object; the **root key** in the JSON object defines the translation key namespace — the filename is irrelevant to the key structure. Files are named `{group}_{locale}.json` (e.g. `chat_en_US.json`, `ai_de_DE.json`); the main file `{locale}.json` (e.g. `en_US.json`) holds top-level keys without a namespace prefix.

1. Create or edit a `_{locale}.json` file. The root key in the JSON object defines the translation key:

   ```json
   // resources/language/my_feature_en_US.json
   {
       "my_feature": {
           "some_key": "The translated text",
           "nested": {
               "key": "Another text"
           }
       }
   }
   ```

   The translation key is `my_feature.some_key` (dot notation for nested keys). The filename `my_feature_en_US.json` is just for organisation — it does not affect the key.

2. Add matching keys to every supported locale file (`_en_US.json`, `_de_DE.json`, …). Missing keys fall through to the key string itself.

3. No class changes or cache clears are needed in development. On production, a config cache clear (`php artisan config:clear`) may be required if you are serving from a cached config.

4. Use `__('my_feature.some_key')` or `trans('my_feature.some_key')` server-side. On the frontend, the `__()` helper reads from the fetched label map.

## `CustomTranslator` and `LaravelTranslationLoaderAdapter`

`App\Services\Translation\CustomTranslator` is registered as the `translator` binding in the container, replacing Laravel's default `Translator`. The swap is transparent: all Laravel helper functions and facade calls continue to work unchanged.

`App\Services\Translation\LaravelTranslationLoaderAdapter` handles the file-loading layer. `TranslationFileLoader` reads all JSON files from `resources/language/` matching the current locale, loads the main `{locale}.json` file first, then merges all other `{group}_{locale}.json` files on top (key-wins-last). You do not interact with either class directly — they are framework wiring.

The reason HAWKI overrides the translator: the built-in translator cannot merge custom JSON files on top of Laravel's defaults, has no mechanism for exposing labels via JSON:API, and does not integrate with `LocaleService`'s resolution chain. The override solves all three while keeping `trans()` / `__()` working unchanged everywhere.

## Frontend consumer

The frontend translator lives at `resources/js/kernel/localization/translator.ts`. It exposes a `Translator` interface with `hasLabel(label)` and `__(label, replacements)` — the latter supports dot-notation keys and Laravel-compatible placeholder replacement (`:key`, `:Key`, `:KEY` variants plus function-value callbacks for `<key>content</key>` patterns). The label map is fetched via `GET /api/hawki/v1/translation-labels/{locale}` at boot time.
