# User Keychain

HAWKI's server-side key blob store. The server stores opaque encrypted values — it never decrypts them. All key management and derivation happens in the browser.

## Data model

Table `user_keychain_values` with columns `user_id`, `type`, `key`, `value`. The `value` column is stored via `AsSymmetricCryptoValueCast` — what lands in the database is the AES-256-GCM wire format (`base64(iv)|base64(tag)|base64(ciphertext)`, see [Encryption](./410-Encryption.md)). The server writes and reads these blobs without ever touching the plaintext.

`UserKeychainValueType` is the enum that distinguishes blob types: `PRIVATE_KEY` (RSA-OAEP private key, passkey-encrypted), `PUBLIC_KEY` (RSA public key, plaintext, readable by the server for validation), `ROOM` / `ROOM_AI` / `ROOM_AI_LEGACY` (per-room keys, keyed by room slug), `AI_CONV` (per-private-conversation key). The `key` column disambiguates entries of the same type. Open the enum for the canonical case list.

## REST endpoints

All under `/api/hawki/v1/user-keychain-values`:

- `GET /api/hawki/v1/user-keychain-values` — returns all keychain values for the authenticated user. `BelongsToUserScope` (a contextual scope via `HasContextualScopesTrait` — see [Contextual Scopes](../../200-Concepts/140-Contextual-Scopes.md)) ensures users only see their own records; no filter parameter needed.
- `GET /api/hawki/v1/user-keychain-values/actions/validator` — returns the user's public key entry. The frontend uses this to verify passkey ownership before committing a migration.
- `POST /api/hawki/v1/user-keychain-values/actions/batch-update` — the primary write endpoint. Accepts three optional arrays: `set` (upsert `{type, key, value}` objects), `remove` (delete `{type, key}` objects), `clean` (boolean — remove room keys for rooms the user is no longer a member of), and `newPublicKey` (replace the user's stored public key).

`UserKeychainRepository::setValues()` and `removeValues()` drive the upsert and delete, both bypassing the `access` contextual scope so the repository can write on behalf of the user without the scope filtering out the user's own records. See [Repositories](../../200-Concepts/130-Repositories.md).

## Housekeeping

`UserKeychainRepository::removeRoomKeysWithoutMembership()` is called automatically after every `removeValues()` call. It queries the user's current room memberships, then deletes any `ROOM`, `ROOM_AI`, or `ROOM_AI_LEGACY` entries whose `key` (room slug) no longer appears in that list.

A 7-day grace period applies: only entries whose `updated_at` is older than 7 days are removed. This prevents race conditions where a new key was written but room membership has not yet fully propagated.

## Domain events

`UserKeychainValue` dispatches Eloquent model events mapped to HAWKI domain events via `$dispatchesEvents`:

- `UserKeychainValueCreatedEvent` — a new keychain entry is inserted
- `UserKeychainValueUpdatedEvent` — an existing entry's `value` column changes
- `UserKeychainValueDeletingEvent` — an entry is about to be deleted

See [Events & Listeners](../../200-Concepts/170-Events-and-Listeners.md). These events are not yet marked `@api` — treat them as stable for internal listeners but subject to change for external code until v3.

## JWK-to-base64 auto-migration

Passkeys stored in the legacy JWK format (from HAWKI versions before v2.5) are automatically migrated to a base64 string format on the user's first login after the upgrade. This is implemented as a frontend migration (`after_login` run type — see [Frontend Migrations](../500-Frontend-Bridge/520-Frontend-Migrations.md)) and is non-destructive: the old JWK blob is replaced in place. No manual operator action is required.

## Frontend integration

The primary frontend consumer is `KeychainHandle` (`resources/js/kernel/keychain/keychainHandle.ts`), created via `createKeychainHandle` and wrapped reactively by `KeychainStore` (`resources/js/plugins/core/stores/KeychainStore.svelte.ts`). It fetches keychain values from the `user-keychain-values` JSON:API resource and commits writes through `runBatchUpdate` / `collectDeferredBatchUpdates` (`resources/js/kernel/keychain/batchUpdater.ts`), which batch all `set`/`remove`/`clean` operations into a single `POST .../actions/batch-update` round-trip. During frontend migrations, `doUpdatesDeferred()` stacks multiple batch-update calls into one final flush. See [Frontend Migrations](../500-Frontend-Bridge/520-Frontend-Migrations.md) for the migration lifecycle.
