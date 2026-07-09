# User Keychain

The user keychain is HAWKI's server-side key blob store. The server stores opaque encrypted
values — it never decrypts them. All key management and derivation happens in the browser.

---

## Data Model

**Table:** `user_keychain_values`

**Columns:** `user_id`, `type`, `key`, `value`

The `value` column is stored using the `AsSymmetricCryptoValueCast` cast, so what lands in the
database is the AES-256-GCM wire format (`base64(iv)|base64(tag)|base64(ciphertext)`). The
server writes and reads these blobs without ever touching the plaintext.

### UserKeychainValueType Enum

| Enum case | String value | Stores |
|---|---|---|
| `PRIVATE_KEY` | `private_key` | User's RSA-OAEP private key, encrypted with the passkey-derived key |
| `PUBLIC_KEY` | `public_key` | User's RSA public key (plaintext, readable by the server for validation) |
| `ROOM` | `room_key` | Symmetric room key for a specific room (keyed by room slug) |
| `ROOM_AI` | `room_ai` | AI-layer derived key for a room (current format) |
| `ROOM_AI_LEGACY` | `room_ai_legacy` | AI-layer key in the older pre-v2.5 format |
| `AI_CONV` | `ai_conv` | Symmetric key for a private AI conversation |

The `key` column disambiguates entries of the same type: for `ROOM` / `ROOM_AI` / `ROOM_AI_LEGACY`
it holds the room slug; for other types it is a fixed identifier.

---

## REST Endpoints

All keychain endpoints are under `/api/hawki/v1/user-keychain-values`.

### `GET /api/hawki/v1/user-keychain-values`

Returns all keychain values that belong to the authenticated user. The `BelongsToUserScope`
applied by `UserKeychainValue`'s `HasContextualScopesTrait` ensures users can only see their
own records — there is no filter parameter needed.

### `GET /api/hawki/v1/user-keychain-values/actions/validator`

Returns the user's public key entry (type `public_key`). The frontend uses this to verify
passkey ownership before committing a migration.

### `POST /api/hawki/v1/user-keychain-values/actions/batch-update`

The primary write endpoint. Accepts a JSON body with three optional arrays:

| Field | Type | Effect |
|---|---|---|
| `set` | Array of `{type, key, value}` objects | Upsert: create if absent, update if present |
| `remove` | Array of `{type, key}` objects | Delete the matching entries |
| `clean` | Boolean | If `true`, remove room keys for rooms the user is no longer a member of |
| `newPublicKey` | String or null | If set, replace the user's stored public key |

`UserKeychainRepository::setValues()` and `removeValues()` drive the upsert and delete
operations respectively, both bypassing the `access` contextual scope so the repository can
write on behalf of the user without the scope filtering out the current user's own records.

---

## Housekeeping: `removeRoomKeysWithoutMembership()`

`UserKeychainRepository::removeRoomKeysWithoutMembership()` is called automatically after every
`removeValues()` call. It queries the user's current room memberships, then deletes any
`ROOM`, `ROOM_AI`, or `ROOM_AI_LEGACY` entries whose `key` (room slug) no longer appears in
that list.

A **7-day grace period** applies: only entries whose `updated_at` is older than 7 days are
removed. This prevents race conditions where a new key was written but room membership has not
yet fully propagated.

---

## Domain Events

`UserKeychainValue` dispatches Eloquent model events that are mapped to HAWKI domain events in
`$dispatchesEvents`. Plugin authors and internal listeners can hook into these via the standard
event auto-discovery mechanism in `app/Services/*/Listeners/`:

| Event class | Fires when |
|---|---|
| `App\Services\Users\Events\UserKeychainValueCreatedEvent` | A new keychain entry is inserted |
| `App\Services\Users\Events\UserKeychainValueUpdatedEvent` | An existing entry's `value` column changes |
| `App\Services\Users\Events\UserKeychainValueDeletingEvent` | An entry is about to be deleted |

:::note[API stability]
These events are not yet marked `@api`. They will receive the `@api` annotation before
becoming part of the official plugin extension surface in v3. Until then, treat them as stable
for internal listeners but subject to change for external code.
:::

---

## JWK-to-Base64 Auto-Migration

Passkeys stored in the legacy JWK format (from HAWKI versions before v2.5) are automatically
migrated to a base64 string format on the user's first login after the upgrade. This migration
is implemented as a frontend migration (`after_login` run type) and is non-destructive: the old
JWK blob is replaced in place. No manual operator action is required.

---

## Frontend Integration Notes

The primary frontend consumer is `KeychainHandle` in `resources/js/data/keychain/`. It calls:

- `GET /user-keychain-values` to hydrate the `KeychainStore` on startup.
- `POST .../actions/batch-update` during `after_passkey` frontend migrations to commit
  re-encrypted blobs in a single round-trip via `doUpdatesDeferred()`.

The `ctx.data` available inside a JS migration function is the serialised return value of the
PHP `userDataFinder` closure registered in the corresponding PHP migration file. See
[Frontend Migrations](../900-Frontend-Integration/100-Frontend-Migrations.md) for the full
data flow.

---

## Plugin Extension Point

This domain is a future plugin extension point. See
[Plugin System Preview](../1000-Infrastructure/100-Plugin-System-Preview.md) for the full
picture of v3 plugin hooks.
