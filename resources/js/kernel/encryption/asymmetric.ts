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
 * Loads a public key from a base64-encoded string or ArrayBuffer.
 * @param keyString
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

export async function exportPublicKeyToString(publicKey: CryptoKey): Promise<string> {
    return exportCryptoKeyToString(publicKey, 'spki');
}

/**
 * Loads a private key from a base64-encoded string or ArrayBuffer.
 * @param keyString
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

export async function exportPrivateKeyToString(privateKey: CryptoKey): Promise<string> {
    return exportCryptoKeyToString(privateKey, 'pkcs8');
}

/**
 * The same as encryptAsymmetric, but explicitly encrypts a CryptoKey instead of a string
 * @param keyToEncrypt
 * @param publicKey
 */
export async function encryptKeyAsymmetric(keyToEncrypt: CryptoKey, publicKey: CryptoKey) {
    try {
        return encryptArrayBufferAsymmetric(await exportCryptoKeyToArrayBuffer(keyToEncrypt), publicKey);
    } catch (error) {
        throw new Error(`Public key encryption failed: ${(error as Error).message}`);
    }
}

/**
 * Encrypts data using the provided public key.
 * This method uses the RSA public key to encrypt the data.
 * @param plaintext - The data to encrypt.
 * @param publicKey - he public key to use for encryption.
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

/**
 * Internal function to encrypt an ArrayBuffer using a public key with RSA-OAEP
 * @internal
 * @param data
 * @param publicKey
 */
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
 * The same as decryptAsymmetric, but explicitly decrypts a CryptoKey instead of a string
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
 * Decrypts data using the provided private key.
 * This method uses the RSA private key to decrypt the data.
 * @param ciphertext - The ciphertext to decrypt, base64-encoded.
 * @param privateKey - The private key to use for decryption
 * @return The decrypted plaintext.
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

/**
 * Decrypts an ArrayBuffer using a private key with RSA-OAEP
 * @internal
 * @param ciphertext
 * @param privateKey
 */
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
 * Generates a new asymmetric key pair (RSA-OAEP 2048-bit)
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
