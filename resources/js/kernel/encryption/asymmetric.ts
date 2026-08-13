/**
 * RSA-OAEP 4096-bit asymmetric encryption helpers.
 *
 * Used to encrypt short values (typically AES keys) with a recipient's public key.
 * For encrypting arbitrary-length data, use `hybrid.ts` which wraps these helpers.
 *
 * Keys are serialised as base64 SPKI (public) or PKCS#8 (private) strings. Load
 * a server-provided public key with {@link loadPublicKey}; load a stored private key
 * with {@link loadPrivateKey}.
 *
 * @example
 * // Encrypt a message for the server
 * const pubKey = await loadPublicKey(serverPublicKeyBase64);
 * const ciphertext = await encryptAsymmetric('secret', pubKey);
 *
 * @example
 * // Generate a new key pair (e.g. on first login)
 * const {publicKey, privateKey} = await generateAsymmetricKeyPair();
 * const pubString = await exportPublicKeyToString(publicKey);
 * // send pubString to the server, store privateKey encrypted with the user's passkey
 */
import {arrayBufferToBase64, base64ToArrayBuffer, exportCryptoKeyToArrayBuffer, exportCryptoKeyToString} from './utils.js';

/**
 * Loads an SPKI-encoded public key from a base64-encoded string or raw ArrayBuffer.
 * @param extractable Whether the loaded key should be extractable (i.e. can be exported again). Defaults to false for security.
 */
export async function loadPublicKey(keyString: string | ArrayBuffer, extractable: boolean = false): Promise<CryptoKey> {
    if (typeof keyString === 'string') {
        keyString = base64ToArrayBuffer(keyString);
    }

    return await window.crypto.subtle.importKey(
        'spki',
        keyString,
        {
            name: 'RSA-OAEP',
            hash: {name: 'SHA-256'}
        },
        extractable,
        ['encrypt']
    );
}

/**
 * Serialises a public key to a base64 SPKI string, e.g. to send it to the server or
 * another user so they can {@link loadPublicKey} it and encrypt data for this user.
 */
export async function exportPublicKeyToString(publicKey: CryptoKey): Promise<string> {
    return exportCryptoKeyToString(publicKey, 'spki');
}

/**
 * Loads a PKCS#8-encoded private key from a base64-encoded string or raw ArrayBuffer.
 * @param extractable Whether the loaded key should be extractable (i.e. can be exported again). Defaults to false for security reasons.
 */
export async function loadPrivateKey(keyString: string | ArrayBuffer, extractable: boolean = false): Promise<CryptoKey> {
    if (typeof keyString === 'string') {
        keyString = base64ToArrayBuffer(keyString);
    }

    return await window.crypto.subtle.importKey(
        'pkcs8',
        keyString,
        {
            name: 'RSA-OAEP',
            hash: {name: 'SHA-256'}
        },
        extractable,
        ['decrypt']
    );
}

/**
 * Serialises a private key to a base64 PKCS#8 string. Only ever store this encrypted
 * (see `batchUpdater.ts`, which symmetrically encrypts it with the keychain password
 * before it is sent to the server) — never persist or transmit it in plaintext.
 */
export async function exportPrivateKeyToString(privateKey: CryptoKey): Promise<string> {
    return exportCryptoKeyToString(privateKey, 'pkcs8');
}

/**
 * The same as {@link encryptAsymmetric}, but explicitly encrypts a CryptoKey instead of a string.
 * Used by `hybrid.ts` to wrap a freshly-generated AES key with a recipient's RSA public
 * key, so that only that recipient's private key can unwrap it.
 * @param keyToEncrypt - The symmetric (or other) key to wrap, exported to raw bytes first.
 * @param publicKey - The recipient's RSA-OAEP public key.
 * @returns The wrapped key, base64-encoded.
 */
export async function encryptKeyAsymmetric(keyToEncrypt: CryptoKey, publicKey: CryptoKey) {
    try {
        return encryptArrayBufferAsymmetric(await exportCryptoKeyToArrayBuffer(keyToEncrypt), publicKey);
    } catch (error) {
        throw new Error(`Public key encryption failed: ${(error as Error).message}`);
    }
}

/**
 * Encrypts `plaintext` with the given RSA-OAEP public key.
 * @returns The ciphertext, base64-encoded.
 */
export async function encryptAsymmetric(plaintext: string, publicKey: CryptoKey): Promise<string> {
    try {
        const plainTextBuffer = new TextEncoder().encode(plaintext);
        return encryptArrayBufferAsymmetric(plainTextBuffer.buffer, publicKey);
    } catch (error) {
        throw new Error(`Public key encryption failed: ${(error as Error).message}`);
    }
}

/** @internal Encrypts raw bytes with RSA-OAEP; shared by {@link encryptAsymmetric} and {@link encryptKeyAsymmetric}. */
async function encryptArrayBufferAsymmetric(data: ArrayBuffer, publicKey: CryptoKey): Promise<string> {
    return arrayBufferToBase64(await window.crypto.subtle.encrypt(
        {
            name: 'RSA-OAEP'
        },
        publicKey,
        data
    ));
}

/**
 * The same as {@link decryptAsymmetric}, but explicitly decrypts a CryptoKey instead of a string.
 * Counterpart to {@link encryptKeyAsymmetric} — used to unwrap an AES key that was
 * RSA-encrypted for this private key's owner (see `hybrid.ts`).
 *
 * NOTE: the unwrapped key is always re-imported as `AES-GCM`, since in this codebase
 * asymmetric encryption is only ever used to wrap symmetric AES keys (never other key
 * types). Do not reuse this for unwrapping non-AES keys.
 * @param ciphertext - The ciphertext to decrypt, base64-encoded.
 * @param privateKey - The private key to use for decryption
 * @return The decrypted symmetric key as a CryptoKey.
 */
export async function decryptKeyAsymmetric(ciphertext: string, privateKey: CryptoKey): Promise<CryptoKey> {
    try {
        return await window.crypto.subtle.importKey(
            'raw',
            await decryptArrayBufferAsymmetric(base64ToArrayBuffer(ciphertext), privateKey),
            {
                name: 'AES-GCM'
            },
            true, // Extractable
            ['encrypt', 'decrypt']
        );
    } catch (error) {
        throw new Error(`Decrypting a key asymmetrically failed: ${(error as Error).message || `${error}`}`);
    }
}

/**
 * Decrypts a base64-encoded ciphertext with the given RSA-OAEP private key.
 * Counterpart to {@link encryptAsymmetric}.
 */
export async function decryptAsymmetric(ciphertext: string, privateKey: CryptoKey): Promise<string> {
    try {
        const ciphertextBuffer = base64ToArrayBuffer(ciphertext);
        const decryptedBuffer = await decryptArrayBufferAsymmetric(ciphertextBuffer, privateKey);
        return new TextDecoder().decode(decryptedBuffer);
    } catch (error) {
        throw new Error(`Decrypting an asymmetric value failed: ${(error as Error).message || `${error}`}`);
    }
}

/** @internal Decrypts raw bytes with RSA-OAEP; shared by {@link decryptAsymmetric} and {@link decryptKeyAsymmetric}. */
function decryptArrayBufferAsymmetric(ciphertext: ArrayBuffer, privateKey: CryptoKey): Promise<ArrayBuffer> {
    return window.crypto.subtle.decrypt(
        {
            name: 'RSA-OAEP'
        },
        privateKey,
        ciphertext
    );
}

/**
 * Generates a new asymmetric key pair (RSA-OAEP 4096-bit, SHA-256, public exponent 65537).
 * Used once per user, typically on first login / keychain initialization
 * (see `keychainHandle.ts` `initializeNewKeychain`), to create the `publicKey`/`privateKey`
 * pair stored in the keychain.
 * @returns The generated key pair with public and private keys
 */
export async function generateAsymmetricKeyPair(): Promise<CryptoKeyPair> {
    try {
        return await window.crypto.subtle.generateKey(
            {
                name: 'RSA-OAEP',
                modulusLength: 4096,
                publicExponent: new Uint8Array([1, 0, 1]),
                hash: 'SHA-256'
            },
            true,
            ['encrypt', 'decrypt']
        );
    } catch (error) {
        throw new Error(`Failed to generate key pair: ${(error as Error).message}`);
    }
}
