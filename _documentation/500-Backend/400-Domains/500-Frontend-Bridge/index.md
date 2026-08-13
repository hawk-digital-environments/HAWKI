# Frontend Bridge

Backend mechanisms that bridge to the Svelte SPA. Most of the legacy bridge (`OldUiBridge`, the Svelte snippet bridge, `AssetCacheBusting`) is deprecated transition scaffolding — see [Temporary Constructs](../../700-Roadmap/200-Temporary-Constructs.md). The two pieces that are not deprecated:

- [Translations](./510-Translations.md) — `LocaleService`, the `translation-labels` resource, adding a translation key.
- [Frontend Migrations](./520-Frontend-Migrations.md) — the two-file pattern for transforming encryption-protected data the server cannot read.
