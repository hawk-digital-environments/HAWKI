import type {MigrationContext} from '$lib/data/migrations/migrator.js';
import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
import {decryptSymmetric, loadSymmetricCryptoValue} from '$lib/encryption/symmetric.js';
import {deriveKey} from '$lib/encryption/utils.js';
import {getConfig} from '$lib/data/config/config.js';
import {createKeychainHandle} from '$lib/data/keychain/keychainHandle.js';
import type {UserKeychainValueType} from '$lib/schemas/resources/user-keychain-values.schema.js';
import {loadPrivateKey, loadPublicKey} from '$lib/encryption/asymmetric.js';

export async function migrate({name, data}: MigrationContext) {
    console.warn(getConfig());
    // No data means the user is probably new and is already on the new keychain system, so we can skip the migration.
    if (!data || !data.blob) {
        return;
    }

    if (`${oldUiBridge.passkey}` === '') {
        throw new Error('No passkey available for migration, cannot proceed!');
    }

    const keychainPassword = await deriveKey(
        oldUiBridge.passkey as string,
        'keychain_encryptor',
        getConfig().salts!.userdata);

    const decrypted = await decryptSymmetric(loadSymmetricCryptoValue(data.blob), keychainPassword);
    const decryptedKeychain = JSON.parse(decrypted);

    const keychainHandle = createKeychainHandle(() => oldUiBridge.passkey!);
    await keychainHandle.doUpdate(async ({set, clear}) => {
        clear();

        const keysToIgnore = ['username', 'time-signature'];
        for (const [key, value] of Object.entries(decryptedKeychain)) {
            if (keysToIgnore.includes(key)) {
                continue;
            }

            console.log(`Migrating key: ${key}`);
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
