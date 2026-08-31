# Encryption

HAWKI operates a client-first encryption model: all personally identifiable message content and cryptographic keys are encrypted in the browser before they reach the server. The server stores ciphertext blobs and distributes salts — it never holds plaintext keys or decrypted content.

This page is the single home for the cryptographic architecture. For the browser-side implementation of `deriveKey()`, symmetric helpers, and the `KeychainHandle` store, see the frontend Encryption page.

## Three tiers

HAWKI uses three complementary encryption modes, each chosen for its role in the data flow.

### Tier 1 — Symmetric (AES-256-GCM)

Used for bulk data: room messages, AI-conversation messages, the keychain itself.

- AES-256-GCM with a random 12-byte IV per operation. Authentication tag verifies ciphertext integrity on every decrypt.
- Wire format serialised by `SymmetricCryptoValue`:

  ```
  base64(iv) | base64(tag) | base64(ciphertext)
  ```

  The three segments are joined by `|`. Both the backend `AsSymmetricCryptoValueCast` Eloquent cast and the frontend `symmetric.ts` helpers read and write this exact format.

### Tier 2 — Asymmetric (RSA-OAEP-4096)

Used for key distribution: encrypting a symmetric key so only one specific recipient can unwrap it.

- RSA-OAEP with a 4096-bit keypair. Each user holds one keypair.
- Public keys are stored in the `user_keychain_values` table (type `public_key`) and on the `users` record.
- Private keys never leave the browser in plaintext; the server stores only the passkey-encrypted blob.

### Tier 3 — Hybrid (AES key + RSA wrapping)

Used for large data that only the server needs to decrypt — for example, ext-app secrets the server must later retrieve. A random AES key encrypts the payload; that AES key is then wrapped with an RSA public key.

Wire format serialised by `HybridCryptoValue`:

```
base64(encryptedAesKey) | base64(symmetricPayload)
```

## `SaltProvider`

`App\Services\Encryption\SaltProvider` is the single source for all server-side salts. The frontend fetches salts through the connection bootstrap payload (the `salts` block inside `hawki-core` config — see [Config Blocks](../../200-Concepts/200-Config-Blocks.md)) — there is no dedicated salt HTTP endpoint.

`SaltType` enum maps each salt to its env variable: `USERDATA` (`USERDATA_ENCRYPTION_SALT`), `INVITATION` (`INVITATION_SALT`), `AI` (`AI_CRYPTO_SALT`), `PASSKEY` (`PASSKEY_SALT`), `BACKUP` (`BACKUP_SALT`). Call `SaltProvider::getSalt(SaltType $type)` or the typed convenience methods to retrieve a `Salt` value object. Open the enum for the canonical case list.

:::danger[Production salts must be pre-configured]
When a salt env variable is missing or empty, `SaltProvider` falls back to `hash('sha256', $appKey . 'semi_static_salt' . hash('sha256', $type->value))`. This fallback is **development-only** — it is tied to `APP_KEY` and predictable to anyone who obtains it.

All five salt variables must be set to random, independent values in `_docker_production/.env` before the very first `php artisan migrate` run. Re-seeding salts after migration will invalidate every existing encrypted record in the database.
:::

## Model attribute casts

HAWKI provides three Eloquent casts that transparently encrypt and decrypt model attributes using the wire formats above. See [Model Casts](../../200-Concepts/160-Model-Casts/index.md) for the cast mechanics.

- `AsSymmetricCryptoValueCast` — handles `SymmetricCryptoValue` (used on `UserKeychainValue.value`, `Message.content`, …)
- `AsAsymmetricPublicKeyCast` — handles RSA public key strings, normalises PEM / base64 formats
- `AsHybridCryptoValueCast` — handles `HybridCryptoValue` (used on ext-app secret columns)

## Standalone crypto library

The server-side cryptographic primitives (symmetric, asymmetric, and hybrid encryption/decryption) are maintained as a standalone PHP library: [`hawk-hhg/hawki-crypto`](https://github.com/hawk-digital-environments/hawki-crypto). The backend wire formats (`SymmetricCryptoValue`, `HybridCryptoValue`, `AsymmetricKeypair`) and the Eloquent crypto casts are built against this library. The library provides `SymmetricCrypto`, `AsymmetricCrypto`, and `HybridCrypto` classes plus serialisable value objects that can be cast to/from strings and JSON.

## Passkey UX settings

Two `config/hawki.php` keys under `security.passkey` control passkey UX on the frontend: `APP_SECURITY_PASSKEY_ALLOW_PASTE` (default `true` — allow pasting into the passkey input field) and `APP_SECURITY_PASSKEY_CHAR_LIMITATION` (default none — maximum character count for the passkey input).

## Where to go next

- [User Keychain](./420-User-Keychain.md) — per-user key blob storage and batch-update API
- [Passkey Backup](./430-Passkey-Backup.md) — encrypted passkey backup and recovery
