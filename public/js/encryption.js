/** @type {string|undefined} */
let passKey;

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
    // Double encode severSalt for legacy reasons
    if (typeof serverSalt === 'string') {
        serverSalt = Uint8Array.from(serverSalt, c => c.charCodeAt(0));
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


//#region HASH KEYS
async function encryptWithTempHash(roomKey, tempHash) {


    const exportedRoomKey = await exportSymmetricKey(roomKey);


    // Fetch server salt
    const serverSalt = window.getConfig().salts.invitation;

    // Derive a key from the temporary hash
    const derivedKey = await deriveKey(tempHash, 'invitation_key', serverSalt);

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
    const serverSalt = window.getConfig().salts.invitation;

    // Derive the key from the temporary hash using the salt
    const derivedKey = await deriveKey(tempHash, 'invitation_key', serverSalt);

    // Decrypt the data
    const decryptedData = await decryptWithSymKey(derivedKey, encryptedData, iv, tag, true);

    const roomKey = importSymmetricKey(decryptedData);

    return roomKey; // Return the original room key
}


//#endregion


//#endregion


//#region Utilities


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
        const userInfo = window.getAuthenticatedConnection().userinfo;
        try {
            const keyData = localStorage.getItem(`${userInfo.username}PK`);
            const keyJson = JSON.parse(keyData);
            const salt = window.getConfig().salts.passkey;
            const key = await deriveKey(userInfo.email, userInfo.username, salt);

            passKey = await decryptWithSymKey(key, keyJson.ciphertext, keyJson.iv, keyJson.tag, false);

            if (await window.userKeychain.validateKeychainPassword(passKey)) {
                window.oldUiBridge.passkey = passKey;
                return passKey;
            } else {
                return null;
            }
        } catch (error) {
            return null;
        }
    }

}

async function setPassKey(enteredKey) {
    if (enteredKey === '') {
        return null;
    }
    const userInfo = window.getConnectionWithUserInfo().userinfo;
    const salt = window.getConfig().salts.passkey;
    //NOTE: USER INFO AND EMAIL SHOULD BE CHANGED TO SOMETHING PROPER
    const key = await deriveKey(userInfo.email, userInfo.username, salt);

    const encryptedPassKeyData = await encryptWithSymKey(key, enteredKey, false);

    localStorage.setItem(`${userInfo.username}PK`, JSON.stringify(encryptedPassKeyData));
    passKey = enteredKey;
    window.oldUiBridge.passkey = passKey || null;
}

//#endregion


async function cleanupUserData(callback) {
    try {
        const connection = window.getConnection();
        if (!connection || (connection.type !== 'internal_authenticated' && connection.type !== 'internal_registering_user')) {
            throw new Error('No authenticated connection found. Cleanup aborted.');
        }
        const userInfo = connection.userinfo;
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
