import {deriveKey, loadCryptoKey} from '../../encryption/utils.js';
import {decryptSymmetric, generateSymmetricKey, loadSymmetricCryptoValue} from '../../encryption/symmetric.js';
import {generateAsymmetricKeyPair, loadPrivateKey, loadPublicKey} from '../../encryption/asymmetric.js';
import {getConfig} from '$lib/data/config/config.js';
import type {UserKeychainValue, UserKeychainValueType} from '$lib/schemas/resources/user-keychain-values.schema.js';
import {getFromResourceAction, getResourceCollectionFromApi} from '$lib/data/api/api.js';
import {type BatchKeychainUpdater, type BatchKeychainUpdaterArgs, collectDeferredBatchUpdates, runBatchUpdate} from '$lib/data/keychain/batchUpdater.js';
import z from 'zod';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';

export type KeychainHandle = Awaited<ReturnType<typeof createKeychainHandle>>;

export interface RoomKeys {
    /**
     * The crypto key used to encrypt/decrypt user messages in the room.
     */
    roomKey: CryptoKey;
    /**
     * The AI key derived from the room key, used for AI-related operations.
     * This key is derived using the room slug and the AI salt.
     */
    aiKey: CryptoKey;
    /**
     * The AI key derived from the room key, used for AI-related operations.
     * This key is derived using the room slug and the AI salt.
     * This is a legacy key, used for compatibility with older versions of Hawki.
     * @deprecated In newer code, use `aiKey` instead.
     */
    aiLegacyKey: CryptoKey;
}

export function createKeychainHandle(passkeyProvider: () => string) {
    const sync = new SyncPipeline<{ change: void }>();

    const onChange = (callback: () => void) => sync.on('change', callback);
    const triggerChange = () => sync.trigger('change');

    let loadedPasskey: CryptoKey | null = null;
    let loadedKeys: Partial<Record<UserKeychainValueType, Record<string, CryptoKey>>> = {};
    let loadedRoomKeys: Record<string, RoomKeys> = {};
    let incompleteRoomKeys: Record<string, CryptoKey> = {};
    let passkeyValidator: string | null = null;

    const loadPasskeyValidator = async () => {
        return (await getFromResourceAction(
            'user-keychain-values',
            'actions/validator',
            {
                schema: z.object({
                    validator: z.string()
                })
            }
        )).validator;
    };

    const validateKeychainPassword = async (passkey: string): Promise<boolean> => {
        passkeyValidator = passkeyValidator || await loadPasskeyValidator();
        const derivedKey = await deriveKeychainPassword(passkey);
        try {
            await decryptSymmetric(
                loadSymmetricCryptoValue(passkeyValidator),
                derivedKey
            );
            return true;
        } catch (error) {
            return false;
        }
    };

    const getKeychainPassword = async () => {
        if (loadedPasskey) {
            return loadedPasskey;
        }

        const passkey = passkeyProvider();
        if (!passkey) {
            throw new Error('No passkey provided to create keychain handle!');
        }
        loadedPasskey = await deriveKeychainPassword(passkey);
        return loadedPasskey;
    };

    const load = async () => await loadKeys(await getResourceCollectionFromApi('user-keychain-values'));

    const loadKeys = async (records: UserKeychainValue[]) => {
        const loadingQueue: (() => Promise<void>)[] = [];
        loadedKeys = {};
        records.forEach(i => {
            loadingQueue.push((async () => {
                const type = i.type;
                if (!loadedKeys[type]) {
                    loadedKeys[type] = {};
                }
                loadedKeys[type]![i.key] = await loadCryptoKeyFromKeychainValue(i);
            }));
        });

        // We load the keys in batches to avoid overwhelming the browser with too many simultaneous decryption operations
        // This is especially important for users with many rooms, as each room has multiple keys that need to be loaded.
        const loadingQueueBatchSize = 5;
        while (loadingQueue.length > 0) {
            const batch = loadingQueue.splice(0, loadingQueueBatchSize);
            await Promise.all(batch.map(fn => fn()));
        }

        await loadRoomKeys();
        triggerChange();
    };

    const loadCryptoKeyFromKeychainValue = async ({value, type}: UserKeychainValue) => {
        const decrypted = await decryptSymmetric(
            loadSymmetricCryptoValue(value),
            await getKeychainPassword()
        );

        if (type === 'public_key') {
            return await loadPublicKey(decrypted);
        }

        if (type === 'private_key') {
            return await loadPrivateKey(decrypted);
        }

        return await loadCryptoKey(decrypted);
    };

    const roomKeyUpdateHelper = async (
        update: BatchKeychainUpdaterArgs,
        roomSlug: string,
        roomKey: CryptoKey
    ) => {
        const aiSalt = getConfig().salts?.ai;
        if (!aiSalt) {
            throw new Error('AI salt is not configured, cannot derive AI keys for room!');
        }
        const aiKey = await deriveKey(roomKey, roomSlug, aiSalt);
        // No need to wait, fire and forget
        // Due to a bug in the initial deriveKey implementation,
        // the room key was not used to derive the AI key, instead the CryptoKey was used as string,
        // meaning it was cast into a string representation of the CryptoKey object.
        // This is a workaround to support legacy keys.
        const aiLegacyKey = await deriveKey('[object CryptoKey]', roomSlug, aiSalt);
        update.set(roomSlug, roomKey, 'room_key');
        update.set(roomSlug, aiKey, 'room_ai');
        update.set(roomSlug, aiLegacyKey, 'room_ai_legacy');
    };

    const loadRoomKeys = async () => {
        loadedRoomKeys = {};
        incompleteRoomKeys = {};
        const roomAiCryptoKeys = loadedKeys['room_ai'] || {};
        const roomAiLegacyCryptoKeys = loadedKeys['room_ai_legacy'] || {};
        const roomCryptoKeys = loadedKeys['room_key'] || {};

        Object.entries(roomCryptoKeys).forEach(([key, roomCryptoKey]) => {
            let aiKey: CryptoKey;
            let aiLegacyKey: CryptoKey;
            if (!roomAiCryptoKeys[key] || !roomAiLegacyCryptoKeys[key]) {
                // The 2026_06_10_190815_after_passkey_upgrade_room_key_format migration should take care of generating the missing AI keys
                // but better safe than sorry.
                console.warn(`AI key or legacy AI key for room "${key}" is missing! This means that the room will not be able to use AI features until the keys are generated.`);
                incompleteRoomKeys[key] = roomCryptoKey;
                return;
            } else {
                aiKey = roomAiCryptoKeys[key];
                aiLegacyKey = roomAiLegacyCryptoKeys[key];
            }

            loadedRoomKeys[key] = {
                roomKey: roomCryptoKey,
                aiKey,
                aiLegacyKey
            };
        });
    };

    const getKey = (key: string, type: UserKeychainValueType): CryptoKey | null => loadedKeys[type]?.[key] || null;

    const getKeyOrFail = (key: string, type: UserKeychainValueType): CryptoKey => {
        const loadedKey = getKey(key, type);
        if (!loadedKey) {
            throw new Error(`Key "${key}" of type "${type}" not found in keychain!`);
        }
        return loadedKey;
    };

    const doUpdate = async (updater: BatchKeychainUpdater) => {
        const response = await runBatchUpdate(await getKeychainPassword(), updater);
        if (!response) {
            return false;
        }
        await loadKeys(response);
        return true;
    };

    /**
     * Allows to wrap multiple doUpdate calls into a single batch update, which can improve performance when doing multiple updates in a row.
     *
     * WARNING: Any runBatchUpdate() call wrapped inside this runner will return NULL, since the actual execution is deferred until the end of the runner.
     * This means that if you need to use the result of a batch update inside the runner, you should not wrap it in this function.
     * There could be unwanted side effects and errors if you are not careful with this!
     *
     * @internal Designed for migrations and other special cases where you want to run multiple batch updates together.
     * @param runner
     */
    const doUpdatesDeferred = async (runner: () => Promise<void>) => {
        return collectDeferredBatchUpdates(await getKeychainPassword(), runner);
    };

    const initializeNewKeychain = async () => {
        const keyPair = await generateAsymmetricKeyPair();
        return await doUpdate(async (update) => {
            update.clear();
            update.set('privateKey', keyPair.privateKey, 'private_key');
            update.set('publicKey', keyPair.publicKey, 'public_key');
            update.set('aiConvKey', await generateSymmetricKey(), 'ai_conv');
        });
    };

    /**
     * Returns an array containing all key-names in the keychain
     */
    const listKeys = (type?: UserKeychainValueType) => {
        if (type) {
            return Array.from(Object.keys(loadedKeys[type] || {}));
        }
        const allKeys = new Set<string>();
        Object.values(loadedKeys).forEach(typeMap => {
            Object.keys(typeMap).forEach(key => allKeys.add(key));
        });
        return Array.from(allKeys);
    };

    /**
     * Returns the users private key, to decrypt data sent to them
     */
    const privateKey = () => getKeyOrFail('privateKey', 'private_key');

    /**
     * Returns the users public key, to encrypt data sent to them
     */
    const publicKey = () => getKeyOrFail('publicKey', 'public_key');

    /**
     * Returns the AI conversation key, used for encrypting/decrypting AI conversations.
     */
    const aiConvKey = () => getKeyOrFail('aiConvKey', 'ai_conv');

    /**
     * Returns a map of all room keys in the keychain, mapped by room slug.
     * If the AI key or legacy AI key for a room is missing, new keys will be derived and stored.
     * This ensures that all rooms have the necessary keys for encryption and AI operations.
     */
    const roomKeys = () => loadedRoomKeys;

    /**
     * Returns a map of room keys that are missing their AI keys, mapped by room slug.
     * @internal This can be used in migrations to find room keys that were created before the AI key generation was implemented.
     */
    const brokenRoomKeys = () => incompleteRoomKeys;

    /**
     * The same as "roomKeys", but for a specific room.
     * If the room does not have keys, null is returned.
     * @param room
     */
    const roomKeysOf = (room: string) => loadedRoomKeys[room] || null;

    /**
     * Imports a room key (from an invitation) into the keychain.
     * If the room key already exists, it will be overwritten.
     * @param roomSlug
     * @param roomKey
     */
    const importRoomKey = async (roomSlug: string, roomKey: CryptoKey) => {
        return doUpdate(async (update) => {
            await roomKeyUpdateHelper(update, roomSlug, roomKey);
        });
    };

    const createRoomKey = async (roomSlug: string): Promise<RoomKeys | null> => {
        if (loadedRoomKeys[roomSlug]) {
            console.warn('Room keys for room already exist, not creating new ones:', roomSlug);
            return null;
        }

        await doUpdate(async (update) => {
            await roomKeyUpdateHelper(update, roomSlug, await generateSymmetricKey());
        });

        return roomKeysOf(roomSlug);
    };

    const removeRoomKeys = async (roomSlug: string) => {
        await doUpdate(async (update) => {
            update.remove(roomSlug, 'room_key');
            update.remove(roomSlug, 'room_ai');
            update.remove(roomSlug, 'room_ai_legacy');
        });
    };

    return {
        onChange,
        validateKeychainPassword,
        load,
        doUpdate,
        doUpdatesDeferred,
        initializeNewKeychain,
        publicKey,
        aiConvKey,
        privateKey,
        listKeys,
        importRoomKey,
        roomKeys,
        brokenRoomKeys,
        roomKeysOf,
        createRoomKeys: createRoomKey,
        removeRoomKeys
    };
}

export function deriveKeychainPassword(
    passkey: string
): Promise<CryptoKey> {
    const userDataSalt = getConfig().salts!.userdata;
    return deriveKey(passkey, 'keychain_encryptor', userDataSalt);
}
