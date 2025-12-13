/**
 * WebAuthn Passkey Integration for Backup Hash Storage
 * 
 * This module provides client-side only WebAuthn functionality to store
 * and retrieve backup hash codes using native OS passkeys (TouchID, FaceID, Windows Hello).
 * 
 * CRITICAL: Zero Trust Architecture
 * - Backup hash is NEVER sent to server
 * - Passkey credentials are stored locally on user's device
 * - WebAuthn is used purely for local secure storage
 * 
 * @version 1.0.0
 */

/**
 * Check if WebAuthn is supported in the current browser
 * @returns {boolean} True if WebAuthn is supported
 */
function isWebAuthnSupported() {
    return window.PublicKeyCredential !== undefined && 
           navigator.credentials !== undefined;
}

/**
 * Register a new passkey credential with the backup hash stored in userHandle
 * 
 * @param {Uint8Array|string} backupData - The backup data to store in the passkey (as Uint8Array or string)
 * @param {string} username - Username for display purposes
 * @returns {Promise<{success: boolean, credentialId?: string, error?: string}>}
 */
async function registerBackupHashPasskey(backupData, username) {
    if (!isWebAuthnSupported()) {
        return {
            success: false,
            error: 'WebAuthn is not supported in this browser'
        };
    }

    try {
        // Ensure document has focus (Safari requirement)
        window.focus();
        
        // Small delay to ensure focus is established
        await new Promise(resolve => setTimeout(resolve, 100));
        
        // Generate a random challenge (client-side only, no server validation needed)
        const challenge = crypto.getRandomValues(new Uint8Array(32));
        
        // Handle both Uint8Array (new combined format) and string (legacy format)
        let backupDataBytes;
        if (backupData instanceof Uint8Array) {
            backupDataBytes = backupData;
        } else {
            // Legacy string format
            backupDataBytes = new TextEncoder().encode(backupData);
        }
        
        if (backupDataBytes.length > 64) {
            throw new Error(`User handle exceeds 64 bytes (${backupDataBytes.length} bytes)`);
        }
        
        // Create credential options
        const publicKeyCredentialCreationOptions = {
            challenge: challenge,
            rp: {
                name: "HAWKI",
                id: window.location.hostname
            },
            user: {
                id: backupDataBytes, // Store backup data here
                name: username,
                displayName: "HAWKI Backup Recovery Code"
            },
            pubKeyCredParams: [
                { type: "public-key", alg: -7 },  // ES256
                { type: "public-key", alg: -257 } // RS256
            ],
            authenticatorSelection: {
                authenticatorAttachment: "platform", // Prefer platform authenticators
                userVerification: "preferred",
                residentKey: "required" // Discoverable credential
            },
            timeout: 60000,
            attestation: "none"
        };

        // Create the credential
        const credential = await navigator.credentials.create({
            publicKey: publicKeyCredentialCreationOptions
        });

        if (!credential) {
            return {
                success: false,
                error: 'Failed to create passkey credential'
            };
        }

        // Convert credential ID to base64 for storage reference (optional)
        const credentialId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)));
        
        return {
            success: true,
            credentialId: credentialId
        };

    } catch (error) {
        console.error('Error registering backup hash passkey:', error);
        
        // User-friendly error messages
        let errorMessage = 'Failed to create passkey';
        if (error.name === 'NotAllowedError') {
            errorMessage = 'Passkey creation was cancelled or not allowed';
        } else if (error.name === 'InvalidStateError') {
            errorMessage = 'A passkey already exists for this account';
        } else if (error.name === 'NotSupportedError') {
            errorMessage = 'Passkeys are not supported on this device';
        }
        
        return {
            success: false,
            error: errorMessage
        };
    }
}

/**
 * Authenticate with a passkey and retrieve the stored backup hash
 * 
 * @returns {Promise<{success: boolean, backupHash?: string, error?: string}>}
 */
async function authenticateWithBackupHashPasskey() {
    if (!isWebAuthnSupported()) {
        return {
            success: false,
            error: 'WebAuthn is not supported in this browser'
        };
    }

    try {
        // Ensure document has focus (Safari requirement)
        window.focus();
        
        // Small delay to ensure focus is established
        await new Promise(resolve => setTimeout(resolve, 100));
        
        // Generate a random challenge (client-side only)
        const challenge = crypto.getRandomValues(new Uint8Array(32));
        
        // Get credential options
        const publicKeyCredentialRequestOptions = {
            challenge: challenge,
            rpId: window.location.hostname,
            timeout: 60000,
            userVerification: "preferred"
        };

        // Get the credential
        const assertion = await navigator.credentials.get({
            publicKey: publicKeyCredentialRequestOptions
        });

        if (!assertion || !assertion.response.userHandle) {
            console.error('No userHandle in assertion response');
            return {
                success: false,
                error: 'No backup hash found in passkey'
            };
        }

        // Extract data from userHandle
        const userHandleBytes = new Uint8Array(assertion.response.userHandle);
        
        // Check if this is combined data (40 bytes) or legacy backup hash
        let backupHash, hawkiPasskey;
        
        if (userHandleBytes.length === 40) {
            // New combined format: first 8 bytes = backupHash, next 32 bytes = hawkiPasskey
            const backupHashBytes = userHandleBytes.slice(0, 8);
            const hawkiPasskeyBytes = userHandleBytes.slice(8, 40);
            
            // Convert bytes back to hex strings
            backupHash = Array.from(backupHashBytes)
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');
            
            // Add dashes to backupHash (xxxx-xxxx-xxxx-xxxx format)
            backupHash = backupHash.match(/.{1,4}/g).join('-');
            
            hawkiPasskey = Array.from(hawkiPasskeyBytes)
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');
        } else {
            // Legacy format: backup hash as string
            backupHash = new TextDecoder().decode(userHandleBytes);
        }

        // Validate that we got a backup hash
        if (!backupHash || backupHash.length < 10) {
            console.error('Invalid backup hash retrieved:', backupHash);
            return {
                success: false,
                error: 'Invalid backup hash format in passkey'
            };
        }

        return {
            success: true,
            backupHash: backupHash,
            hawkiPasskey: hawkiPasskey // Optional, only present in combined format
        };

    } catch (error) {
        console.error('Error authenticating with backup hash passkey:', error);
        
        // User-friendly error messages
        let errorMessage = 'Failed to retrieve backup hash';
        if (error.name === 'NotAllowedError') {
            errorMessage = 'Authentication was cancelled';
        } else if (error.name === 'NotFoundError') {
            errorMessage = 'No saved passkey found for this account';
        }
        
        return {
            success: false,
            error: errorMessage
        };
    }
}

/**
 * Initialize passkey UI elements and event handlers
 */
function initializePasskeyUI() {
    // Check browser support on page load
    if (!isWebAuthnSupported()) {
        console.warn('WebAuthn not supported in this browser');
        // Hide passkey-related UI elements
        const passkeyButtons = document.querySelectorAll('.passkey-btn');
        passkeyButtons.forEach(btn => {
            btn.style.display = 'none';
        });
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePasskeyUI);
} else {
    initializePasskeyUI();
}

/**
 * Show the passkey registration form on Slide 6.1
 */
function showPasskeyRegistrationForm() {
    const showBtn = document.getElementById('show-passkey-registration-btn');
    const form = document.getElementById('passkey-registration-form');
    
    if (showBtn && form) {
        showBtn.style.display = 'none';
        form.style.display = 'block';
    }
}

/**
 * Register a new WebAuthn Passkey with both backup hash and HAWKI passkey
 * This creates a combined credential for complete account recovery
 */
async function registerNewWebAuthnPasskey() {
    const errorMsg = document.querySelector('#passkey-registration-error');
    const successMsg = document.querySelector('#passkey-registration-success');
    
    // Clear previous messages
    if (errorMsg) {
        errorMsg.innerText = '';
        errorMsg.style.display = 'none';
    }
    if (successMsg) {
        successMsg.innerText = '';
        successMsg.style.display = 'none';
    }

    // Get the backup hash from input
    const backupHash = window.getPasskeyRealValue('backup-hash-input-registration');
    
    if (!backupHash) {
        if (errorMsg) {
            errorMsg.innerText = window.translations?.['HS-EnterBackupHashError'] || 'Please enter your recovery code.';
            errorMsg.style.display = 'block';
        }
        return;
    }

    // Validate backup hash format
    if (!window.isValidBackupKeyFormat(backupHash)) {
        if (errorMsg) {
            errorMsg.innerText = window.translations?.['InvalidBackupFormat'] || 'Invalid backup hash format';
            errorMsg.style.display = 'block';
        }
        return;
    }

    // Check WebAuthn support
    if (!isWebAuthnSupported()) {
        if (errorMsg) {
            errorMsg.innerText = window.translations?.['PasskeyNotSupported'] || 'Passkeys are not supported by this browser';
            errorMsg.style.display = 'block';
        }
        return;
    }

    try {
        // Get passkey backup from server
        const passkeyBackup = await window.requestPasskeyBackup();
        if (!passkeyBackup) {
            if (errorMsg) {
                errorMsg.innerText = window.translations?.['HS-NoBackupFound'] || 'No backup found for this user.';
                errorMsg.style.display = 'block';
            }
            return;
        }

        // Derive key from entered backup hash
        const passkeyBackupSalt = await window.fetchServerSalt('BACKUP_SALT');
        const derivedKey = await window.deriveKey(backupHash, `${userInfo.username}_backup`, passkeyBackupSalt);
        
        // Decrypt passkey from backup using backup code
        const hawkiPasskey = await window.decryptWithSymKey(
            derivedKey,
            passkeyBackup.ciphertext,
            passkeyBackup.iv,
            passkeyBackup.tag,
            false
        );

        // Verify the decrypted passkey with keychain
        const verified = await window.verifyPasskey(hawkiPasskey);
        
        if (!verified) {
            if (errorMsg) {
                errorMsg.innerText = window.translations?.['HS-BackupVerificationFailed'] || 'Backup hash is correct, but passkey verification failed.';
                errorMsg.style.display = 'block';
            }
            return;
        }

        // If user already has a passkey registered, reset the flag first to allow new registration
        if (typeof hasWebAuthnPasskey !== 'undefined' && hasWebAuthnPasskey) {
            try {
                const resetResponse = await fetch('/req/user/set-webauthn-pk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ has_passkey: false })
                });

                if (!resetResponse.ok) {
                    console.warn('Failed to reset webauthn_pk flag, continuing anyway...');
                }
            } catch (err) {
                console.warn('Error resetting webauthn_pk flag:', err);
                // Continue anyway - WebAuthn might still work
            }
        }

        // Create combined data structure for WebAuthn storage
        // Convert hex strings to bytes to save space (backupHash: 8 bytes, hawkiPasskey: 32 bytes = 40 bytes total)
        const backupHashClean = backupHash.replace(/-/g, ''); // Remove dashes
        
        // Convert hex strings to byte arrays
        const backupHashBytes = new Uint8Array(backupHashClean.match(/.{1,2}/g).map(byte => parseInt(byte, 16)));
        const hawkiPasskeyBytes = new Uint8Array(hawkiPasskey.match(/.{1,2}/g).map(byte => parseInt(byte, 16)));
        
        // Combine both byte arrays (8 + 32 = 40 bytes, well under 64 byte limit)
        const combinedBytes = new Uint8Array(backupHashBytes.length + hawkiPasskeyBytes.length);
        combinedBytes.set(backupHashBytes, 0);
        combinedBytes.set(hawkiPasskeyBytes, backupHashBytes.length);

        // Register combined passkey with WebAuthn (pass combinedBytes as binary, not string)
        const result = await registerBackupHashPasskey(combinedBytes, userInfo.username);

        if (result.success) {
            // Update user's webauthn_pk flag in database
            try {
                const response = await fetch('/req/user/set-webauthn-pk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ has_passkey: true })
                });

                if (!response.ok) {
                    console.error('Failed to update webauthn_pk flag');
                }
            } catch (err) {
                console.error('Error updating webauthn_pk flag:', err);
            }

            // Show success message
            if (successMsg) {
                successMsg.innerText = window.translations?.['PasskeySaveSuccess'] || '✓ Passkey successfully created! You will be logged in...';
                successMsg.style.display = 'block';
            }

            // Disable the submit button during auto-login wait
            const submitBtn = document.querySelector('#passkey-registration-form button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }

            // Wait 5 seconds before auto-login
            setTimeout(async () => {
                // Auto-login the user since they've verified their backup hash
                try {
                    // Save the passkey to localStorage
                    await window.setPassKey(hawkiPasskey);
                    
                    // Sync the keychain
                    if (typeof serverKeychainCryptoData !== 'undefined') {
                        await window.syncKeychain(serverKeychainCryptoData);
                    }
                    
                    // Redirect to chat
                    window.location.href = '/chat';
                } catch (loginError) {
                    console.error('Error during auto-login:', loginError);
                    
                    // Fallback: just hide the form and show success
                    const form = document.getElementById('passkey-registration-form');
                    const showBtn = document.getElementById('show-passkey-registration-btn');
                    if (form) form.style.display = 'none';
                    if (showBtn) {
                        showBtn.innerText = window.translations?.['PasskeyRegistered'] || 'Passkey registered ✓';
                        showBtn.disabled = true;
                        showBtn.style.display = 'block';
                    }
                }
            }, 5000); // 5 seconds delay

        } else {
            // Show error message
            if (errorMsg) {
                errorMsg.innerText = result.error || (window.translations?.['PasskeySaveFailed'] || 'Error saving passkey');
                errorMsg.style.display = 'block';
            }
        }

    } catch (error) {
        console.error('Error during passkey registration:', error);
        
        // Check if it's a decryption error (wrong backup code)
        if (error.message && error.message.includes('Decryption failed')) {
            if (errorMsg) {
                errorMsg.innerText = window.translations?.['HS-IncorrectBackupCode'] || 'Incorrect backup hash.';
                errorMsg.style.display = 'block';
            }
        } else {
            if (errorMsg) {
                errorMsg.innerText = window.translations?.['PasskeySaveError'] || 'Error creating passkey';
                errorMsg.style.display = 'block';
            }
        }
    }
}

/**
 * Automatically start WebAuthn login for Slide 6 (system passkey recovery)
 * Called when user lands on Slide 6 and has webauthn_pk = true
 */
async function autoStartWebAuthnLogin() {
    const errorMsg = document.querySelector('#webauthn-auto-error');
    const autoSection = document.querySelector('#webauthn-auto-login-section');
    const manualSection = document.querySelector('#manual-backup-section');

    try {
        // Authenticate with WebAuthn and retrieve combined data (backup hash + HAWKI passkey)
        const result = await authenticateWithBackupHashPasskey();

        if (result.success && result.hawkiPasskey) {
            // We got both backup hash and HAWKI passkey directly from WebAuthn
            
            // Save passkey and sync keychain
            await window.setPassKey(result.hawkiPasskey);
            
            if (typeof serverKeychainCryptoData !== 'undefined') {
                await window.syncKeychain(serverKeychainCryptoData);
            }
            
            // Redirect to chat
            window.location.href = '/chat';
            
        } else if (result.success && result.backupHash) {
            // Legacy: Only got backup hash, need to decrypt passkey

            
            // Use existing extractPasskeySystem logic but with the retrieved backup hash
            const passkeyBackup = await window.requestPasskeyBackup();
            if (!passkeyBackup) {
                throw new Error('No backup found for this user');
            }

            const passkeyBackupSalt = await window.fetchServerSalt('BACKUP_SALT');
            const derivedKey = await window.deriveKey(result.backupHash, `${userInfo.username}_backup`, passkeyBackupSalt);
            
            const hawkiPasskey = await window.decryptWithSymKey(
                derivedKey,
                passkeyBackup.ciphertext,
                passkeyBackup.iv,
                passkeyBackup.tag,
                false
            );
            
            const verified = await window.verifyPasskey(hawkiPasskey);
            if (verified) {
                await window.setPassKey(hawkiPasskey);
                await window.syncKeychain(serverKeychainCryptoData);
                window.location.href = '/chat';
            } else {
                throw new Error('Passkey verification failed');
            }
        } else {
            throw new Error(result.error || 'WebAuthn authentication failed');
        }

    } catch (error) {
        console.error('WebAuthn auto-login failed:', error);
        
        // Show error message
        if (errorMsg) {
            errorMsg.innerText = window.translations?.['HS-WebAuthnFailed'] || 'Passkey authentication failed. Please enter your recovery code manually.';
            errorMsg.style.display = 'block';
        }
        
        // Show manual input section as fallback
        setTimeout(() => {
            if (autoSection) autoSection.style.display = 'none';
            if (manualSection) {
                manualSection.style.display = 'block';
                // Show title if it was hidden
                const title = document.querySelector('#manual-backup-title');
                if (title) title.style.display = 'block';
            }
        }, 2000);
    }
}

/**
 * Auto-fill passkey from WebAuthn on Slide 1
 * Automatically authenticates with WebAuthn and logs in the user
 * @returns {Promise<void>}
 */
async function autoFillPasskeyFromWebAuthn() {
    if (!isWebAuthnSupported()) {
        return;
    }

    try {
        const result = await authenticateWithBackupHashPasskey();

        if (result.success && result.hawkiPasskey) {
            // We got the HAWKI passkey from the combined credential
            // Directly authenticate and login without filling the input field
            
            // Save passkey to localStorage
            await window.setPassKey(result.hawkiPasskey);
            
            // Sync the keychain
            if (typeof serverKeychainCryptoData !== 'undefined') {
                await window.syncKeychain(serverKeychainCryptoData);
            }
            
            // Redirect to chat
            window.location.href = '/chat';
        }
    } catch (error) {
        console.error('Error auto-filling passkey from WebAuthn:', error);
        // Silently fail - user can still manually enter passkey
    }
}

// Export functions to global scope
window.isWebAuthnSupported = isWebAuthnSupported;
window.registerBackupHashPasskey = registerBackupHashPasskey;
window.authenticateWithBackupHashPasskey = authenticateWithBackupHashPasskey;
window.showPasskeyRegistrationForm = showPasskeyRegistrationForm;
window.registerNewWebAuthnPasskey = registerNewWebAuthnPasskey;
window.autoStartWebAuthnLogin = autoStartWebAuthnLogin;
window.autoFillPasskeyFromWebAuthn = autoFillPasskeyFromWebAuthn;
