# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

- `php hawki init` and `php hawki run -build` no longer run an unattended `composer update` when outdated packages are detected. Setup and build now only perform a deterministic `composer install`; upgrading packages is a deliberate decision. Previously the silent update could jump packages across major versions (e.g. `laravel/ai` 0.10 → 1.3), breaking the app with errors that first surfaced in unrelated commands like `models sync`.
- Self-service access token creation was unavailable in the profile view: it still checked the pre-2.5.0 `sanctum.*` config keys instead of the new `external_access.*` keys, so the AccessTokens panel never appeared even with `ALLOW_EXTERNAL_COMMUNICATION` and `ALLOW_USER_TOKEN_CREATION` enabled.
- Model enrichment (pricing, context windows, feature flags) stopped updating because LiteLLM discontinued their catalog API. Lookups always fell back to the static files. The catalog is now read from LiteLLM's GitHub file.
- Fixed attachments never reaching the AI conversation when `STORAGE_DISK` uses an s3-compatible server (e.g. garage, MinIO): uploaded files were never persisted from the temp area because whole "directory" moves are silently ignored by s3 drivers, so the attachment was never linked to the message. Each file is now copied individually instead.
- The `s3` disk in `config/filesystems.php` now fails loudly on storage errors and works on s3 servers without ACL support (see Internals section for details).
- The `check:storage` artisan command now respects the configured `S3_ENDPOINT` and path-style addressing instead of always probing `amazonaws.com`.

### Internals

- The `laravel/ai` version constraint is now `^0.10.1` (was `>=0.10.1`), so dependency updates stay within semver-compatible releases instead of silently allowing a major jump. Lock file refreshed to `laravel/ai` v0.10.3.
- LiteLLM catalog: downloaded once into a shared cache and filtered per provider, kept fresh via ETag revalidation every 5 minutes. During upstream outages cached data is served for up to 24 h, then the static files take over.
- The `static_llm_data` updater no longer deletes the old files when the catalog is unreachable.
- **Storage: driver-agnostic file persistence** (`app/Services/Storage/AbstractFileStorage.php`): `persistTemporaryFile` previously moved the whole temp *directory*, which only works on local disks (via `rename()`); s3-style drivers treat directories as key prefixes and silently ignore such moves. It now copies each file individually (`allFiles()` + `copy()` + one `deleteDirectory()`), which Laravel's contract guarantees for every driver — a server-side `CopyObject` on s3, a native copy on local disks.
- **Storage: hardened s3 disk config** (`config/filesystems.php`): `throw => true` turns failed Flysystem operations into logged exceptions instead of unchecked `false` returns (the reason the original bug stayed invisible); `retain_visibility => false` skips the `GetObjectAcl` pre-check before each `copy()`, which ACL-less s3 servers (garage, Cloudflare R2) reject.
- **Storage: `check:storage` now honors custom s3 endpoints** (`app/Console/Commands/CheckStorageConnection.php`) by forwarding the disk's `endpoint`/`use_path_style_endpoint` to its `S3Client` instead of always probing `amazonaws.com`.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
