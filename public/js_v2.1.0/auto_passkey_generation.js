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

    console.log('Generating passkey with secret:', passkeySecret);
    console.log('User info for passkey generation:', {
        username: userInfo.username,
        created_at: userInfo.created_at,
        publicKey: userInfo.publicKey ? 'available' : 'not available'
    });

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
        default:
            passkeyValue = userInfo.username;
            break;
    }

    console.log('passkeyValue:', passkeyValue);
    
    const hashBuffer = await crypto.subtle.digest(
        'SHA-256',
        encoder.encode(passkeyValue)
    );

    const generatedPasskey = Array.from(new Uint8Array(hashBuffer))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    
    console.log('Generated passkey:', generatedPasskey);
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
    console.log('=== autoGeneratePasskey START ===');
    console.log('passkeySecret configuration:', passkeySecret);
    
    try {
        // Use the same logic as verifyGeneratedPassKey to ensure consistency
        console.log('Generating passkey using generatePasskeyFromSecret...');
        const generatedPasskey = await generatePasskeyFromSecret(passkeySecret, userInfo);
        console.log('Generated passkey in autoGeneratePasskey:', generatedPasskey);

        // create backup hash
        backupHash = generatePasskeyBackupHash();
        console.log('backupHash: ' + backupHash);
        
        // Check if backup-hash element exists before setting its content
        const backupHashElement = document.querySelector('#backup-hash');
        if (backupHashElement) {
            backupHashElement.innerText = backupHash;
        }
        
        // derive key from backup hash
        const passkeyBackupSalt = await fetchServerSalt('BACKUP_SALT');
        const derivedKey = await deriveKey(backupHash, `${userInfo.username}_backup`, passkeyBackupSalt);
        //encrypt Passkey as plaintext
        const cryptoPasskey = await encryptWithSymKey(derivedKey, generatedPasskey, false);
        
        // upload backup to the server.
        const dataToSend = {
            'username': userInfo.username,
            'cipherText': cryptoPasskey.ciphertext,
            'tag': cryptoPasskey.tag,
            'iv': cryptoPasskey.iv,
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        // Send the registration data to the server
        const response = await fetch('/req/profile/backupPassKey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(dataToSend)
        });

        // Handle the server response
        if (!response.ok) {
            const errorData = await response.json();
            console.error('Server Error:', errorData.error);
            throw new Error(`Server Error: ${errorData.error}`);
        }

        const data = await response.json();
        if (data.success) {
            console.log('Backup stored successfully');
        }
        
        // save passkey to localstorage.
        await setPassKey(generatedPasskey);

        console.log('Passkey generated and saved successfully');
        console.log('=== autoGeneratePasskey END ===');
        
        // Complete registration directly - skip backup code slide
        // Check if completeRegistration function exists (for registration context)
        if (typeof completeRegistration === 'function') {
            completeRegistration();
        } else {
            // For handshake context, go directly to chat
            console.log('Handshake context - redirecting to chat');
            window.location.href = '/chat';
        }
        
    } catch (error) {
        console.error('Error in autoGeneratePasskey:', error);
        // Fallback to manual passkey creation
        if (typeof switchSlide === 'function') {
            switchSlide(1);
        }
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
        
        // Generate the passkey using the same logic as autoGeneratePasskey
        const generatedPasskey = await generatePasskeyFromSecret(passkeySecret, userInfo);
        
        // Verify that serverKeychainCryptoData is valid
        if (!serverKeychainCryptoData) {
            console.error('serverKeychainCryptoData is not available');
            return;
        }

        // Try to parse serverKeychainCryptoData first
        try {
            const parsedData = JSON.parse(serverKeychainCryptoData);
            // parsedData checked for presence of expected fields
        } catch (parseError) {
            console.error('Failed to parse serverKeychainCryptoData:', parseError);
            return;
        }

        const verificationResult = await verifyPasskey(generatedPasskey);
        
        if(verificationResult){
            await setPassKey(generatedPasskey);
            
            try {
                await syncKeychain(serverKeychainCryptoData);
                window.location.href = '/chat';
            } catch (syncError) {
                console.error('Error syncing keychain:', syncError);
            }
        }
    } catch (error) {
        console.error('Error in verifyGeneratedPassKey:', error);
    }
}

// Make functions available globally for backward compatibility
window.generatePasskeyFromSecret = generatePasskeyFromSecret;
window.autoGeneratePasskey = autoGeneratePasskey;
window.verifyGeneratedPassKey = verifyGeneratedPassKey;
