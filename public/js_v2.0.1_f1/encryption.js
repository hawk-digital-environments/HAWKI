let passKey;
let saltObj = {};



//#region Key Creation
async function generateKey() {
    const key = await window.crypto.subtle.generateKey(
        {
            name: "AES-GCM",
            length: 256
        },
        true,
        ["encrypt", "decrypt"]
    );

    return key;
}

async function generateKeyPair() {
    const keyPair = await window.crypto.subtle.generateKey(
        {
            name: "RSA-OAEP",
            modulusLength: 2048,
            publicExponent: new Uint8Array([1, 0, 1]),
            hash: "SHA-256",
        },
        true,
        ["encrypt", "decrypt"]
    );

    return keyPair;
}

function generateTempHash() {
    const array = new Uint8Array(16); // 16 bytes = 128 bits
    window.crypto.getRandomValues(array);
    return Array.from(array).map(byte => byte.toString(16).padStart(2, '0')).join('');
}

function generatePasskeyBackupHash(){
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

    const enc = new TextEncoder();
    
    const keyMaterial = await window.crypto.subtle.importKey(
        "raw",
        enc.encode(passkey),
        { name: "PBKDF2" },
        false,
        ["deriveKey"]
    );

    // Combine label and serverSalt to create a unique salt for this derived key
    const combinedSalt = new Uint8Array([
        ...new TextEncoder().encode(label), 
        ...new Uint8Array(serverSalt)
    ]);

    const derivedKey = await window.crypto.subtle.deriveKey(
        {
            name: "PBKDF2",
            salt: combinedSalt,
            iterations: 100000,
            hash: "SHA-256"
        },
        keyMaterial,
        { name: "AES-GCM", length: 256 },
        true,
        ["encrypt", "decrypt"]
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
            name: "AES-GCM",
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
                name: "AES-GCM",
                iv: ivBuffer
            },
            encKey, // Symmetric key
            combinedBuffer // Combined ciphertext + tag
        );

        // Return decrypted data (binary or text based on isKey)
        return isKey ? new Uint8Array(decryptedData) : new TextDecoder().decode(decryptedData);
    } catch (error) {
        // console.error("Decryption failed:", error);
        throw new Error("Decryption failed: " + error.message);
    }
}

//#endregion



//#region Asymmetric

async function encryptWithPublicKey(roomKey, publicKey) {

    // Export the roomKey (CryptoKey) to raw format (ArrayBuffer)
    const exportedRoomKey = await exportSymmetricKey(roomKey);

    // Import the recipient's public key
    const importedPublicKey = await window.crypto.subtle.importKey(
        "spki", // Key format
        publicKey, // Recipient's public key in ArrayBuffer format
        {
            name: "RSA-OAEP",
            hash: { name: "SHA-256" },
        },
        false, // Not extractable
        ["encrypt"]
    );

    // Encrypt the exported roomKey using the recipient's public key
    const encryptedRoomKey = await window.crypto.subtle.encrypt(
        {
            name: "RSA-OAEP",
        },
        importedPublicKey,
        exportedRoomKey // The raw bytes of the roomKey
    );

    // Return the encrypted roomKey as Base64 string
    return {
        ciphertext: arrayBufferToBase64(encryptedRoomKey),
    };
}

async function decryptWithPrivateKey(encryptedData, privateKey) {
    // Import the user's private key
    const importedPrivateKey = await window.crypto.subtle.importKey(
        "pkcs8", // Key format
        privateKey, // User's private key in ArrayBuffer format
        {
            name: "RSA-OAEP",
            hash: { name: "SHA-256" },
        },
        false, // Not extractable
        ["decrypt"]
    );

    // Decrypt the encrypted roomKey
    const decryptedRoomKeyBytes = await window.crypto.subtle.decrypt(
        {
            name: "RSA-OAEP",
        },
        importedPrivateKey,
        encryptedData // Encrypted symmetric key in ArrayBuffer format
    );

    // Import the decrypted bytes back into a CryptoKey object
    const roomKey = await window.crypto.subtle.importKey(
        "raw",
        decryptedRoomKeyBytes,
        {
            name: "AES-GCM",
        },
        true, // Extractable
        ["encrypt", "decrypt"]
    );

    // Return the reconstructed roomKey (CryptoKey object)
    return roomKey;
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


async function openHawkIDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('HAWKI', 1); // Fixed version

        request.onupgradeneeded = function (event) {
            const db = event.target.result;
            console.log('Initializing the database.');
            if (!db.objectStoreNames.contains('keychains')) {
                db.createObjectStore('keychains', { keyPath: 'username' });
                console.log('Created object store: keychains');
            }
        };

        request.onsuccess = function (event) {
            resolve(event.target.result);
        };

        request.onerror = function (event) {
            reject('Failed to open IndexedDB: ' + event.target.error);
        };
    });
}




// Set the keychain value in IndexedDB
async function keychainSet(key, value, formatToJWK, backup = true) {

    if (formatToJWK) {
        value = await exportKeyValueToJWK(value);
    }

    let keychain;
    try {
        keychain = await openKeychain(); // Try to open and decrypt the keychain
    } catch (error) {
        console.warn("Failed to open or decrypt keychain. Creating a new one.");
        keychain = {}; // Initialize a new keychain if there's an error
    }

    // Update keychain with username, timestamp, and new key-value pair
    keychain['username'] = userInfo.username;
    keychain['time-signature'] = Date.now();
    keychain[key] = value;

    const keychainString = JSON.stringify(keychain);

    // Encrypt the updated keychain
    const passKey = await getPassKey();
    const udSalt = await fetchServerSalt('USERDATA_ENCRYPTION_SALT');
    const keychainEncryptor = await deriveKey(passKey, "keychain_encryptor", udSalt);
    const encKeychainData = await encryptWithSymKey(keychainEncryptor, keychainString, false);

    // Store the encrypted keychain in IndexedDB
    const db = await openHawkIDatabase();
    const transaction = db.transaction('keychains', 'readwrite');
    const store = transaction.objectStore('keychains');

    const keychainData = {
        ciphertext: encKeychainData.ciphertext,
        iv: encKeychainData.iv,
        tag: encKeychainData.tag,
    };

    const userData = {
        username: userInfo.username,
        keychain: keychainData,
    };

    const storeRequest = store.put(userData);

    storeRequest.onsuccess = function () {
        console.log("Keychain successfully stored in IndexedDB.");
        if (backup) {
            backupKeychainOnServer(encKeychainData);
        }
    };

    storeRequest.onerror = function (event) {
        console.error("Error storing keychain:", event.target.error);
    };

    return encKeychainData;
}



// Get the keychain value from IndexedDB
async function keychainGet(key) {
    const keychain = await openKeychain();
    if (!keychain) {
        console.warn("No keychain available. Returning null.");
        return null;
    }

    if (!(key in keychain)) {
        console.warn(`Key "${key}" not found in keychain.`);
        return null; // Return null if key is not found
    }

    try {
        const keyValue = await importKeyValueFromJWK(keychain[key]);
        return keyValue;
    } catch (error) {
        console.error(`Error importing key "${key}" from keychain:`, error);
        throw error;
    }
}



// Retrieve and decrypt the keychain from IndexedDB
async function openKeychain() {
    const db = await openHawkIDatabase();
    const transaction = db.transaction('keychains', 'readonly');
    const store = transaction.objectStore('keychains');

    return new Promise((resolve, reject) => {
        // console.log("Fetching keychain for user:", userInfo.username);

        const request = store.get(userInfo.username);

        request.onsuccess = async function (event) {
            const result = event.target.result;
            // console.log("Keychain entry retrieved from IndexedDB:", result);

            if (!result) {
                console.warn('No keychain found for user, initializing a new keychain.');
                resolve({}); // Return an empty object if no entry exists
                return;
            }

            const { ciphertext, iv, tag } = result.keychain;

            // Verify that required fields exist
            if (!ciphertext || !iv || !tag) {
                console.error("Incomplete keychain data in IndexedDB:", result);
                reject("Keychain data is missing required fields.");
                return;
            }

            try {
                const passKey = await getPassKey();
                const udSalt = await fetchServerSalt('USERDATA_ENCRYPTION_SALT');
                const keychainEncryptor = await deriveKey(passKey, "keychain_encryptor", udSalt);

                // console.log("Decrypting keychain...");
                const decryptedKeychain = await decryptWithSymKey(
                    keychainEncryptor,
                    ciphertext,
                    iv,
                    tag,
                    false  // Expecting text output
                );

                // console.log("Decrypted keychain:", decryptedKeychain);
                const keychain = JSON.parse(decryptedKeychain);
                resolve(keychain);
            } catch (error) {
                console.error("Error decrypting keychain:", error);
                reject(error);
            }
        };

        request.onerror = function (event) {
            console.error('Error fetching keychain from IndexedDB:', event.target.error);
            reject(event.target.error);
        };
    });
}



async function backupKeychainOnServer(encKeychainData){

    const requestObject = {
        ciphertext: encKeychainData.ciphertext,
        iv: encKeychainData.iv,
        tag: encKeychainData.tag,
    }

    try{
        const response = await fetch('/req/backupKeychain', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify(requestObject)
        });

        if (!response.ok) {
            const errorData = await response.json();
            console.error('Server Error:', errorData.error);
            throw new Error(`Server Error: ${errorData.error}`);
        }

        const data = await response.json();

        if (data.success) {
            console.log('Keychain backup successful...');
        } else {
            console.log('Failed to make backup for keychain');
        }
    }
    catch (error) {
        console.error('Error storing keychain backup:', error);
        throw error;
    }
}


async function syncKeychain(serverKeychainData) {
    const { keychain, KCIV, KCTAG } = JSON.parse(serverKeychainData);

    const passKey = await getPassKey();
    const udSalt = await fetchServerSalt('USERDATA_ENCRYPTION_SALT');
    const keychainEncryptor = await deriveKey(passKey, "keychain_encryptor", udSalt);

    let serverKeychain;
    try {
        serverKeychain = await decryptWithSymKey(keychainEncryptor, keychain, KCIV, KCTAG, false);
        serverKeychain = JSON.parse(serverKeychain);
    } catch (error) {
        console.error("Error decrypting server keychain:", error);
        throw error; // Prevent further sync attempts with corrupted server data
    }

    const localKeychain = await openKeychain();

    if (!localKeychain || (serverKeychain['time-signature'] > (localKeychain['time-signature'] || 0))) {
        console.log("Updating local keychain with server data.");
        const keychainString = JSON.stringify(serverKeychain);
        const encKeychainData = await encryptWithSymKey(keychainEncryptor, keychainString, false);

        const db = await openHawkIDatabase();
        const transaction = db.transaction('keychains', 'readwrite');
        const store = transaction.objectStore('keychains');

        const keychainData = {
            ciphertext: encKeychainData.ciphertext,
            iv: encKeychainData.iv,
            tag: encKeychainData.tag,
        };

        const userData = {
            username: userInfo.username,
            keychain: keychainData,
        };

        const storeRequest = store.put(userData);

        storeRequest.onsuccess = function () {
            console.log("Local keychain updated successfully.");
        };

        storeRequest.onerror = function (event) {
            console.error("Error updating local keychain:", event.target.error);
        };
    } else {
        console.log("Local keychain is newer. Uploading to server.");
        const keychainString = JSON.stringify(localKeychain);
        const encKeychainData = await encryptWithSymKey(keychainEncryptor, keychainString, false);
        await backupKeychainOnServer(encKeychainData);
    }
}




async function removeKeychain(username) {
    try {
        const db = await openHawkIDatabase();
        const transaction = db.transaction('keychains', 'readwrite');
        const store = transaction.objectStore('keychains');

        return new Promise((resolve, reject) => {
            const request = store.delete(username);

            request.onsuccess = function () {
                console.log(`Keychain entry for username '${username}' successfully removed.`);
                resolve(`Keychain entry for username '${username}' removed.`);
            };

            request.onerror = function (event) {
                console.error(`Error removing keychain for username '${username}':`, event.target.error);
                reject(event.target.error);
            };
        });
    } catch (error) {
        console.error("Failed to open IndexedDB or remove keychain:", error);
        throw error;
    }
}



//#endregion




//#region Utilities


//fetches server salt with label
async function fetchServerSalt(saltLabel) {

    if(saltObj[saltLabel]){
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
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
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
    return await window.crypto.subtle.exportKey("jwk", keyValue);
}

async function importKeyValueFromJWK(jwk) {
    try{
        const value = await window.crypto.subtle.importKey(
            "jwk",
            jwk,
            {
                name: "AES-GCM",
                length: 256
            },
            true,
            ["encrypt", "decrypt"]
        );
        return value;
    }
    catch{
        return jwk;
    }

}



async function exportSymmetricKey(key) {
    return await window.crypto.subtle.exportKey("raw", key);
}
async function importSymmetricKey(decryptedRoomKey) {
    if (decryptedRoomKey.byteLength !== 16 && decryptedRoomKey.byteLength !== 32) {
        throw new Error("Decrypted AES key must be 128 or 256 bits");
    }

    return await window.crypto.subtle.importKey(
        "raw",
        decryptedRoomKey, // The decrypted AES key in ArrayBuffer format
        {
            name: "AES-GCM",
            length: decryptedRoomKey.byteLength * 8 // Convert byteLength to bits
        },
        true, // The key can be extracted (optional)
        ["encrypt", "decrypt"]
    );
}

//#endregion

//#region PassKey
async function getPassKey(){

    if(passKey){
       return passKey; 
    }
    else{
        try{
            const keyData = localStorage.getItem(`${userInfo.username}PK`);
            const keyJson = JSON.parse(keyData);
            const salt = await fetchServerSalt('PASSKEY_SALT');
            const key = await deriveKey(userInfo.email, userInfo.username, salt);
        
            passKey = await decryptWithSymKey(key, keyJson.ciphertext, keyJson.iv, keyJson.tag, false);
            
            if(await testPassKey()){
                return passKey;
            }
            else{
                return null;
            }
        }
        catch (error) {
            console.log("Passkey not found:", error);
            return null;
        }
    }

}

async function setPassKey(enteredKey){
    if(enteredKey === ''){
        return null;
    }
    const salt = await fetchServerSalt('PASSKEY_SALT');
    //NOTE: USER INFO AND EMAIL SHOULD BE CHANGED TO SOMETHING PROPER
    const key = await deriveKey(userInfo.email, userInfo.username, salt);

    const encryptedPassKeyData = await encryptWithSymKey(key, enteredKey, false);

    localStorage.setItem(`${userInfo.username}PK`, JSON.stringify(encryptedPassKeyData));
    passKey = enteredKey;
}

async function testPassKey(passKey){

    if( await keychainGet('username') === userInfo.username){
        return true;
    }    
    else{
        return false;
    }
}

//#endregion



async function cleanupUserData(callback) {
    try {
        // Cleanup localStorage
        if (localStorage.getItem(`${userInfo.username}PK`)) {
            localStorage.removeItem(`${userInfo.username}PK`);
        }

        // Remove the keychain from IndexedDB
        await removeKeychain(userInfo.username);

        console.log("Cleanup completed successfully.");

        // If a callback is provided, invoke it
        if (callback && typeof callback === 'function') {
            callback();
        }
    } catch (error) {
        console.error("Error during cleanup:", error);

        // Optional: Invoke callback with an error
        if (callback && typeof callback === 'function') {
            callback(error);
        }
    }
}