/**
 * Batching layer for keychain writes.
 *
 * Keychain mutations (set/remove/clear) go to the server in a single
 * `actions/batch-update` round-trip. This module turns the imperative
 * {@link BatchKeychainUpdater} callback API exposed by the keychain handle into a
 * deduplicated payload: it exports every `CryptoKey` to a transport string, encrypts it
 * with the passkey-derived keychain password, and posts the combined set/remove/clear
 * payload together. {@link collectDeferredBatchUpdates} lets migrations stack many
 * `runBatchUpdate` calls into one final flush.
 */
import {exportPrivateKeyToString, exportPublicKeyToString} from '$lib/kernel/encryption/asymmetric.js';
import {exportCryptoKeyToString} from '$lib/kernel/encryption/utils.js';
import {encryptSymmetric} from '$lib/kernel/encryption/symmetric.js';
import {decodeJsonApiIndexResponse} from '$lib/kernel/api/jsonApiEncoding.js';
import {type UserKeychainValue, UserKeychainValueType} from '$lib/plugins/core/schemas/resources/user-keychain-values.schema';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';

interface QueuedValueToSet {
    key: string;
    value: CryptoKey;
    type: UserKeychainValueType;
}

interface ValueToSet {
    key: string;
    value: string;
    type: UserKeychainValueType;
}

interface ValueToRemove {
    key: string;
    type: UserKeychainValueType;
}

interface BatchUpdatePayload {
    set?: ValueToSet[],
    remove?: ValueToRemove[],
    publicKey?: string,
    clean?: boolean
}

async function prepareValueToSend(
    keychainPassword: CryptoKey,
    {type, value, key}: QueuedValueToSet
): Promise<ValueToSet> {
    let valueString: string;

    if (type === 'private_key') {
        valueString = await exportPrivateKeyToString(value);
    } else if (type === 'public_key') {
        valueString = await exportPublicKeyToString(value);
    } else {
        valueString = await exportCryptoKeyToString(value);
    }

    const encrypted = await encryptSymmetric(
        valueString,
        keychainPassword
    );

    return {
        key,
        value: encrypted.toString(),
        type
    };
}

async function prepareQueuedSetsForSending(
    keychainPassword: CryptoKey,
    queuedSets: Map<string, QueuedValueToSet>
): Promise<ValueToSet[]> {
    const preparedValues: ValueToSet[] = [];
    for (const queuedSet of queuedSets.values()) {
        const prepared = await prepareValueToSend(keychainPassword, queuedSet);
        preparedValues.push(prepared);
    }
    return preparedValues;
}

async function buildPayload(
    queuedSets: ValueToSet[],
    queuedRemovals: ValueToRemove[],
    publicKey: string | null,
    clearOldValues: boolean
): Promise<BatchUpdatePayload | null> {
    let hasPayload = false;
    const payload: BatchUpdatePayload = {};
    if (queuedSets.length > 0) {
        payload.set = queuedSets;
        hasPayload = true;
    }
    if (queuedRemovals.length > 0) {
        payload.remove = queuedRemovals;
        hasPayload = true;
    }
    if (publicKey) {
        payload.publicKey = publicKey;
        hasPayload = true;
    }
    if (clearOldValues) {
        payload.clean = true;
        hasPayload = true;
    }

    return hasPayload ? payload : null;
}

export interface BatchKeychainUpdaterArgs {
    set: (key: string, value: CryptoKey, type: UserKeychainValueType) => void,
    remove: (key: string, type: UserKeychainValueType) => void
    clear: () => void
}

export type BatchKeychainUpdater = (args: BatchKeychainUpdaterArgs) => Promise<void>

let deferredUpdaters: BatchKeychainUpdater[] | null = null;

export async function runBatchUpdate(
    app: HawkiApp,
    keychainPassword: CryptoKey,
    updater: BatchKeychainUpdater
): Promise<UserKeychainValue[] | null> {
    if (deferredUpdaters) {
        deferredUpdaters.push(updater);
        return null;
    }

    const queuedSets = new Map<string, QueuedValueToSet>();
    const queuedRemovals = new Map<string, ValueToRemove>();
    let newPublicKey: CryptoKey | null = null;
    let clearOldValues = false;

    const makeCombinedKey = (key: string, type: UserKeychainValueType) => `${type}:${key}`;

    const set = (key: string, value: CryptoKey, type: UserKeychainValueType) => {
        const combinedKey = makeCombinedKey(key, type);
        queuedSets.set(combinedKey, {key, value, type});
        if (type === 'public_key') {
            newPublicKey = value;
        }
    };

    const remove = (key: string, type: UserKeychainValueType) => {
        const combinedKey = makeCombinedKey(key, type);
        queuedRemovals.set(combinedKey, {key, type});
    };

    const clear = () => {
        clearOldValues = true;
    };

    await updater({set, remove, clear});

    const preparedSets = await prepareQueuedSetsForSending(keychainPassword, queuedSets);
    const preparedRemovals = Array.from(queuedRemovals.values());
    const preparedPublicKey = newPublicKey ? await exportPublicKeyToString(newPublicKey) : null;
    const payload = await buildPayload(preparedSets, preparedRemovals, preparedPublicKey, clearOldValues);

    if (!payload) {
        return null;
    }

    const response = await app.restApi.postToResourceAction('user-keychain-values', 'actions/batch-update', payload);
    return decodeJsonApiIndexResponse<UserKeychainValue>(response);
}

/**
 * Instead of running the batch update immediately, this function collects the updaters and runs them all at once after the runner has completed.
 * This allows even nested calls to batch update to be collected together and run in a single batch, which can be more efficient and ensures that all updates are applied together.
 *
 * WARNING: Any runBatchUpdate() call wrapped inside this runner will return NULL, since the actual execution is deferred until the end of the runner.
 * This means that if you need to use the result of a batch update inside the runner, you should not wrap it in this function.
 * There could be unwanted side effects and errors if you are not careful with this!
 *
 * @internal Designed for migrations and other special cases where you want to run multiple batch updates together.
 */
export async function collectDeferredBatchUpdates(
    app: HawkiApp,
    keychainPassword: CryptoKey,
    runner: () => Promise<void>
): Promise<UserKeychainValue[] | null> {
    try {
        deferredUpdaters = [];
        await runner();
        if (!deferredUpdaters || deferredUpdaters.length === 0) {
            return null;
        }

        // Save the collected updaters to a local variable and clear the global one before running
        // This means the next batch update (below) will indeed be able to execute the update.
        const updatersToRun = deferredUpdaters;
        deferredUpdaters = null;

        return await runBatchUpdate(app, keychainPassword, async (args) => {
            for (const updater of updatersToRun) {
                await updater(args);
            }
        });
    } finally {
        deferredUpdaters = null;
    }
}
