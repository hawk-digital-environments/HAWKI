# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)
- The same model can now be offered by several providers (e.g. `gemma-4-31b-it` on GWDG and Google). Models are identified by `<provider_id>/<model_id>`; previously the second provider's entry failed to sync.

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

- `php hawki init` and `php hawki run -build` no longer run an unattended `composer update` when outdated packages are detected. Setup and build now only perform a deterministic `composer install`; upgrading packages is a deliberate decision. Previously the silent update could jump packages across major versions (e.g. `laravel/ai` 0.10 → 1.3), breaking the app with errors that first surfaced in unrelated commands like `models sync`.
- Self-service access token creation was unavailable in the profile view: it still checked the pre-2.5.0 `sanctum.*` config keys instead of the new `external_access.*` keys, so the AccessTokens panel never appeared even with `ALLOW_EXTERNAL_COMMUNICATION` and `ALLOW_USER_TOKEN_CREATION` enabled.
- **Security:** Fixed a privilege escalation in group chat invitations. Any room member (including viewers) could create an invitation for themselves with the `admin` role and accept it to become a room admin, or overwrite other users' pending invitations. Sending invitations is now restricted to room admins, invitation roles are validated, and accepting an invitation no longer changes the role of an existing member. Thanks to [Artur Chlebicki](https://github.com/Artur12555) for responsibly reporting this issue!
- The room controls are now fully restricted for room admins.
- Removing or leaving a room now deletes the member's pending invitations for that room, and leaving a room no longer fails halfway.
- An admin leaving a room no longer deletes the whole room when other admins remain. The last admin of a room can't leave it anymore, only delete it.
- Members can no longer change their own role, and the role of HAWKI can't be changed. Members can only be added to a room through invitations.
- Model enrichment (pricing, context windows, feature flags) stopped updating because LiteLLM discontinued their catalog API. Lookups always fell back to the static files. The catalog is now read from LiteLLM's GitHub file.
- Fixed attachments never reaching the AI conversation when `STORAGE_DISK` uses an s3-compatible server (e.g. garage, MinIO): uploaded files were never persisted from the temp area because whole "directory" moves are silently ignored by s3 drivers, so the attachment was never linked to the message. Each file is now copied individually instead.
- The `s3` disk in `config/filesystems.php` now fails loudly on storage errors and works on s3 servers without ACL support (see Internals section for details).
- The `check:storage` artisan command now respects the configured `S3_ENDPOINT` and path-style addressing instead of always probing `amazonaws.com`.
- Tool-call progress never reached the UI during streaming because the stream handler matched the tool-call *data* class instead of the stream *event*. Tool calls are now reported for every provider as `tool_call`/`tool_result` packets.
- MCP tools failed because processes were decoupled using `autoSse=true` (the default). New default is `autoSse=false`. 
- Per-provider AI request timeouts via the `adapter` settings in `model_providers.php`: `request_timeout` (default 60 seconds, `0` = no limit) limits the complete answer of non-streaming requests. `stall_timeout` (default off) aborts a stream that receives no data for this many seconds, before or mid-stream — like HAWKI 2.4, streams are never cut off while they receive data, and silent streams are no longer aborted after 60 seconds. Supported drivers for stall detection: `openai-compatible`, `ollama`. GWDG ships with the HAWKI 2.4 behaviour (`request_timeout=0`, `stall_timeout=120`, via `GWDG_REQUEST_TIMEOUT`/`GWDG_STALL_TIMEOUT`).
- Large chat messages fail to encrypt with a `RangeError: too many arguments provided for a function call`: the client-side encryption encoded the ciphertext with one `String.fromCharCode.apply()` over every byte, which exceeds the browser's argument limit at roughly 100k bytes. Base64 conversion now runs in 32k chunks, in both the encryption utils and the legacy bundle.
- Multiple Mermaid diagram previews failed to render at the same time: generated diagrams could receive duplicate render IDs, causing the renderer to silently draw only one and skip the rest. Fixed upstream in `markstream` 2.0.15.

### Internals

[//]: # (- Changes that are mostly relevant to maintainers and contributors, such as refactors, dependency updates, CI changes, etc.)
- `ai_models.model_id` is now unique per provider instead of globally; `system_models` references models via the new `ai_model_id` column, `system_models.model_id` was removed.
- Inside HAWKI a model is only referenced by its provider-qualified identifier `<provider_id>/<model_id>` (or its numeric id). This includes the JSON:API `model_id` of `ai-models` and `system-models`, the `system-models` `model_id` filter, chat requests on `/req/streamAI`, and the `model` stored with messages and usage records.
- A migration converts the stored model references (`ai_conv_msgs.model`, `messages.model`, `usage_records.model`) to the qualified identifier. References to models that no longer exist are left unchanged.
- The external app API (`/api/ai-req`) still accepts a plain `model_id` as long as only one provider offers it; otherwise the request is rejected with `400` and asks the client to use the `<provider_id>/<model_id>` format. A model that does not exist is rejected with `400` as well.
- New `ModelFinder` (`App\Services\Ai\Models\Identifiers\ModelFinder`) resolves model references, `AiModelIdentifier` builds and parses the qualified form.
- Provider keys in `model_providers.php` must not contain `/`, the config sync reports an error for them.
- The model lists (`config/model_lists/*.php`) keep their format; the config sync combines the provider key with the model ID.
- System models in `model_providers.php` (`system_models`, `default_models`, and the `*_MODEL` env variables) keep their format and also accept `<provider_id>/<model_id>`. If a plain model ID is offered by multiple providers, the config sync uses the model that was created first and logs a warning recommending the qualified ID (e.g. `openAi/gpt-5-nano`).
- `ai:tools:assign --model` and `usage:top-users --model` accept the qualified identifier; a plain model ID matches the model of every provider offering it.
- Breaking for custom code: `AiModelRepository::findOne()` / `findOneOrFail()` and `ModelFinder::find()` no longer resolve a plain `model_id`, pass `<provider_id>/<model_id>` or use `AiModelRepository::findOneByProvider()`. `SystemModel::$model_id` was removed, use `ai_model_id` / `SystemModel::model()`.
- The `laravel/ai` version constraint is now `^0.10.1` (was `>=0.10.1`), so dependency updates stay within semver-compatible releases instead of silently allowing a major jump. Lock file refreshed to `laravel/ai` v0.10.3.
- LiteLLM catalog: downloaded once into a shared cache and filtered per provider, kept fresh via ETag revalidation every 5 minutes. During upstream outages cached data is served for up to 24 h, then the static files take over.
- The `static_llm_data` updater no longer deletes the old files when the catalog is unreachable.
- Added `RoomRepository` and `InvitationRepository` (`App\Services\Chat\Room\Repositories`). Room and invitation queries in `RoomService`, `InvitationController`, `StreamController`, the `roomAdmin`/`roomEditor` middlewares, the room broadcast channel and the `migrate:avatars` command now go through these repositories instead of Eloquent statics.
- Replaced the unused `POST /req/room/addMember/{slug}` endpoint, which could add members without a room key, with `POST /req/room/changeMemberRole/{slug}` (route name `web.roomEditMember` unchanged).
- **Storage: driver-agnostic file persistence** (`app/Services/Storage/AbstractFileStorage.php`): `persistTemporaryFile` previously moved the whole temp *directory*, which only works on local disks (via `rename()`); s3-style drivers treat directories as key prefixes and silently ignore such moves. It now copies each file individually (`allFiles()` + `copy()` + one `deleteDirectory()`), which Laravel's contract guarantees for every driver — a server-side `CopyObject` on s3, a native copy on local disks.
- **Storage: hardened s3 disk config** (`config/filesystems.php`): `throw => true` turns failed Flysystem operations into logged exceptions instead of unchecked `false` returns (the reason the original bug stayed invisible); `retain_visibility => false` skips the `GetObjectAcl` pre-check before each `copy()`, which ACL-less s3 servers (garage, Cloudflare R2) reject.
- **Storage: `check:storage` now honors custom s3 endpoints** (`app/Console/Commands/CheckStorageConnection.php`) by forwarding the disk's `endpoint`/`use_path_style_endpoint` to its `S3Client` instead of always probing `amazonaws.com`.
- `AbstractLaravelAgent` passes the timeout explicitly to the Laravel AI package: `send()` the request timeout, `sendStreaming()` the stall timeout (or no limit when stall detection is off). The new `AgentTimeouts` value object reads and validates both values. For streamed requests, Guzzle uses its PHP stream handler, where `timeout` is a per-read timeout and curl options are ignored.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
