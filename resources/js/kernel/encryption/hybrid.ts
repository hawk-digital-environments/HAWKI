/**
 * Hybrid encryption: AES-256-GCM for the payload, RSA-OAEP for the AES key.
 *
 * This is the right module for encrypting user data that must be decryptable by
 * the server (or another party who holds the private key). The AES key is encrypted
 * with the recipient's public RSA key, so only the private-key holder can recover it.
 *
 * Serialise with `.toString()` for storage or transmission; reconstruct with
 * {@link loadHybridCryptoValue}. The format is `base64(encryptedKey)|base64(iv|tag|ciphertext)`.
 *
 * Maps to `\App\Services\Crypto\Value\HybridCryptoValue` on the PHP side.
 *
 * @example
 * const encrypted = await encryptHybrid(plaintext, recipientPublicKey);
 * const stored = encrypted.toString(); // safe to send to the server
 *
 * const loaded = loadHybridCryptoValue(stored);
 * const recovered = await decryptHybrid(loaded, myPrivateKey);
 *
 * WHY the double base64-wrapping in `.toString()`: `value.toString()` (the symmetric
 * part) already contains its own `|`-separated `iv|tag|ciphertext` string. If it were
 * joined with `passphrase` using `|` directly, splitting the outer string on `|` would
 * yield more than the expected two parts. Wrapping each part in `btoa()` before joining
 * hides the inner `|` characters from the outer split, so `loadHybridCryptoValue` can
 * reliably `split('|')` into exactly two segments and `atob()` each one back out.
 */

import {
    decryptSymmetric,
    encryptSymmetric,
    generateSymmetricKey,
    loadSymmetricCryptoValue,
    type SymmetricCryptoValue
} from './symmetric.js';
import {decryptKeyAsymmetric, encryptKeyAsymmetric} from './asymmetric.js';

export interface HybridCryptoValue {
    /**
     * The one-time AES key (from {@link generateSymmetricKey}), RSA-OAEP-encrypted with
     * the recipient's public key and base64-encoded. Despite the name, this is NOT a
     * user-chosen passphrase — it is the wrapped symmetric key needed to decrypt `value`.
     * Only the holder of the matching private key can recover it (see {@link decryptKeyAsymmetric}).
     */
    passphrase: string;
    /** The actual payload, AES-256-GCM encrypted with the (unwrapped) key above. */
    value: SymmetricCryptoValue,
    /** Serialises to `base64(passphrase)|base64(value.toString())`, safe for storage/transmission. */
    toString: () => string;
}

/**
 * Internal helper function to create a HybridCryptoValue object.
 * @param passphrase
 * @param value
 */
function createHybridCryptoValue(passphrase: string, value: SymmetricCryptoValue): HybridCryptoValue {
    return {
        passphrase,
        value,
        toString: function () {
            return [this.passphrase, this.value.toString()].map(v => btoa(v)).join('|');
        }
    };
}

/**
 * Loads a HybridCryptoValue from a form generated when calling toString on a HybridCryptoValue.
 * @param ciphertext
 */
export function loadHybridCryptoValue(ciphertext: string): HybridCryptoValue {
    const cipherParts = ciphertext.split('|');
    if (cipherParts.length !== 2) {
        throw new Error('Invalid hybrid ciphertext format');
    }
    return createHybridCryptoValue(
        atob(cipherParts[0]),
        loadSymmetricCryptoValue(atob(cipherParts[1]))
    );
}

/**
 * Uses the best of both worlds: symmetric encryption for the data and asymmetric encryption for the passphrase.
 * This allows for efficient encryption of large data while maintaining the security of the passphrase.
 * @param plaintext - The plaintext to encrypt
 * @param publicKey - The asymmetric public key to use for encrypting the passphrase
 */
export async function encryptHybrid(plaintext: string, publicKey: CryptoKey): Promise<HybridCryptoValue> {
    const key = await generateSymmetricKey();
    // This value would be assignable to: \App\Services\Crypto\Value\HybridCryptoValue
    return createHybridCryptoValue(
        await encryptKeyAsymmetric(key, publicKey),
        await encryptSymmetric(plaintext, key)
    );
}

/**
 * Decrypts the hybrid encrypted value using the provided private key.
 * This method first decrypts the passphrase using the private key, then uses that passphrase to decrypt the symmetric value.
 * @param value - The value to decrypt
 * @param privateKey - The private key to use for decryption
 */
export async function decryptHybrid(value: HybridCryptoValue, privateKey: CryptoKey): Promise<string> {
    return await decryptSymmetric(
        value.value,
        await decryptKeyAsymmetric(value.passphrase, privateKey)
    );
}
