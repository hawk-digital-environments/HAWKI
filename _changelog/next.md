# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

- `php hawki init` and `php hawki run -build` no longer run an unattended `composer update` when outdated packages are detected. Setup and build now only perform a deterministic `composer install`; upgrading packages is a deliberate decision. Previously the silent update could jump packages across major versions (e.g. `laravel/ai` 0.10 → 1.3), breaking the app with errors that first surfaced in unrelated commands like `models sync`.
- Self-service access token creation was unavailable in the profile view: it still checked the pre-2.5.0 `sanctum.*` config keys instead of the new `external_access.*` keys, so the AccessTokens panel never appeared even with `ALLOW_EXTERNAL_COMMUNICATION` and `ALLOW_USER_TOKEN_CREATION` enabled.
- Model enrichment (pricing, context windows, feature flags) stopped updating because LiteLLM discontinued their catalog API. Lookups always fell back to the static files. The catalog is now read from LiteLLM's GitHub file.

### Internals

- The `laravel/ai` version constraint is now `^0.10.1` (was `>=0.10.1`), so dependency updates stay within semver-compatible releases instead of silently allowing a major jump. Lock file refreshed to `laravel/ai` v0.10.3.
- LiteLLM catalog: downloaded once into a shared cache and filtered per provider, kept fresh via ETag revalidation every 5 minutes. During upstream outages cached data is served for up to 24 h, then the static files take over.
- The `static_llm_data` updater no longer deletes the old files when the catalog is unreachable.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
