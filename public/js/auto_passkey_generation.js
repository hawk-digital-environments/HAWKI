/**
 * Auto Passkey Generation Module
 * 
 * This module contains functions for automatic passkey generation and verification.
 * It's separated from the original handshake_functions.js to avoid modifying 
 * upstream files and make maintenance easier.
 * 
 * Author: Custom HAWKI Extension
 * Created: 2025-09-18
 * Updated: 2025-10-21 - Removed insecure passkeySecret options, now uses only cryptographically secure random generation
 */

/**
 * Generate a cryptographically secure random passkey for system-generated method
 * 
 * System-generated passkeys always use 256 bits of cryptographically secure randomness.
 * 
 * @returns {Promise<string>} - Generated passkey as hex string (64 characters)
 */
async function generateRandomPasskey() {
    // Generate 256 bits (32 bytes) of cryptographically secure randomness
    const randomBytes = new Uint8Array(32);
    crypto.getRandomValues(randomBytes);
    
    // Convert to hex string
    const randomHex = Array.from(randomBytes)
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    
    // Hash the random value with SHA-256 for additional entropy mixing
    const encoder = new TextEncoder();
    const hashBuffer = await crypto.subtle.digest('SHA-256', encoder.encode(randomHex));
    
    // Convert hash to hex string
    const generatedPasskey = Array.from(new Uint8Array(hashBuffer))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    
    return generatedPasskey;
}

/**
 * Automatically generate a passkey in the background without user interaction
 * Used during registration process for system-generated method
 * 
 * @returns {Promise<void>}
 */
async function autoGeneratePasskey(){
    // This function generates the passkey in the background without user interaction
    try {
        // Generate a cryptographically secure random passkey
        const generatedPasskey = await generateRandomPasskey();

        // Create backup hash
        backupHash = generatePasskeyBackupHash();
        console.log(backupHash);
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
        
        // Make backupHash available globally for completeRegistration
        window.backupHash = backupHash;
        
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
            const isHandshakeContext = window.location.pathname.includes('/handshake');
            if (isHandshakeContext) {
                // In handshake context with system passkeys, go to backup recovery slide (6)
                switchSlide(6);
            }
            // In registration context: no fallback, registration fails
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
 * Verify a generated passkey
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

        // Generate a cryptographically secure random passkey
        const generatedPasskey = await generateRandomPasskey();
        
        // Verify the generated passkey against the encrypted keychain
        const verificationResult = await verifyPasskeyWithKeychain(generatedPasskey);
        
        if(verificationResult){
            await setPassKey(generatedPasskey);
            
            try {
                await syncKeychain(serverKeychainCryptoData);
                window.location.href = '/chat';
            } catch (syncError) {
                console.error('Error syncing keychain:', syncError);
                // Fallback only in handshake context
                if (typeof switchSlide === 'function') {
                    const isHandshakeContext = window.location.pathname.includes('/handshake');
                    if (isHandshakeContext) {
                        switchSlide(6);
                    }
                    // In registration context: no fallback
                }
            }
        } else {
            // Verification failed - fallback only in handshake context
            //console.error('Automatic passkey verification failed - falling back to manual entry');
            
            // Show appropriate slide based on context
            if (typeof switchSlide === 'function') {
                const isHandshakeContext = window.location.pathname.includes('/handshake');
                if (isHandshakeContext) {
                    switchSlide(6);
                } else {
                    // In registration context: show error, no fallback
                    alert('Automatic passkey verification failed. Please try logging in again.');
                    window.location.href = '/login';
                }
            } else {
                alert('Automatic passkey verification failed. Please try logging in again.');
                window.location.href = '/login';
            }
        }
    } catch (error) {
        console.error('Error in verifyGeneratedPassKey:', error);
        // Fallback only in handshake context
        if (typeof switchSlide === 'function') {
            const isHandshakeContext = window.location.pathname.includes('/handshake');
            if (isHandshakeContext) {
                switchSlide(6);
            } else {
                // In registration context: show error, no fallback
                alert('An error occurred during passkey verification. Please try logging in again.');
                window.location.href = '/login';
            }
        } else {
            alert('An error occurred during passkey verification. Please try logging in again.');
            window.location.href = '/login';
        }
    }
}

// Make functions available globally for backward compatibility
window.generateRandomPasskey = generateRandomPasskey;
window.autoGeneratePasskey = autoGeneratePasskey;
window.verifyGeneratedPassKey = verifyGeneratedPassKey;
window.verifyPasskeyWithKeychain = verifyPasskeyWithKeychain;
