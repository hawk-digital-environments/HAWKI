/**
 * Low-level crypto utilities shared across symmetric and asymmetric modules.
 *
 * Most code should use `symmetric.ts`, `asymmetric.ts`, or `hybrid.ts` instead.
 * Use these helpers directly only when you need raw key serialisation or PBKDF2
 * key derivation.
 *
 * {@link deriveKey} is the standard way to turn a user's passkey string into an
 * AES-256-GCM CryptoKey. Always pass the server salt from the connection config
 * to prevent offline dictionary attacks.
 *
 * @example
 * import {deriveKey, loadServerSalt} from '$lib/kernel/encryption/utils.js';
 * const salt = loadServerSalt(getConnection().crypto_salt);
 * const aesKey = await deriveKey(userPasskey, 'keychain', salt);
 */

export type ServerSalt = Uint8Array;

export async function exportCryptoKeyToArrayBuffer(key: CryptoKey, format: Exclude<KeyFormat, 'jwk'> = 'raw'): Promise<ArrayBuffer> {
    try {
        return await window.crypto.subtle.exportKey(format, key);
    } catch (error) {
        throw new Error(`Failed to export crypto key as ${format}: ${(error as Error).message}`);
    }
}

export async function exportCryptoKeyToString(key: CryptoKey, format: Exclude<KeyFormat, 'jwk'> = 'raw'): Promise<string> {
    return arrayBufferToBase64(await exportCryptoKeyToArrayBuffer(key, format));
}

export async function loadCryptoKey(keyString: string): Promise<CryptoKey> {
    const buffer = base64ToArrayBuffer(keyString);
    return loadCryptoKeyFromArrayBuffer(buffer);
}

export async function loadCryptoKeyFromArrayBuffer(buffer: ArrayBuffer): Promise<CryptoKey> {
    try {
        return await window.crypto.subtle.importKey(
            'raw',
            buffer,
            {
                name: 'AES-GCM',
                length: 256 // Assuming the key is 256 bits
            },
            true, // extractable
            ['encrypt', 'decrypt']
        );
    } catch (error) {
        throw new Error(`Failed to import crypto key from ArrayBuffer: ${(error as Error).message}`);
    }
}

/**
 * Converts the server-provided salt string into bytes for {@link deriveKey}. Maps each
 * character to its char code directly — this is *not* base64 decoding, it expects the
 * raw latin1 salt string as sent by the server (`crypto_salt` on the connection).
 */
export function loadServerSalt(raw: string): ServerSalt {
    return Uint8Array.from(raw, c => c.charCodeAt(0));
}

/**
 * Derives an AES-256-GCM key from a passphrase (or another key) using PBKDF2, 100k
 * iterations. `label` is mixed into the salt alongside `serverSalt` so the same
 * passphrase yields a different key per purpose (e.g. `'keychain'` vs a room key) —
 * pass a distinct, stable label per use case.
 */
export async function deriveKey(
    key: string | CryptoKey,
    label: string,
    serverSalt: ServerSalt | string
): Promise<CryptoKey> {
    if (!key || !label || !serverSalt) {
        throw new Error('Missing required parameters for key derivation');
    }

    if (typeof serverSalt === 'string') {
        serverSalt = loadServerSalt(serverSalt);
    }

    try {
        if (key instanceof CryptoKey) {
            key = arrayBufferToBase64(await exportCryptoKeyToArrayBuffer(key));
        }

        const keyMaterial = await window.crypto.subtle.importKey(
            'raw',
            new TextEncoder().encode(key),
            {name: 'PBKDF2'},
            false,
            ['deriveKey']
        );

        // Combine label and serverSalt to create a unique salt for this derived key
        const combinedSalt = new Uint8Array([
            ...new TextEncoder().encode(label),
            ...new Uint8Array(serverSalt)
        ]);

        // Derive the actual key using PBKDF2
        return await window.crypto.subtle.deriveKey(
            {
                name: 'PBKDF2',
                salt: combinedSalt,
                iterations: 100000,
                hash: 'SHA-256'
            },
            keyMaterial,
            {name: 'AES-GCM', length: 256},
            true, // extractable
            ['encrypt', 'decrypt']
        );
    } catch (error) {
        throw new Error(`Failed to derive key: ${(error as Error).message}`);
    }
}

/** Converts an ArrayBuffer to a Base64 string. */
export function arrayBufferToBase64(buffer: ArrayBuffer) {
    const binary = String.fromCharCode.apply(null, [...new Uint8Array(buffer)]);
    return btoa(binary);
}

/** Converts a Base64 string to an ArrayBuffer. */
export function base64ToArrayBuffer(base64: string): ArrayBuffer {
    const binary = atob(base64);
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}
