# Encryption

HAWKI uses the browser's native **Web Crypto API** (`window.crypto.subtle`) for end-to-end encryption. The `resources/js/encryption/` directory provides a thin typed wrapper that keeps all crypto primitives in one place, enforces consistent serialisation formats, and prevents accidental direct use of `window.crypto.subtle` in feature code.

The serialised formats produced by these helpers are wire-compatible with the PHP value objects in [hawki-crypto](https://github.com/hawk-digital-environments/hawki-crypto), which means a value encrypted in the browser can be decrypted server-side without any transformation.

**Contributors must never call `window.crypto.subtle` directly.** Use the helpers described below.

## Choosing the Right Module

| Scenario | Module |
|---|---|
| Both sides share the same key (e.g. derived from a passkey) | `symmetric.ts` |
| Encrypt a short value (typically an AES key) for a recipient's RSA public key | `asymmetric.ts` |
| Encrypt arbitrary-length plaintext for a recipient's RSA public key | `hybrid.ts` |

The RSA primitives in `asymmetric.ts` have a hard limit on how much data they can encrypt in a single operation (roughly 446 bytes for a 4096-bit key with SHA-256). Always use `hybrid.ts` when the plaintext length is not tightly bounded.

## Symmetric Encryption (`symmetric.ts`)

AES-256-GCM with a 12-byte random IV and a 16-byte authentication tag. Use this module when both the encrypting and decrypting party share the same key, for example a key derived from the user's passkey.

### Key Generation

```ts
import { generateSymmetricKey } from '$lib/encryption/symmetric.js';

const key = await generateSymmetricKey();
// Returns an extractable CryptoKey usable for AES-GCM encrypt/decrypt.
```

### Encrypting and Decrypting Strings

```ts
import {
    generateSymmetricKey,
    encryptSymmetric,
    decryptSymmetric,
    loadSymmetricCryptoValue
} from '$lib/encryption/symmetric.js';

const key = await generateSymmetricKey();
const encrypted = await encryptSymmetric('hello', key);
const stored = encrypted.toString(); // safe to store or transmit

const loaded = loadSymmetricCryptoValue(stored);
const plaintext = await decryptSymmetric(loaded, key); // 'hello'
```

`encryptSymmetric(plaintext, key)` returns a `SymmetricCryptoValue`. `decryptSymmetric(value, key)` returns the original string.

### Encrypting and Decrypting CryptoKeys

When you need to wrap one key inside another (e.g. storing an RSA private key encrypted with the user's AES key), use the key-specific helpers:

```ts
import { encryptKeySymmetric, decryptKeySymmetric } from '$lib/encryption/symmetric.js';

const wrappedKey = await encryptKeySymmetric(privateKey, userAesKey);
// wrappedKey is a SymmetricCryptoValue — serialise with .toString()

const recovered = await decryptKeySymmetric(wrappedKey, userAesKey);
// recovered is a CryptoKey
```

### The `SymmetricCryptoValue` Type

```ts
interface SymmetricCryptoValue {
    ciphertext: ArrayBuffer;
    iv: ArrayBuffer;
    tag: ArrayBuffer;
    toObject: () => { ciphertext: string; iv: string; tag: string };
    toString: () => string;  // "base64(iv)|base64(tag)|base64(ciphertext)"
    toJson: () => string;    // JSON of toObject()
}
```

The serialised string format from `.toString()` is `base64(iv)|base64(tag)|base64(ciphertext)`. This is the format expected by `\App\Services\Crypto\Value\SymmetricCryptoValue` on the PHP side.

### Loaders

Multiple loaders reconstruct a `SymmetricCryptoValue` from different storage formats:

| Function | Input |
|---|---|
| `loadSymmetricCryptoValue(str)` | Pipe-delimited string from `.toString()` |
| `loadSymmetricCryptoValueFromJson(str \| obj)` | JSON string or `{ciphertext, iv, tag}` object from `.toJson()` / `.toObject()` |
| `loadSymmetricCryptoValueFromStrings(ciphertext, iv, tag)` | Three separate base64 strings |
| `loadSymmetricCryptoValueFromObject(obj)` | `{ciphertext, iv, tag}` plain object |

## Asymmetric Encryption (`asymmetric.ts`)

RSA-OAEP 4096-bit with SHA-256. This module is intended only for short values — in practice, encrypting AES keys. For arbitrary-length data use `hybrid.ts`.

### Key Generation and Export

```ts
import {
    generateAsymmetricKeyPair,
    exportPublicKeyToString,
    exportPrivateKeyToString
} from '$lib/encryption/asymmetric.js';

const { publicKey, privateKey } = await generateAsymmetricKeyPair();
const pubBase64 = await exportPublicKeyToString(publicKey);   // SPKI, base64
const privBase64 = await exportPrivateKeyToString(privateKey); // PKCS#8, base64
// Send pubBase64 to the server; store privBase64 encrypted with the user's AES key.
```

### Loading Keys

```ts
import { loadPublicKey, loadPrivateKey } from '$lib/encryption/asymmetric.js';

// Public key: accepts base64 SPKI string or ArrayBuffer.
// The second argument controls whether the imported key is extractable (default: false).
const pubKey = await loadPublicKey(serverPublicKeyBase64);

// Private key: accepts base64 PKCS#8 string or ArrayBuffer.
const privKey = await loadPrivateKey(storedPrivateKeyBase64);
```

Both loaders default to `extractable: false`, which prevents the key material from being exported again after import. Pass `true` only when you explicitly need to re-export the key.

### Encrypting and Decrypting Strings

`encryptAsymmetric` and `decryptAsymmetric` operate on strings and return/accept a base64-encoded ciphertext:

```ts
import { encryptAsymmetric, decryptAsymmetric } from '$lib/encryption/asymmetric.js';

const ciphertext = await encryptAsymmetric('short value', pubKey); // base64 string
const plaintext = await decryptAsymmetric(ciphertext, privKey);
```

### Encrypting and Decrypting CryptoKeys

```ts
import { encryptKeyAsymmetric, decryptKeyAsymmetric } from '$lib/encryption/asymmetric.js';

const encryptedKey = await encryptKeyAsymmetric(aesKey, pubKey); // base64 string
const recoveredKey = await decryptKeyAsymmetric(encryptedKey, privKey); // CryptoKey
```

`decryptKeyAsymmetric` re-imports the raw key bytes directly as an AES-GCM key, so the returned `CryptoKey` is ready for symmetric operations without any further conversion.

## Hybrid Encryption (`hybrid.ts`)

RSA-OAEP + AES-256-GCM. This is the correct module for encrypting user data that must be readable by the server or another party who holds a specific RSA private key.

Internally, `encryptHybrid` generates a fresh AES-256-GCM key for every operation, encrypts the plaintext with that key, then encrypts the AES key with the recipient's RSA public key. Only the holder of the corresponding private key can recover the AES key and therefore the plaintext.

### Encrypting

```ts
import { encryptHybrid, loadHybridCryptoValue } from '$lib/encryption/hybrid.js';
import { loadPublicKey } from '$lib/encryption/asymmetric.js';

const pubKey = await loadPublicKey(serverPublicKeyBase64);
const encrypted = await encryptHybrid('secret data', pubKey);
const stored = encrypted.toString(); // send to server or store
```

### Decrypting

```ts
import { decryptHybrid, loadHybridCryptoValue } from '$lib/encryption/hybrid.js';
import { loadPrivateKey } from '$lib/encryption/asymmetric.js';

const privKey = await loadPrivateKey(myPrivateKeyBase64);
const loaded = loadHybridCryptoValue(stored);
const plaintext = await decryptHybrid(loaded, privKey);
```

### The `HybridCryptoValue` Type

```ts
interface HybridCryptoValue {
    passphrase: string;         // RSA-encrypted AES key, base64
    value: SymmetricCryptoValue; // AES-GCM encrypted payload
    toString: () => string;      // "base64(encryptedAesKey)|base64(iv|tag|ciphertext)"
}
```

The serialised string format from `.toString()` is `base64(encryptedAesKey)|base64(symmetricPayload)`. This is the format expected by `\App\Services\Crypto\Value\HybridCryptoValue` on the PHP side.

`loadHybridCryptoValue(str)` reconstructs a `HybridCryptoValue` from a `.toString()` output. It is the only supported deserialisation path.

## Key Derivation (`utils.ts`)

`utils.ts` also exposes `deriveKey`, which turns a user's passkey string into an AES-256-GCM `CryptoKey` via PBKDF2 (100 000 iterations, SHA-256). Always pass the server salt from the connection config to prevent offline dictionary attacks.

```ts
import { deriveKey, loadServerSalt } from '$lib/encryption/utils.js';

const salt = loadServerSalt(connection.crypto_salt);
const aesKey = await deriveKey(userPasskey, 'keychain', salt);
```

The `label` parameter is concatenated with the server salt before derivation, which means the same passkey with different labels produces independent keys. This makes it safe to derive separate keys for separate purposes (e.g. `'keychain'`, `'session'`) from a single passkey without key reuse.

The `utils.ts` module is used internally by the other modules — feature code should not import from it directly.

## PHP Compatibility

`HybridCryptoValue.toString()` and `SymmetricCryptoValue.toString()` produce strings that the corresponding PHP value objects in [hawki-crypto](https://github.com/hawk-digital-environments/hawki-crypto) can deserialise directly. This is the standard path for sending encrypted data to the API or storing it for server-side decryption. See the hawki-crypto README for the PHP-side API.

## See Also

For key management — loading, storing, and rotating the user's crypto keys — see `data/keychain/keychainHandle.ts`, documented in the Data Layer section.
