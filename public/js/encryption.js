let passKey;
let saltObj = {};


//#region Key Creation
async function generateKey() {
    const key = await window.crypto.subtle.generateKey(
        {
            name: 'AES-GCM',
            length: 256
        },
        true,
        ['encrypt', 'decrypt']
    );

    return key;
}

async function generateKeyPair() {
    const keyPair = await window.crypto.subtle.generateKey(
        {
            name: 'RSA-OAEP',
            modulusLength: 2048,
            publicExponent: new Uint8Array([1, 0, 1]),
            hash: 'SHA-256'
        },
        true,
        ['encrypt', 'decrypt']
    );

    return keyPair;
}

function generateTempHash() {
    const array = new Uint8Array(16); // 16 bytes = 128 bits
    window.crypto.getRandomValues(array);
    return Array.from(array).map(byte => byte.toString(16).padStart(2, '0')).join('');
}

function generatePasskeyBackupHash() {
    const array = new Uint8Array(8); // 8 bytes = 64 bits
    window.crypto.getRandomValues(array);
    return Array.from(array)
        .map(byte => byte.toString(16).padStart(2, '0'))
        .join('')
        .match(/.{1,4}/g)
        .join('-');
}

//NOTE: DERIVEKEY MISTAKE?
async function deriveKey(passkey, label, serverSalt) {
    if (passkey instanceof CryptoKey) {
        passkey = arrayBufferToBase64(await window.crypto.subtle.exportKey('raw', passkey));
    }

    const enc = new TextEncoder();

    const keyMaterial = await window.crypto.subtle.importKey(
        'raw',
        enc.encode(passkey),
        {name: 'PBKDF2'},
        false,
        ['deriveKey']
    );

    // Combine label and serverSalt to create a unique salt for this derived key
    const combinedSalt = new Uint8Array([
        ...new TextEncoder().encode(label),
        ...new Uint8Array(serverSalt)
    ]);

    const derivedKey = await window.crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt: combinedSalt,
            iterations: 100000,
            hash: 'SHA-256'
        },
        keyMaterial,
        {name: 'AES-GCM', length: 256},
        true,
        ['encrypt', 'decrypt']
    );

    return derivedKey;
}

//#endregion


//#region Encyrption


//#region Symmetric
async function encryptWithSymKey(encKey, data, isKey = false) {
    const iv = window.crypto.getRandomValues(new Uint8Array(12)); // 12-byte IV

    // If the data is a key (binary), skip text encoding
    const encodedData = isKey ? data : new TextEncoder().encode(data);

    // Encrypt the data
    const encryptedData = await window.crypto.subtle.encrypt(
        {
            name: 'AES-GCM',
            iv: iv
        },
        encKey, // Symmetric key
        encodedData // Data to encrypt
    );

    // Extract the authentication tag (last 16 bytes)
    const tag = encryptedData.slice(-16);
    const ciphertext = encryptedData.slice(0, encryptedData.byteLength - 16);

    // Return ciphertext, iv, and tag as Base64 encoded
    return {
        ciphertext: arrayBufferToBase64(ciphertext),
        iv: arrayBufferToBase64(iv),
        tag: arrayBufferToBase64(tag)
    };
}


async function decryptWithSymKey(encKey, ciphertext, iv, tag, isKey = false) {
    // Convert Base64-encoded ciphertext, IV, and tag back to ArrayBuffers
    const ciphertextBuffer = base64ToArrayBuffer(ciphertext);
    const ivBuffer = base64ToArrayBuffer(iv);
    const tagBuffer = base64ToArrayBuffer(tag);

    // Recombine ciphertext and tag (AES-GCM requires them together for decryption)
    const combinedBuffer = new Uint8Array(ciphertextBuffer.byteLength + tagBuffer.byteLength);
    combinedBuffer.set(new Uint8Array(ciphertextBuffer), 0);
    combinedBuffer.set(new Uint8Array(tagBuffer), ciphertextBuffer.byteLength);

    try {
        // Decrypt the combined ciphertext and tag
        const decryptedData = await window.crypto.subtle.decrypt(
            {
                name: 'AES-GCM',
                iv: ivBuffer
            },
            encKey, // Symmetric key
            combinedBuffer // Combined ciphertext + tag
        );

        // Return decrypted data (binary or text based on isKey)
        return isKey ? new Uint8Array(decryptedData) : new TextDecoder().decode(decryptedData);
    } catch (error) {
        // console.error("Decryption failed:", error);
        throw new Error('Decryption failed: ' + error.message);
    }
}

//#endregion


//#region Asymmetric

async function encryptWithPublicKey(roomKey, publicKey) {

    // Export the roomKey (CryptoKey) to raw format (ArrayBuffer)
    const exportedRoomKey = await exportSymmetricKey(roomKey);

    // Import the recipient's public key
    const importedPublicKey = publicKey instanceof CryptoKey
        ? publicKey
        : await window.crypto.subtle.importKey(
            'spki', // Key format
            publicKey, // Recipient's public key in ArrayBuffer format
            {
                name: 'RSA-OAEP',
                hash: {name: 'SHA-256'}
            },
            false, // Not extractable
            ['encrypt']
        );

    // Encrypt the exported roomKey using the recipient's public key
    const encryptedRoomKey = await window.crypto.subtle.encrypt(
        {
            name: 'RSA-OAEP'
        },
        importedPublicKey,
        exportedRoomKey // The raw bytes of the roomKey
    );

    // Return the encrypted roomKey as Base64 string
    return {
        ciphertext: arrayBufferToBase64(encryptedRoomKey)
    };
}

async function decryptWithPrivateKey(encryptedData, privateKey) {
    // Import the user's private key
    const importedPrivateKey = privateKey instanceof CryptoKey
        ? privateKey
        : await window.crypto.subtle.importKey(
            'pkcs8', // Key format
            privateKey, // User's private key in ArrayBuffer format
            {
                name: 'RSA-OAEP',
                hash: {name: 'SHA-256'}
            },
            false, // Not extractable
            ['decrypt']
        );

    // Decrypt the encrypted roomKey
    const decryptedRoomKeyBytes = await window.crypto.subtle.decrypt(
        {
            name: 'RSA-OAEP'
        },
        importedPrivateKey,
        encryptedData // Encrypted symmetric key in ArrayBuffer format
    );

    // Import the decrypted bytes back into a CryptoKey object
    const roomKey = await window.crypto.subtle.importKey(
        'raw',
        decryptedRoomKeyBytes,
        {
            name: 'AES-GCM'
        },
        true, // Extractable
        ['encrypt', 'decrypt']
    );

    // Return the reconstructed roomKey (CryptoKey object)
    return roomKey;
}

//#endregion

//#region Hybrid
/**
 * Uses the best of both worlds: symmetric encryption for the data and asymmetric encryption for the passphrase.
 * This allows for efficient encryption of large data while maintaining the security of the passphrase.
 * @param {string} data
 * @param {string} publicKey
 * @returns {Promise<{
 *     passphrase: string,
 *     value: {
 *         ciphertext: string,
 *         iv: string,
 *         tag: string,
 *     },
 *     toString: function(): string
 * }>}
 */
async function encryptWithHybrid(data, publicKey) {
    const passphrase = await generateKey();
    const encryptedData = await encryptWithSymKey(passphrase, data, false);
    const encryptedPassphrase = await encryptWithPublicKey(passphrase, base64ToArrayBuffer(publicKey));
    // This value would be assignable to: \App\Services\Crypto\Value\HybridCryptoValue
    return {
        passphrase: encryptedPassphrase.ciphertext,
        value: encryptedData,
        toString: function () {
            const {iv, ciphertext, tag} = this.value;
            const valueString = [iv, tag, ciphertext].map(v => btoa(v)).join('|');
            return [this.passphrase, valueString].map(v => btoa(v)).join('|');
        }
    };
}

/**
 * Decrypts the hybrid encrypted value using the provided private key.
 * This method first decrypts the passphrase using the private key, then uses that passphrase to decrypt the symmetric value.
 * @param {{
 *     passphrase: string,
 *     value: {
 *         ciphertext: string,
 *         iv: string,
 *         tag: string,
 *     },
 *     toString: function(): string
 * }|string} ciphertext
 * @param {string} privateKey
 * @return {Promise<string>} The decrypted data
 */
async function decryptWithHybrid(ciphertext, privateKey) {
    if (typeof ciphertext === 'string') {
        const cipherParts = ciphertext.split('|');
        if (cipherParts.length !== 2) {
            throw new Error('Invalid hybrid ciphertext format');
        }
        const passphraseCiphertext = atob(cipherParts[0]);
        const valueParts = cipherParts[1].split('|').map(part => atob(part));
        ciphertext = {
            passphrase: passphraseCiphertext,
            value: {
                iv: valueParts[0],
                tag: valueParts[1],
                ciphertext: valueParts[2]
            },
            toString: function () {
                return ''; // This method is not used in the decryption process, but can be implemented if needed.
            }
        };
    }

    if (!ciphertext || !ciphertext.passphrase || typeof ciphertext.value !== 'object' || !ciphertext.value.ciphertext || !ciphertext.value.iv || !ciphertext.value.tag) {
        throw new Error('Invalid hybrid ciphertext format');
    }

    const passphrase = await decryptWithPrivateKey(ciphertext.passphrase, privateKey, true);
    return await decryptWithSymKey(
        passphrase,
        ciphertext.value.ciphertext,
        ciphertext.value.iv,
        ciphertext.value.tag,
        false
    );
}

//#endregion


//#region HASH KEYS
async function encryptWithTempHash(roomKey, tempHash) {


    const exportedRoomKey = await exportSymmetricKey(roomKey);


    // Fetch server salt
    const severSalt = await fetchServerSalt('INVITATION_SALT');

    // Derive a key from the temporary hash
    const derivedKey = await deriveKey(tempHash, 'invitation_key', severSalt);

    // Encrypt the room key using the derived key
    const encryptedRoomKeyData = await encryptWithSymKey(derivedKey, exportedRoomKey, true);


    // Return both IV and the encrypted ciphertext (including tag)
    return {
        tag: encryptedRoomKeyData.tag,
        iv: encryptedRoomKeyData.iv, // IV is kept separate
        ciphertext: encryptedRoomKeyData.ciphertext // Ciphertext and tag are combined
    };
}


async function decryptWithTempHash(encryptedData, tempHash, iv, tag) {

    //fetch server salt
    const severSalt = await fetchServerSalt('INVITATION_SALT');

    // Derive the key from the temporary hash using the salt
    const derivedKey = await deriveKey(tempHash, 'invitation_key', severSalt);

    // Decrypt the data
    const decryptedData = await decryptWithSymKey(derivedKey, encryptedData, iv, tag, true);

    const roomKey = importSymmetricKey(decryptedData);

    return roomKey; // Return the original room key
}


//#endregion


//#endregion


//#region Keychain Access

async function keychainSet(key, value, type = 'room_key') {
    if (!(value instanceof CryptoKey)) {
        throw new Error('Value must be a CryptoKey');
    }

    if (typeof type !== 'string') {
        console.warn('Migration: Using keychainSet with non-string type. Defaulting to "room_key".');
        type = 'room_key';
    }

    const keychain = await getKeychain();

    const existingValue = keychain.find(kv => kv.key === key);
    const newValue = await createKeychainValue(key, value, existingValue ? existingValue.type : type);
    if (existingValue && existingValue.type === newValue.type && existingValue.value === newValue.value) {
        console.log(`Keychain entry for key "${key}" is unchanged. Skipping update.`);
        return;
    }

    const encryptedNewValue = await encryptKeychainValue(
        await createKeychainEncryptor(await getPassKey()),
        newValue
    );

    await sendKeychainValuesToServer({set: [encryptedNewValue]});

    // Update local keychain
    const idx = keychain.findIndex(kv => kv.key === key);
    if (idx === -1) {
        keychain.push(newValue);
    } else {
        keychain.splice(idx, 1, newValue);
    }

    console.log(`Keychain entry for key "${key}" updated successfully.`);
}

async function keychainGet(key, keyType = 'room_key') {
    const keychain = await getKeychain();

    // Backward compatibility for well known keys
    if (key === 'publicKey') {
        keyType = 'public_key';
    } else if (key === 'privateKey') {
        keyType = 'private_key';
    } else if (key === 'aiConvKey') {
        keyType = 'ai_conv';
    }

    const value = keychain.find(kv => kv.key === key && kv.type === keyType);
    if (!value) {
        console.warn(`Key "${key}" not found in keychain.`);
        return null;
    }

    const {type, value: valueBuffer} = value;

    /**
     * @var CryptoKey | null loadedValue
     */
    let loadedValue = null;
    if (type === 'public_key') {
        loadedValue = await window.crypto.subtle.importKey(
            'spki',
            valueBuffer,
            {
                name: 'RSA-OAEP',
                hash: {name: 'SHA-256'}
            },
            false,
            ['encrypt']
        );
    } else if (type === 'private_key') {
        loadedValue = await window.crypto.subtle.importKey(
            'pkcs8',
            valueBuffer,
            {
                name: 'RSA-OAEP',
                hash: {name: 'SHA-256'}
            },
            false,
            ['decrypt']
        );
    } else {
        loadedValue = await importSymmetricKey(valueBuffer);
    }

    return loadedValue;
}

/**
 * Creates a new keychain value
 * @param {string} key
 * @param {CryptoKey} value
 * @param {string} type
 * @return {Promise<{key:string,value:ArrayBuffer,type:string}>}
 */
async function createKeychainValue(key, value, type = 'room_key') {
    console.log('Creating keychain value:', {key, value, type});
    let buffer;

    if (key === 'publicKey' || type === 'public_key') {
        type = 'public_key';
        buffer = await window.crypto.subtle.exportKey('spki', value);
    } else if (key === 'privateKey' || type === 'private_key') {
        type = 'private_key';
        buffer = await window.crypto.subtle.exportKey('pkcs8', value);
    } else if (type === 'room_key' || type === 'ai_conv') {
        buffer = await window.crypto.subtle.exportKey('raw', value);
    } else {
        throw new Error('Invalid key type for keychain value');
    }

    return {key, value: buffer, type};
}

/**
 * Encrypts the given keychain value using the provided encryptor key
 *
 * @param {CryptoKey} keychainEncryptor
 * @param {{key: string, value: ArrayBuffer, type: string}}value
 * @return {Promise<{key: string, value: string, type: string}>}
 */
async function encryptKeychainValue(keychainEncryptor, value) {
    const encryptedValue = await encryptWithSymKey(keychainEncryptor, arrayBufferToBase64(value.value), false);
    return {
        ...value,
        value: [encryptedValue.iv, encryptedValue.tag, encryptedValue.ciphertext].join('|')
    };
}

/**
 * Decrypts the given keychain value using the provided encryptor key
 * @param {CryptoKey} keychainEncryptor
 * @param {{key: string, value: string, type: string}}value
 * @return {Promise<{key: string, value: ArrayBuffer, type: string}>}
 */
async function decryptKeychainValue(keychainEncryptor, value) {
    const [iv, tag, ciphertext] = value.value.split('|');

    const decrypted = await decryptWithSymKey(
        keychainEncryptor,
        ciphertext,
        iv,
        tag,
        false
    );

    return {...value, value: base64ToArrayBuffer(decrypted)};
}

/**
 * Initializes a new keychain for the user
 * @return {Promise<void>}
 */
async function initializeNewKeychain() {
    const passKey = await getPassKey();
    const keychainEncryptor = await createKeychainEncryptor(passKey);
    const keyPair = await generateKeyPair();

    const initialValues = [];
    initialValues.push(
        await encryptKeychainValue(
            keychainEncryptor,
            await createKeychainValue(
                'publicKey',
                keyPair.publicKey,
                'public_key'
            )
        )
    );
    initialValues.push(
        await encryptKeychainValue(
            keychainEncryptor,
            await createKeychainValue(
                'privateKey',
                keyPair.privateKey,
                'private_key'
            )
        )
    );
    initialValues.push(
        await encryptKeychainValue(
            keychainEncryptor,
            await createKeychainValue(
                'aiConvKey',
                await generateKey(),
                'ai_conv'
            )
        )
    );

    const publicKeyString = arrayBufferToBase64(
        await window.crypto.subtle.exportKey('spki', keyPair.publicKey)
    );

    await sendKeychainValuesToServer({
        set: initialValues,
        clear: true,
        publicKey: publicKeyString
    });
}

/**
 * Generates the crypto key to use for keychain encryption/decryption
 * @param passkey
 * @return {Promise<CryptoKey>}
 */
async function createKeychainEncryptor(passkey) {
    const udSalt = await fetchServerSalt('USERDATA_ENCRYPTION_SALT');
    return await deriveKey(passkey, 'keychain_encryptor', udSalt);
}

let __decryptionValidator = null;

/**
 * Checks if the given passkey can decrypt at least one keychain value successfully
 * This implies that the given passkey is valid
 * @param {string} passkey
 * @return {Promise<boolean>}
 */
async function canPasskeyDecryptKeychain(passkey) {
    if (!__decryptionValidator) {
        const response = await fetch('/keychain/validator', {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });
        if (!response.ok) {
            throw new Error(`Server responded with status ${response.status}`);
        }
        const json = await response.json();
        if (!json.success || !json.validator) {
            throw new Error('Server indicated failure: ' + (json.error || 'Unknown error'));
        }
        __decryptionValidator = json.validator;
    }

    const encryptor = await createKeychainEncryptor(passkey);
    const [iv, tag, ciphertext] = __decryptionValidator.split('|');

    try {
        await decryptWithSymKey(
            encryptor,
            ciphertext,
            iv,
            tag,
            false  // Expecting text output
        );
        return true;
    } catch (error) {
        console.error('Invalid passkey, decryption failed:', error);
        return false;
    }
}

/**
 * @return {Promise<{key:string,value:string,type:string}[]|null>}
 */
async function getRawKeychainValues() {
    const response = await fetch('/keychain', {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    });

    if (!response.ok) {
        throw new Error(`Server responded with status ${response.status}`);
    }

    const json = await response.json();

    if (!Array.isArray(json) || json.length === 0) {
        return null;
    }

    return json;
}

/**
 * @return {Promise<string|null>}
 */
async function getRawLegacyKeychain() {
    const response = await fetch('/keychain/legacy', {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    });

    if (!response.ok) {
        throw new Error(`Server responded with status ${response.status}`);
    }

    if (response.status === 204) {
        return null;
    }

    const json = await response.json();
    if (!json.success) {
        throw new Error('Server indicated failure: ' + (json.error || 'Unknown error'));
    }

    return json.keychain;
}

let __loadedKeychain = null;

/**
 * Returns the decrypted keychain entries
 * @return {Promise<{key:string,value:ArrayBuffer,type:string}[]>}
 */
async function getKeychain() {
    if (__loadedKeychain) {
        return __loadedKeychain;
    }

    const markAsMigrated = async () => {
        await fetch('/keychain/markAsMigrated', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });
    };

    /**
     *
     * @param {CryptoKey} keychainEncryptor
     * @return {Promise<void>}
     */
    const migrateLegacyKeychain = async (
        keychainEncryptor
    ) => {
        const legacyKeychain = await getRawLegacyKeychain();
        if (!legacyKeychain) {
            console.log('No legacy keychain to migrate.');
            return null;
        }

        console.log('Legacy keychain found, migrating...');
        const [iv, tag, ciphertext] = legacyKeychain.split('|');
        const decryptedKeychain = JSON.parse(
            await decryptWithSymKey(
                keychainEncryptor,
                ciphertext,
                iv,
                tag,
                false  // Expecting text output
            )
        );

        const keysToIgnore = ['username', 'time-signature'];
        const valuesToSet = [];
        for (const [key, value] of Object.entries(decryptedKeychain)) {
            if (keysToIgnore.includes(key)) {
                continue;
            }

            console.log(`Migrating key: ${key}`);
            try {
                let loadedValue;
                if (key === 'publicKey') {
                    loadedValue = await window.crypto.subtle.importKey(
                        'spki',
                        base64ToArrayBuffer(value),
                        {
                            name: 'RSA-OAEP',
                            hash: {name: 'SHA-256'}
                        },
                        true,
                        ['encrypt']
                    );
                } else if (key === 'privateKey') {
                    loadedValue = await window.crypto.subtle.importKey(
                        'pkcs8',
                        base64ToArrayBuffer(value),
                        {
                            name: 'RSA-OAEP',
                            hash: {name: 'SHA-256'}
                        },
                        true,
                        ['decrypt']
                    );
                } else {
                    loadedValue = await importKeyValueFromJWK(value);
                }

                valuesToSet.push(
                    await encryptKeychainValue(
                        keychainEncryptor,
                        await createKeychainValue(
                            key,
                            loadedValue,
                            key === 'aiConvKey' ? 'ai_conv' : 'room_key'
                        )
                    )
                );
            } catch (error) {
                console.error(`Error importing key "${key}" from legacy keychain:`, error);
                throw error;
            }
        }

        try {
            await sendKeychainValuesToServer({
                set: valuesToSet,
                clear: true
            });
        } catch (error) {
            console.error('Error sending migrated keychain values to server:', error);
            throw error;
        }

        await markAsMigrated();

        console.log('Legacy keychain migration completed successfully.');

        return await getRawKeychainValues();
    };

    const passKey = await getPassKey();
    const keychainEncryptor = await createKeychainEncryptor(passKey);

    let entries = await getRawKeychainValues();

    if (!entries) {
        entries = await migrateLegacyKeychain(keychainEncryptor);
    }

    if (!entries) {
        console.log('No keychain entries found, initializing a new keychain.');
        await initializeNewKeychain();
        entries = await getRawKeychainValues();
    }

    return __loadedKeychain = await Promise.all(entries.map(i => decryptKeychainValue(keychainEncryptor, i)));
}

/**
 * @param {{
 *     set: {key: string, value: string, type: string}[]|undefined,
 *     remove: {key: string, type: string}[]|undefined,
 *     clear: boolean|undefined
 *     publicKey: string|undefined
 * }} body
 */
async function sendKeychainValuesToServer(body) {
    const response = await fetch('/keychain', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify(body)
    });

    if (!response.ok) {
        throw new Error(`Server responded with status ${response.status}`);
    }

    const json = await response.json();
    if (!json.success) {
        throw new Error('Server indicated failure: ' + (json.error || 'Unknown error'));
    }

    console.log('Keychain values successfully sent to server.');
}



//#endregion


//#region Utilities


//fetches server salt with label
async function fetchServerSalt(saltLabel) {

    if (saltObj[saltLabel]) {
        const salt = saltObj[saltLabel];
        const serverSalt = Uint8Array.from(atob(salt), c => c.charCodeAt(0));
        return serverSalt;
    }


    try {
        // Make a GET request to the server with saltlabel in the headers
        const response = await fetch('/req/crypto/getServerSalt', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',  // Optional for GET, but useful to include
                'saltlabel': saltLabel,              // Pass saltlabel as a custom header
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });

        // Check if the server responded with an error
        if (!response.ok) {
            const errorData = await response.json();
            console.error('Server Error:', errorData.error);
            throw new Error(`Server Error: ${errorData.error}`);
        }

        // Parse the JSON response
        const data = await response.json();

        // Convert the base64-encoded salt to a Uint8Array
        const serverSalt = Uint8Array.from(atob(data.salt), c => c.charCodeAt(0));
        saltObj[saltLabel] = data.salt;
        return serverSalt;

    } catch (error) {
        console.error('Error fetching salt:', error);
        throw error;
    }
}


function arrayBufferToBase64(buffer) {
    const binary = String.fromCharCode.apply(null, new Uint8Array(buffer));
    return btoa(binary);
}

function base64ToArrayBuffer(base64) {
    const binary = atob(base64);
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

async function exportKeyValueToJWK(keyValue) {
    return await window.crypto.subtle.exportKey('jwk', keyValue);
}

async function importKeyValueFromJWK(jwk) {
    try {
        const value = await window.crypto.subtle.importKey(
            'jwk',
            jwk,
            {
                name: 'AES-GCM',
                length: 256
            },
            true,
            ['encrypt', 'decrypt']
        );
        return value;
    } catch {
        return jwk;
    }

}


async function exportSymmetricKey(key) {
    return await window.crypto.subtle.exportKey('raw', key);
}

async function importSymmetricKey(decryptedRoomKey) {
    if (decryptedRoomKey.byteLength !== 16 && decryptedRoomKey.byteLength !== 32) {
        throw new Error('Decrypted AES key must be 128 or 256 bits');
    }

    return await window.crypto.subtle.importKey(
        'raw',
        decryptedRoomKey, // The decrypted AES key in ArrayBuffer format
        {
            name: 'AES-GCM',
            length: decryptedRoomKey.byteLength * 8 // Convert byteLength to bits
        },
        true, // The key can be extracted (optional)
        ['encrypt', 'decrypt']
    );
}

//#endregion

//#region PassKey
async function getPassKey() {

    if (passKey) {
        return passKey;
    } else {
        try {
            const keyData = localStorage.getItem(`${userInfo.username}PK`);
            const keyJson = JSON.parse(keyData);
            const salt = await fetchServerSalt('PASSKEY_SALT');
            const key = await deriveKey(userInfo.email, userInfo.username, salt);

            passKey = await decryptWithSymKey(key, keyJson.ciphertext, keyJson.iv, keyJson.tag, false);

            if (await canPasskeyDecryptKeychain(passKey)) {
                return passKey;
            } else {
                return null;
            }
        } catch (error) {
            console.log('Passkey not found:', error);
            return null;
        }
    }

}

async function setPassKey(enteredKey) {
    if (enteredKey === '') {
        return null;
    }
    const salt = await fetchServerSalt('PASSKEY_SALT');
    //NOTE: USER INFO AND EMAIL SHOULD BE CHANGED TO SOMETHING PROPER
    const key = await deriveKey(userInfo.email, userInfo.username, salt);

    const encryptedPassKeyData = await encryptWithSymKey(key, enteredKey, false);

    localStorage.setItem(`${userInfo.username}PK`, JSON.stringify(encryptedPassKeyData));
    passKey = enteredKey;
}

//#endregion


async function cleanupUserData(callback) {
    try {
        // Cleanup localStorage
        if (localStorage.getItem(`${userInfo.username}PK`)) {
            localStorage.removeItem(`${userInfo.username}PK`);
        }


        console.log('Cleanup completed successfully.');

        // If a callback is provided, invoke it
        if (callback && typeof callback === 'function') {
            callback();
        }
    } catch (error) {
        console.error('Error during cleanup:', error);

        // Optional: Invoke callback with an error
        if (callback && typeof callback === 'function') {
            callback(error);
        }
    }
}
