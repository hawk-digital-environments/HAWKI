# Passkey Backup

The passkey backup mechanism gives users a way to recover their passkey when they have lost access to their primary device or forgotten their passkey. The backup is stored server-side in encrypted form — the server never sees the plaintext passkey or the recovery secret.

## The `PasskeyBackup` model

`App\Models\PasskeyBackup` stores one row per user, keyed by `username`. The three crypto columns (`ciphertext`, `iv`, `tag`) mirror the `SymmetricCryptoValue` wire format components (see [Encryption](./410-Encryption.md)) but are stored as separate columns rather than a single pipe-delimited string, so the server can update individual components without touching the others.

## Intended use case

During registration, the browser generates a backup code in the format `xxxx-xxxx-xxxx-xxxx` and presents it to the user (the recovery code). The browser then:

1. Derives an AES key from the backup code using PBKDF2 and the `BACKUP` salt from `SaltProvider`.
2. Encrypts the user's passkey with that derived key.
3. Posts the `{iv, tag, ciphertext}` triple to the server, keyed by `username`.

The server persists this via `PasskeyBackup::updateOrCreate()` (keyed on `username`). No dedicated JSON:API resource exists for this model — writes happen through the authentication flow, not through the standard JSON:API layer.

## Recovery flow

1. The user enters their backup code on the recovery screen.
2. The frontend fetches the stored `{iv, tag, ciphertext}` tuple from the server (keyed by `username`).
3. The browser re-derives the same AES key from the backup code and the `BACKUP` salt.
4. The browser decrypts the ciphertext to recover the plaintext passkey.
5. The user can now unlock their keychain and regenerate their session keys as if they had entered their passkey normally.

At no point does the server participate in the derivation or decryption.

## Relationship to the keychain

`PasskeyBackup` is a **separate** persistence mechanism from the `user_keychain_values` table (see [User Keychain](./420-User-Keychain.md)). It stores the recovery-path credential only. The keychain stores all runtime key material (private key, room keys, AI conversation keys). The two tables are independent:

- Updating a keychain entry does not affect the backup.
- Deleting a backup disables recovery but leaves the keychain intact.
- Deleting keychain entries does not touch the backup.

## Operator notes

:::warning
If a user loses both their passkey and their backup code, **their encrypted data cannot be recovered**. There is no server-side master key or admin override. Operators should clearly communicate to users that the backup code must be stored securely — losing it permanently locks the user out of their encrypted history.
:::

`PasskeyService` (in `app/Services/Profile/`) contains the write and read operations for `PasskeyBackup`. It is a pre-refactor class that uses `Auth::user()` facade and direct Eloquent static calls — see the [Technical Debt Register](../../900-Technical-Debt.md).
