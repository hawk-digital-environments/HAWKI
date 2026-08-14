# Roadmap

Transitional scaffolding that exists only for as long as the old Blade + vanilla-JS UI ships alongside the new Svelte SPA. Both systems here are `@deprecated` on purpose — they are meant to be deleted, not extended. The next release removes them.

| Page | What it covers |
|---|---|
| [Legacy UI Bridge](100-Legacy-UI-Bridge.md) | `OldUiBridge`, `OldUiMessageHistory`, the `window.*` globals, and the lazy dependency loader — the typed channel between new Svelte code and legacy JS. |
| [Snippet System](200-Snippet-System.md) | The `<svelte-snippet>` custom element, the `SnippetExtension`/`LegacyToastExtension`, and the `legacyInitializeSnippetApps` fallback — the Blade-to-Svelte bridge the SPA shell replaces. |

Everything in this section is read-only for new work. New Svelte code must not read from `window.*` — import the real modules and use the hooks instead. New pages must mount via the SPA shell (`#hawki-app` + `RouterView`), not via snippets.

For the not-yet-implemented plugin-system design (third-party runtime-installed plugins), see the backend roadmap: [`../../500-Backend/700-Roadmap/100-Plugin-System.md`](../../500-Backend/700-Roadmap/100-Plugin-System.md).
