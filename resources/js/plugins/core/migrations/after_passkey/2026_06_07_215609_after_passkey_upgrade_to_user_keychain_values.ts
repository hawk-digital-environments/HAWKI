import {decryptSymmetric, loadSymmetricCryptoValue} from '$lib/kernel/encryption/symmetric.js';
import {deriveKey} from '$lib/kernel/encryption/utils.js';
import {createKeychainHandle} from '$lib/kernel/keychain/keychainHandle.js';
import {loadPrivateKey, loadPublicKey} from '$lib/kernel/encryption/asymmetric.js';
import type {MigrationContext} from '$lib/kernel/migrations/MigrationExtension.js';
import type {UserKeychainValueType} from '$plugins/core/schemas/resources/user-keychain-values.schema.js';

/**
 * `after_passkey` migration: converts the legacy single-blob keychain into the
 * per-key `user-keychain-values` resource.
 *
 * The legacy keychain was one symmetrically-encrypted blob stored under a
 * server record and decrypted with a passkey-derived password; the new format
 * stores each key as its own `user-keychain-values` record (see
 * {@link KeychainHandle}). This migration runs once after a passkey login when
 * the server reports the user still has a legacy blob to convert.
 *
 * Contract (from {@link MigrationContext}):
 *   - `data.blob` — the server-provided legacy blob (encrypted with the
 *     passkey-derived `keychain_encryptor` key + `userdata` salt). When absent
 *     the user is new and already on the new system; the migration is a no-op.
 *   - `app.config.get().salts.userdata` — the salt used to re-derive the
 *     legacy keychain password.
 *   - `app.passkeySession.passkey` — the user's passkey, used both to derive
 *     the old password and as the provider for the new `KeychainHandle`.
 *
 * WHAT it converts: for every key in the decrypted legacy blob (except the
 * `username` / `time-signature` metadata entries) it re-imports the key —
 * public/private keys via `loadPublicKey`/`loadPrivateKey`, symmetric keys via
 * `crypto.subtle.importKey('jwk', …)` because the legacy format exported them
 * as JWK while the new format stores raw base64 — and writes it into the new
 * per-key store under the matching {@link UserKeychainValueType}
 * (`public_key`, `private_key`, `ai_conv`, or `room_key`). The `clear()` first
 * wipes any partial new keychain so the conversion is idempotent.
 */
export async function migrate({name, data, app}: MigrationContext) {
    // No data means the user is probably new and is already on the new keychain system, so we can skip the migration.
    if (!data || !data.blob) {
        return;
    }

    const passkey = app.passkeySession.passkey;
    if (!passkey) {
        throw new Error('No passkey available for migration, cannot proceed!');
    }

    const keychainPassword = await deriveKey(
        passkey,
        'keychain_encryptor',
        app.config.get().salts!.userdata);

    const decrypted = await decryptSymmetric(loadSymmetricCryptoValue(data.blob), keychainPassword);
    const decryptedKeychain = JSON.parse(decrypted);

    const keychainHandle = createKeychainHandle(app, () => passkey);
    await keychainHandle.doUpdate(async ({set, clear}) => {
        clear();

        const keysToIgnore = ['username', 'time-signature'];
        for (const [key, value] of Object.entries(decryptedKeychain)) {
            if (keysToIgnore.includes(key)) {
                continue;
            }

            try {
                let loadedValue;
                let type: UserKeychainValueType;
                if (key === 'publicKey' && typeof value === 'string') {
                    loadedValue = await loadPublicKey(value, true);
                    set('publicKey', loadedValue, 'public_key');
                    console.log('Loaded public key from legacy keychain, now saving to new keychain format...');
                    continue;
                }

                if (key === 'privateKey' && typeof value === 'string') {
                    loadedValue = await loadPrivateKey(value, true);
                    set('privateKey', loadedValue, 'private_key');
                    console.log('Loaded private key from legacy keychain, now saving to new keychain format...');
                    continue;
                }

                // There is no helper to import from jwk (legacy keys were exported as jwk), so we have to do it manually here.
                // The keys will automatically be re-exported in the new format (raw base64) when they are updated, so this is a one-time compatibility thing.
                loadedValue = await window.crypto.subtle.importKey(
                    'jwk',
                    value as JsonWebKey,
                    {
                        name: 'AES-GCM',
                        length: 256
                    },
                    true,
                    ['encrypt', 'decrypt']
                );
                type = key === 'aiConvKey' ? 'ai_conv' : 'room_key';
                set(key, loadedValue, type);
                console.log(`Loaded key "${key}" from legacy keychain, now saving to new keychain format with type "${type}"...`);

            } catch (error) {
                console.error(`Error importing key "${key}" from legacy keychain:`, error);
                throw error;
            }
        }
    });

    console.log('Legacy keychain migration completed successfully.');
}
