# Encryption & Security Overview

HAWKI operates a client-first encryption model: all personally identifiable message content and
cryptographic keys are encrypted in the browser before they reach the server. The server stores
ciphertext blobs and distributes salts — it never holds plaintext keys or decrypted content.

This article covers the cryptographic architecture and the classes that implement it on the
backend. For the browser-side implementation of `deriveKey()`, symmetric helpers, and the
`KeychainHandle` store, see the frontend **Encryption** page.

---

## Three-Tier Cryptographic System

HAWKI uses three complementary encryption modes, each chosen for its role in the data flow.

### Tier 1 — Symmetric (AES-256-GCM)

Used for bulk data: room messages, AI-conversation messages, and the keychain itself.

- Algorithm: AES-256-GCM with a random 12-byte IV per operation.
- Authentication tag verifies ciphertext integrity on every decrypt.
- Wire format serialised by `SymmetricCryptoValue`:

  ```
  base64(iv) | base64(tag) | base64(ciphertext)
  ```

  The three segments are joined by the `|` character. Both the backend `AsSymmetricCryptoValueCast`
  Eloquent cast and the frontend `symmetric.ts` helpers read and write this exact format.

### Tier 2 — Asymmetric (RSA-OAEP-4096)

Used for key distribution: encrypting a symmetric key so that only one specific recipient can
unwrap it.

- Algorithm: RSA-OAEP with a 4096-bit keypair. Each user holds one keypair.
- Public keys are stored in the `user_keychain_values` table (type `public_key`) and on the
  `users` record.
- Private keys never leave the browser in plaintext; the server stores only the passkey-encrypted
  blob.

### Tier 3 — Hybrid (AES key + RSA wrapping)

Used for large data that only the server needs to decrypt — for example, ext-app secrets that the
server must later retrieve. A random AES key encrypts the payload; that AES key is then wrapped
with an RSA public key.

Wire format serialised by `HybridCryptoValue`:

```
base64(encryptedAesKey) | base64(symmetricPayload)
```

The `AsHybridCryptoValueCast` Eloquent cast and the backend `HybridCrypto` class both expect
this layout.

---

## SaltProvider

`App\Services\Encryption\SaltProvider` is the single source for all server-side salts. The
frontend fetches salts through the connection bootstrap payload (the `crypto_salt` block inside
`hawki-core` config) — there is no dedicated salt HTTP endpoint.

### SaltType enum

| Enum case | Env variable | Purpose |
|---|---|---|
| `USERDATA` | `USERDATA_ENCRYPTION_SALT` | Key derivation for user keychain data |
| `INVITATION` | `INVITATION_SALT` | Invitation payload encryption |
| `AI` | `AI_CRYPTO_SALT` | AI message key derivation (room AI layer) |
| `PASSKEY` | `PASSKEY_SALT` | Passkey/backup derivation |
| `BACKUP` | `BACKUP_SALT` | Passkey backup storage |

Call `SaltProvider::getSalt(SaltType $type)` or the typed convenience methods
(`getSaltForUserDataEncryption()`, `getSaltForPasskey()`, etc.) to retrieve a `Salt` value
object.

### Configuration

Salts are read from `config/encryption.php`, which maps each `SaltType` value to the
corresponding env variable:

```php
'salts' => [
    'USERDATA_ENCRYPTION_SALT' => env('USERDATA_ENCRYPTION_SALT', null),
    'INVITATION_SALT'          => env('INVITATION_SALT', null),
    'AI_CRYPTO_SALT'           => env('AI_CRYPTO_SALT', null),
    'PASSKEY_SALT'             => env('PASSKEY_SALT', null),
    'BACKUP_SALT'              => env('BACKUP_SALT', null),
]
```

:::danger[Production salts must be pre-configured]
When a salt env variable is missing or empty, `SaltProvider` falls back to
`hash('sha256', $appKey . 'semi_static_salt' . hash('sha256', $type->value))`. This fallback is
**development-only** — it is tied to `APP_KEY` and predictable to anyone who obtains it.

All five salt variables (`USERDATA_ENCRYPTION_SALT`, `INVITATION_SALT`, `AI_CRYPTO_SALT`,
`PASSKEY_SALT`, `BACKUP_SALT`) **must be set to random, independent values in
`_docker_production/.env` before the very first `php artisan migrate` run**. Re-seeding salts
after migration will invalidate every existing encrypted record in the database.
:::

---

## Model Attribute Casts

HAWKI provides three Eloquent casts that transparently encrypt and decrypt model attributes
using the wire formats above:

| Cast class | Handles | Notes |
|---|---|---|
| `AsSymmetricCryptoValueCast` | `SymmetricCryptoValue` | Used on `UserKeychainValue.value`, `Message.content`, etc. |
| `AsAsymmetricPublicKeyCast` | RSA public key strings | Normalises PEM / base64 public key formats |
| `AsHybridCryptoValueCast` | `HybridCryptoValue` | Used on ext-app secret columns |

All three are consumed by `AbstractCastableObject`-derived value objects. See
`100-Architecture/200-Utilities.md` for the full cast system description.

---

## SSRF Protection

All outbound HTTP calls from the backend must use `Http::getSsrfSafe()` (registered by
`SsrfSafeGetterMacro`). The macro validates every URL and every redirect hop against a
public-IP allowlist, blocking requests to internal subnets. Full documentation is in
[Infrastructure → index](../1000-Infrastructure/index.md).

---

## Passkey UX Settings

Two `config/hawki.php` keys under `security.passkey` control passkey UX on the frontend. Set
them via `.env`:

| Env variable | Default | Effect |
|---|---|---|
| `APP_SECURITY_PASSKEY_ALLOW_PASTE` | `true` | Allow pasting into the passkey input field |
| `APP_SECURITY_PASSKEY_CHAR_LIMITATION` | _(none)_ | Maximum character count for the passkey input |

---

## CSRF Tokens

CSRF tokens are automatically included in every response as the `X-HAWKI-CSRF-TOKEN` header.
The Svelte frontend reads this header on each response and refreshes its stored token, so the
standard Laravel CSRF protection applies to all state-mutating requests without any manual
token management by contributors.

---

## Related Articles

- [User Keychain](100-User-Keychain.md) — per-user key blob storage and batch-update API
- [Passkey Backup](150-Passkey-Backup.md) — encrypted passkey backup and recovery
- [External App Integration](200-External-Apps.md) — OAuth-like flow for third-party apps
