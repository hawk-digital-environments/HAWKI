/**
 * Auto Passkey Generation Module
 * 
 * This module contains functions for automatic passkey generation and verification.
 * It's separated from the original handshake_functions.js to avoid modifying 
 * upstream files and make maintenance easier.
 * 
 * Author: Custom HAWKI Extension
 * Created: 2025-09-18
 */

/**
 * Generate a passkey from a secret and user information
 * 
 * @param {string} passkeySecret - The secret type ('username', 'time', 'publicKey', 'mixed')
 * @param {object} userInfo - User information object containing username, created_at, publicKey
 * @returns {Promise<string>} - Generated passkey as hex string
 */
async function generatePasskeyFromSecret(passkeySecret, userInfo) {
    const encoder = new TextEncoder();
    let passkeyValue = null;

    switch (passkeySecret) {
        case 'username':
            passkeyValue = userInfo.username;
            break;
        case 'time':
            passkeyValue = userInfo.created_at;
            break;
        case 'publicKey':
            passkeyValue = userInfo.publicKey;
            break;    
        case 'mixed':
            // Concatenate username and created_at, then hash the result for passkeyValue
            const mixedString = userInfo.username + userInfo.created_at;
            const mixedHashBuffer = await crypto.subtle.digest('SHA-256', encoder.encode(mixedString));
            passkeyValue = Array.from(new Uint8Array(mixedHashBuffer))
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');
            break;
        case 'random':
            // Generate a cryptographically secure random value
            const randomBytes = new Uint8Array(32); // 256 bits of randomness
            crypto.getRandomValues(randomBytes);
            passkeyValue = Array.from(randomBytes)
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');
            break;
        default:
            // Invalid passkey secret provided - warn and use username as fallback
            console.warn(`Invalid passkeySecret value: "${passkeySecret}". Valid values are: 'username', 'time', 'publicKey', 'mixed', 'random'. Falling back to 'username'.`);
            passkeyValue = userInfo.username;
            break;
    }
    
    const hashBuffer = await crypto.subtle.digest(
        'SHA-256',
        encoder.encode(passkeyValue)
    );

    const generatedPasskey = Array.from(new Uint8Array(hashBuffer))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    
    return generatedPasskey;
}

/**
 * Automatically generate a passkey in the background without user interaction
 * Used during registration process
 * 
 * @returns {Promise<void>}
 */
async function autoGeneratePasskey(){
    // This function generates the passkey in the background without user interaction
    try {
        // Generate the passkey
        const generatedPasskey = await generatePasskeyFromSecret(passkeySecret, userInfo);

        // Create backup hash
        backupHash = generatePasskeyBackupHash();
        
        // Check if backup-hash element exists before setting its content
        const backupHashElement = document.querySelector('#backup-hash');
        if (backupHashElement) {
            backupHashElement.innerText = backupHash;
        }
        
        // Derive key from backup hash
        const passkeyBackupSalt = await fetchServerSalt('BACKUP_SALT');
        const derivedKey = await deriveKey(backupHash, `${userInfo.username}_backup`, passkeyBackupSalt);
        
        // Encrypt passkey as plaintext
        const cryptoPasskey = await encryptWithSymKey(derivedKey, generatedPasskey, false);
        
        // Upload backup to the server
        const dataToSend = {
            'username': userInfo.username,
            'cipherText': cryptoPasskey.ciphertext,
            'tag': cryptoPasskey.tag,
            'iv': cryptoPasskey.iv,
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const response = await fetch('/req/profile/backupPassKey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(dataToSend)
        });

        if (!response.ok) {
            const errorData = await response.json();
            console.error('Server Error:', errorData.error);
            throw new Error(`Server Error: ${errorData.error}`);
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error('Failed to store backup');
        }
        
        // Save passkey to localStorage
        await setPassKey(generatedPasskey);
        
        // Complete registration directly - skip backup code slide
        if (typeof completeRegistration === 'function') {
            completeRegistration();
        } else {
            // For handshake context, go directly to chat
            window.location.href = '/chat';
        }
        
    } catch (error) {
        console.error('Error in autoGeneratePasskey:', error);
        // Fallback only in handshake context
        if (typeof switchSlide === 'function') {
            switchSlide(2);
        }
    }
}

/**
 * Verify a passkey against the encrypted keychain
 * 
 * @param {string} passkey - The passkey to verify
 * @returns {Promise<boolean>} - True if verification succeeds
 */
async function verifyPasskeyWithKeychain(passkey) {
    try {
        const udSalt = await fetchServerSalt('USERDATA_ENCRYPTION_SALT');
        const keychainEncryptor = await deriveKey(passkey, "keychain_encryptor", udSalt);
        const { keychain, KCIV, KCTAG } = JSON.parse(serverKeychainCryptoData);

        await decryptWithSymKey(
            keychainEncryptor,
            keychain,
            KCIV,
            KCTAG,
            false
        );

        return true;
    } catch (error) {
        console.error('Passkey verification failed:', error.message);
        return false;
    }
}

/**
 * Verify a generated passkey during OTP authentication flow
 * 
 * @returns {Promise<void>}
 */
async function verifyGeneratedPassKey(){
    try {
        // Verify userInfo is available
        if (!userInfo) {
            console.error('userInfo is not available');
            return;
        }
        
        // Verify that serverKeychainCryptoData is valid
        if (!serverKeychainCryptoData) {
            console.error('serverKeychainCryptoData is not available');
            return;
        }

        // Parse serverKeychainCryptoData to validate format
        try {
            JSON.parse(serverKeychainCryptoData);
        } catch (parseError) {
            console.error('Failed to parse serverKeychainCryptoData:', parseError);
            return;
        }

        // Generate the passkey using the same logic as autoGeneratePasskey
        const generatedPasskey = await generatePasskeyFromSecret(passkeySecret, userInfo);
        
        // Verify the generated passkey against the encrypted keychain
        const verificationResult = await verifyPasskeyWithKeychain(generatedPasskey);
        
        if(verificationResult){
            await setPassKey(generatedPasskey);
            
            try {
                await syncKeychain(serverKeychainCryptoData);
                window.location.href = '/chat';
            } catch (syncError) {
                console.error('Error syncing keychain:', syncError);
                // Fallback to manual passkey input on sync error
                if (typeof switchSlide === 'function') {
                    switchSlide(2);
                }
            }
        } else {
            // Verification failed - fallback to manual passkey input
            console.error('Automatic passkey verification failed - falling back to manual entry');
            
            // Show manual passkey input slide
            if (typeof switchSlide === 'function') {
                switchSlide(2);
            } else {
                alert('Automatic passkey verification failed. Please try logging in again.');
                window.location.href = '/login';
            }
        }
    } catch (error) {
        console.error('Error in verifyGeneratedPassKey:', error);
        // Fallback to manual passkey input on any error
        if (typeof switchSlide === 'function') {
            switchSlide(2);
        } else {
            alert('An error occurred during passkey verification. Please try logging in again.');
            window.location.href = '/login';
        }
    }
}

// Make functions available globally for backward compatibility
window.generatePasskeyFromSecret = generatePasskeyFromSecret;
window.autoGeneratePasskey = autoGeneratePasskey;
window.verifyGeneratedPassKey = verifyGeneratedPassKey;
window.verifyPasskeyWithKeychain = verifyPasskeyWithKeychain;
