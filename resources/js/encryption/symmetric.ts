/**
 * AES-256-GCM symmetric encryption helpers.
 *
 * Use this directly when both sides share the same key (e.g. the user's derived
 * passkey). For one-way encryption where only the server can decrypt, use
 * `hybrid.ts` instead.
 *
 * The serialised format (from `.toString()`) is `base64(iv)|base64(tag)|base64(ciphertext)`.
 * Multiple loaders handle the different storage representations:
 * - {@link loadSymmetricCryptoValue} — from `.toString()` pipe-delimited string
 * - {@link loadSymmetricCryptoValueFromJson} — from `.toJson()` JSON string or object
 * - {@link loadSymmetricCryptoValueFromStrings} — from three separate base64 strings
 * - {@link loadSymmetricCryptoValueFromObject} — from `{ciphertext, iv, tag}` object
 *
 * @example
 * const key = await generateSymmetricKey();
 * const encrypted = await encryptSymmetric('hello', key);
 * const stored = encrypted.toString();
 *
 * const loaded = loadSymmetricCryptoValue(stored);
 * const plaintext = await decryptSymmetric(loaded, key); // 'hello'
 */

import {
    arrayBufferToBase64,
    base64ToArrayBuffer,
    exportCryptoKeyToArrayBuffer,
    loadCryptoKeyFromArrayBuffer
} from './utils.js';

export interface SymmetricCryptoValue {
    ciphertext: ArrayBuffer; // Base64-encoded ciphertext
    iv: ArrayBuffer; // Base64-encoded initialization vector
    tag: ArrayBuffer; // Base64-encoded authentication tag,
    toObject: () => { ciphertext: string, iv: string, tag: string };
    toString: () => string;
    toJson: () => string;
}

/**
 * Internal helper function to create a SymmetricCryptoValue object.
 * @param ciphertext
 * @param iv
 * @param tag
 */
function createSymmetricCryptoValue(ciphertext: ArrayBuffer, iv: ArrayBuffer, tag: ArrayBuffer): SymmetricCryptoValue {
    const toObject = () => ({
        ciphertext: arrayBufferToBase64(ciphertext),
        iv: arrayBufferToBase64(iv),
        tag: arrayBufferToBase64(tag)
    });
    const toString = () => {
        const {iv, tag, ciphertext} = toObject();
        return [iv, tag, ciphertext].join('|');
    };
    const toJson = () => JSON.stringify(toObject());
    return {
        ciphertext,
        iv,
        tag,
        toObject,
        toString,
        toJson
    };
}

/**
 * Basically the same as loadSymmetricCryptoValue, but expects a JSON string
 * containing the ciphertext, iv, and tag values.
 * @param ciphertext
 */
export function loadSymmetricCryptoValueFromJson(ciphertext: string | {
    ciphertext: string,
    iv: string,
    tag: string
}): SymmetricCryptoValue {
    let cipherObject: any;
    if (typeof ciphertext === 'string') {
        try {
            cipherObject = JSON.parse(ciphertext);
        } catch (error) {
            throw new Error('Failed to parse symmetric crypto value from JSON');
        }
    } else {
        cipherObject = ciphertext;
    }
    if (!cipherObject || typeof cipherObject !== 'object' || !cipherObject.ciphertext || !cipherObject.iv || !cipherObject.tag) {
        throw new Error('Invalid symmetric crypto value format');
    }
    return loadSymmetricCryptoValueFromStrings(cipherObject.ciphertext, cipherObject.iv, cipherObject.tag);
}

/**
 * Basically the same as loadSymmetricCryptoValue, but expects the ciphertext, iv, and tag
 * as separate strings. This is useful for cases where the values are stored separately,
 * for example in a database or a form.
 * @param ciphertext
 * @param iv
 * @param tag
 */
export function loadSymmetricCryptoValueFromStrings(
    ciphertext: string,
    iv: string,
    tag: string
): SymmetricCryptoValue {
    if (!ciphertext || !iv || !tag) {
        throw new Error('Invalid parameters for loading symmetric crypto value');
    }

    return loadSymmetricCryptoValue([iv, tag, ciphertext].join('|'));
}

/**
 * Loads a SymmetricCryptoValue from an object containing the ciphertext, iv, and tag as base64 strings.
 * @param obj
 */
export function loadSymmetricCryptoValueFromObject(obj: {
    ciphertext: string,
    iv: string,
    tag: string
}): SymmetricCryptoValue {
    if (!obj || typeof obj !== 'object' || !obj.ciphertext || !obj.iv || !obj.tag) {
        throw new Error('Invalid symmetric crypto value format');
    }
    return loadSymmetricCryptoValueFromStrings(obj.ciphertext, obj.iv, obj.tag);
}

/**
 * Loads a SymmetricCryptoValue from a form generated when calling toString on a SymmetricCryptoValue.
 * @param ciphertext
 */
export function loadSymmetricCryptoValue(ciphertext: string): SymmetricCryptoValue {
    const valueParts = ciphertext.split('|').map(part => {
        return base64ToArrayBuffer(part);
    });

    if (valueParts.length !== 3) {
        throw new Error('Invalid symmetric encrypted value format');
    }

    return createSymmetricCryptoValue(valueParts[2], valueParts[0], valueParts[1]);
}

/**
 * Generates a new symmetric encryption key (AES-GCM 256-bit)
 * @returns The generated symmetric key
 */
export async function generateSymmetricKey() {
    try {
        return await window.crypto.subtle.generateKey(
            {
                name: 'AES-GCM',
                length: 256
            },
            true,
            ['encrypt', 'decrypt']
        );
    } catch (error) {
        throw new Error('Failed to generate encryption key');
    }
}

export async function encryptKeySymmetric(keyToEncrypt: CryptoKey, key: CryptoKey): Promise<SymmetricCryptoValue> {
    if (!keyToEncrypt || !key) {
        throw new Error('Missing required parameters for key encryption');
    }

    try {
        return await encryptArrayBufferSymmetric(await exportCryptoKeyToArrayBuffer(keyToEncrypt), key);
    } catch (error) {
        throw new Error(`Key encryption failed: ${(error as Error).message}`);
    }
}

/**
 * Encrypts the given data using AES-256-GCM symmetric encryption.
 * @param plaintext - The data to encrypt.
 * @param key - The key used for encryption.
 */
export async function encryptSymmetric(plaintext: string, key: CryptoKey): Promise<SymmetricCryptoValue> {
    if (!plaintext || !key) {
        throw new Error('Missing required parameters for encryption');
    }

    try {
        return await encryptArrayBufferSymmetric(
            new TextEncoder().encode(plaintext).buffer,
            key
        );
    } catch (error) {
        throw new Error(`Encryption failed: ${(error as Error).message}`);
    }
}

async function encryptArrayBufferSymmetric(data: ArrayBuffer, key: CryptoKey): Promise<SymmetricCryptoValue> {
    const iv = window.crypto.getRandomValues(new Uint8Array(12)).buffer; // 12-byte IV
    const encryptedData = await window.crypto.subtle.encrypt(
        {
            name: 'AES-GCM',
            iv: iv
        },
        key,
        data
    );
    const tag = encryptedData.slice(-16);
    const ciphertext = encryptedData.slice(0, encryptedData.byteLength - 16);
    return createSymmetricCryptoValue(ciphertext, iv, tag);
}

/**
 * The same as decryptSymmetric, but explicitly decrypts a CryptoKey instead of a string.
 * Accepts both full SymmetricCryptoValue and LeanSymmetricCryptoValue.
 * @param value - The symmetric encrypted value to decrypt.
 * @param key - The symmetric key used for decryption.
 */
export async function decryptKeySymmetric(value: SymmetricCryptoValue, key: CryptoKey): Promise<CryptoKey> {
    if (!value || !key) {
        throw new Error('Missing required parameters for key decryption');
    }

    try {
        const buffer = await decryptArrayBufferSymmetric(value, key);

        return await loadCryptoKeyFromArrayBuffer(buffer);
    } catch (error) {
        throw new Error(`Key decryption failed: ${(error as Error).message}`);
    }
}

/**
 * Decrypts the given symmetric encrypted value using AES-256-GCM.
 * @param value - The encrypted value to decrypt.
 * @param key - The symmetric key used for decryption.
 */
export async function decryptSymmetric(value: SymmetricCryptoValue, key: CryptoKey): Promise<string> {
    if (!value || !key) {
        throw new Error('Missing required parameters for decryption');
    }

    try {
        const decryptedBuffer = await decryptArrayBufferSymmetric(value, key);
        return new TextDecoder().decode(decryptedBuffer);
    } catch (error) {
        throw new Error(`Decryption failed: ${(error as Error).message || 'unknown reason'}`);
    }
}

async function decryptArrayBufferSymmetric(value: SymmetricCryptoValue, key: CryptoKey): Promise<ArrayBuffer> {
    if (!value || !value.ciphertext || !value.iv || !value.tag) {
        throw new Error('Missing required parameters for decryption');
    }

    const {ciphertext, iv, tag} = value;

    // Recombine ciphertext and tag (AES-GCM requires them together for decryption)
    const combinedBuffer = new Uint8Array(ciphertext.byteLength + tag.byteLength);
    combinedBuffer.set(new Uint8Array(ciphertext), 0);
    combinedBuffer.set(new Uint8Array(tag), ciphertext.byteLength);

    try {
        return await window.crypto.subtle.decrypt(
            {
                name: 'AES-GCM',
                iv: iv
            },
            key,
            combinedBuffer.buffer
        );
    } catch (error) {
        throw new Error(`Decryption failed: ${(error as Error).message || 'unknown reason'}`);
    }
}
