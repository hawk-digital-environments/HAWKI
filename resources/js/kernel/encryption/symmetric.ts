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
 *
 * WHY ciphertext and tag are split/rejoined: the WebCrypto AES-GCM implementation
 * returns `ciphertext || authTag` concatenated as a single buffer from `encrypt()`, and
 * expects the same concatenated form back into `decrypt()`. This module stores/transmits
 * the tag separately from the ciphertext (see `SymmetricCryptoValue`) to match the format
 * used by the PHP side (`\App\Services\Crypto\Value\SymmetricCryptoValue`), so the two
 * are sliced apart after encryption and recombined before decryption.
 */

import {
    arrayBufferToBase64,
    base64ToArrayBuffer,
    exportCryptoKeyToArrayBuffer,
    loadCryptoKeyFromArrayBuffer
} from './utils.js';

export interface SymmetricCryptoValue {
    /** Raw ciphertext bytes (tag already sliced off, see the module doc). Base64-encode via {@link toObject}/{@link toString}/{@link toJson} for storage. */
    ciphertext: ArrayBuffer;
    /** Raw 12-byte AES-GCM initialization vector. */
    iv: ArrayBuffer;
    /** Raw 16-byte AES-GCM authentication tag. */
    tag: ArrayBuffer;
    toObject: () => { ciphertext: string, iv: string, tag: string };
    toString: () => string;
    toJson: () => string;
}

/** @internal Assembles a {@link SymmetricCryptoValue} with working `toObject`/`toString`/`toJson`. */
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
 * Like {@link loadSymmetricCryptoValue}, but reads from the `.toJson()` shape —
 * either the JSON string itself or an already-parsed `{ciphertext, iv, tag}` object.
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
 * Like {@link loadSymmetricCryptoValue}, but takes the ciphertext, iv, and tag as three
 * separate base64 strings — useful when they're stored as separate columns/fields
 * rather than one pipe-delimited string.
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

/** Like {@link loadSymmetricCryptoValue}, but takes a `{ciphertext, iv, tag}` object of base64 strings. */
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

/** Reconstructs a {@link SymmetricCryptoValue} from the pipe-delimited string produced by its `toString()`. */
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

/**
 * The same as {@link encryptSymmetric}, but explicitly encrypts a CryptoKey instead of a
 * string — used to wrap one key with another (e.g. re-encrypting a room key for a new
 * keychain password).
 */
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

/** Encrypts `plaintext` with the given AES-256-GCM key. */
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
 * The same as {@link decryptSymmetric}, but explicitly decrypts a CryptoKey instead of a
 * string. Counterpart to {@link encryptKeySymmetric}.
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

/** Decrypts a {@link SymmetricCryptoValue} with the given AES-256-GCM key. Counterpart to {@link encryptSymmetric}. */
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
