# Translations

Translation labels are loaded from the server during bootstrap and accessed through three functions exported from `$lib/utils/translator.js`. Labels match the user's current locale automatically — no locale argument is needed at the call site.

---

## `__(label, replacements?, ignoreMissing?)`

The primary translation function. Looks up `label` in the loaded label set and returns the localised string.

```ts
import { __ } from '$lib/utils/translator.js';

__('conversation.title')           // → 'Conversation'
__('errors.not_found')             // → 'The requested item was not found.'
```

**Dot notation** resolves nested label keys:

```ts
__('composer.placeholder')         // → resolves labels.composer.placeholder
```

When a key is not found, `__()` logs a console warning and returns `"Missing translation: {label}"` — making missing keys visible during development without throwing. Pass `ignoreMissing: true` to return an empty string silently instead:

```ts
__('optional.badge', undefined, true)   // → '' when key is absent
```

### String Replacements

Pass a `replacements` object to substitute `:placeholder` tokens in the label. Three case variants are substituted simultaneously, mirroring Laravel's `Translator::makeReplacements()`:

| Token in label | Substituted value |
|---|---|
| `:name` | value as-is |
| `:Name` | value with first letter uppercased |
| `:NAME` | value fully uppercased |

```ts
// Label: "Welcome, :Name! You have :count messages."
__('welcome', { name: 'alice', count: '3' })
// → 'Welcome, Alice! You have 3 messages.'
```

### Function Replacements

When a replacement value is a function, every `<key>content</key>` occurrence in the label is replaced by calling the function with the inner content. Use this to wrap a portion of a translated string in an HTML element or Svelte component:

```ts
// Label: "Please read our <link>terms of service</link>."
__('terms_notice', {
    link: (text) => `<a href="/terms">${text}</a>`
})
// → 'Please read our <a href="/terms">terms of service</a>.'
```

---

## `hasTranslation(label)`

Returns `true` when a label key exists in the loaded label set. Use this to conditionally render optional UI elements without relying on an empty-string check:

```ts
import { hasTranslation } from '$lib/utils/translator.js';

if (hasTranslation('feature.beta_badge')) {
    // render badge
}
```

---

## `getTranslations(label, replacements?, ignoreMissing?)`

Like `__()` but allows returning non-string values. When the resolved label is an object (a nested section of the label tree), it is returned as-is. Replacement substitution only applies when the result is a string.

```ts
import { getTranslations } from '$lib/utils/translator.js';

// Returns the entire 'errors' sub-object if that key maps to a nested section
const allErrors = getTranslations('errors');
```

Prefer `__()` for string labels. Use `getTranslations()` only when you specifically need to iterate over a label section.

---

## `getTranslationsFlat(path)`

Like `getTranslations()`, but flattens a nested label sub-tree into a single-level `Record<string, string>` with dot-notated keys. Requires `path` to resolve to an object — throws if it points to a string or any non-object value.

```ts
import { getTranslationsFlat } from '$lib/utils/translator.js';

// Given labels: { markdown: { markstream: { copy: 'Copy', copied: 'Copied!' } } }
getTranslationsFlat('markdown.markstream');
// → { 'copy': 'Copy', 'copied': 'Copied!' }
```

Returns `{}` with a console warning when labels are not yet loaded or the path is not found. Use this when a third-party library expects a flat key/value map of strings rather than a nested object — the `Markdown` component uses it to pass localised strings to `markstream-svelte`.

---

## Label Files

Translation labels are served by the `translation-labels` API resource and loaded automatically during the `main` boot stage. The active locale comes from the connection object; if it fails to load, the system falls back to the configured default locale, and then to an empty label set. No manual setup is required — `__()` is available in any component or utility after bootstrap.

Labels live on the server side under `resources/lang/`. To add a new label, add the key to the appropriate language file there.
