let previousSlide;
let currentSlideIndex;

function switchSlide(targetIndex) {
    const target = document.querySelector(`.slide[data-index="${targetIndex}"]`);

    if (previousSlide) {
        previousSlide.style.opacity = "0";
    }

    setTimeout(() => {
        if (previousSlide) {
            previousSlide.style.display = "none";
        }

        target.style.display = "flex";
        if(targetIndex > 1){
            document.querySelector('.slide-back-btn').style.opacity = "1";
        }
        else{
            document.querySelector('.slide-back-btn').style.opacity = "0";
        }

        // Add a small delay before changing the opacity to ensure the display change has been processed
        setTimeout(() => {
            target.style.opacity = "1";
        }, 300);

        previousSlide = target;
        currentSlideIndex = targetIndex;
    }, 300);
}

function switchBackSlide(){
    const targetIndex = currentSlideIndex - 1;
    switchSlide(targetIndex);
}

function modalClick(btn){
    switchSlide(4);
}


let backupHash = '';
async function checkPasskey(){

    const msg = document.querySelector('#alert-message');
    const enteredPasskey = String(document.getElementById('passkey-input').value);
    // if passkey field is left empty.
    if(enteredPasskey === ''){
        msg.innerText = "Bitte gebe ein Passkey ein."
        return;
    }

    const repeatField = document.getElementById('passkey-repeat');
    //Show Repeat Passkey
    if(repeatField.style.display === 'none'){
        repeatField.style.display = 'block';
        return;
    }

    const repeatedKey = String(repeatField.value);
    //if repeat passkey is empty
    if(repeatedKey === ''){
        msg.innerText = "Bitte wiederhole das Passkey."
        return;
    }
    //if the inputs are not the same.
    if(enteredPasskey != repeatedKey){
        msg.innerText = "Die Eingaben sind nicht gleich."
        return;
    }


    // create backup hash
    backupHash = generatePasskeyBackupHash();

    document.querySelector('#backup-hash').innerText = backupHash;
    // derive key from backup hash
    const passkeyBackupSalt = await fetchServerSalt('BACKUP_SALT');
    const derivedKey = await deriveKey(backupHash, `${userInfo.username}_backup`, passkeyBackupSalt);
    //encrypt Passkey as plaintext
    const cryptoPasskey = await encryptWithSymKey(derivedKey, enteredPasskey, false);
    // upload backup to the server.
    dataToSend = {
        'username': userInfo.username,
        'cipherText': cryptoPasskey.ciphertext,
        'tag': cryptoPasskey.tag,
        'iv': cryptoPasskey.iv,
    }
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        // Send the registration data to the server
        const response = await fetch('/req/profile/backupPassKey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken
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
            // console.log(data.message)
        }

    } catch (error) {
        console.error('Error Creating Passkey Backup:', error);
        throw error;
    }
    // save passkey to localstorage.
    await setPassKey(enteredPasskey);

    // show backup hash
    switchSlide(6);
}


async function autoGeneratePasskey(){
    // This function generates the passkey in the background without user interaction

    const encoder = new TextEncoder();
    
    let passkeyValue = null;

    //console.log('passkeySecret: ' + passkeySecret);

    switch (passkeySecret) {
        case 'username':
            passkeyValue = userInfo.username;
            break;
        case 'time':
            passkeyValue = userInfo.created_at;
            break;
        case 'time':
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


    console.log('passkeValue: ' + passkeyValue);
    console.log('username: ' + userInfo.username);
    
    const hashBuffer = await crypto.subtle.digest(
        'SHA-256',
        encoder.encode(passkeyValue)
    );

    const generatedPasskey = Array.from(new Uint8Array(hashBuffer))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');

    console.log('generatedPasskey: ' + generatedPasskey);

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
    dataToSend = {
        'username': userInfo.username,
        'cipherText': cryptoPasskey.ciphertext,
        'tag': cryptoPasskey.tag,
        'iv': cryptoPasskey.iv,
    }
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        // Send the registration data to the server
        const response = await fetch('/req/profile/backupPassKey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken
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
            // console.log(data.message)
        }

    } catch (error) {
        console.error('Error Creating Passkey Backup:', error);
        throw error;
    }
    // save passkey to localstorage.
    await setPassKey(generatedPasskey);

        console.log('Passkey generated and saved successfully');
        onBackupCodeComplete();
}

async function verifyGeneratedPassKey(){

    // user passkey generation logic removed
    const encoder = new TextEncoder();
    
    let passkeyValue = null;

    console.log('passkeSecret: ' + passkeySecret);

    switch (passkeySecret) {
        case 'username':
            passkeyValue = userInfo.username;
            break;
        case 'time':
            passkeyValue = userInfo.created_at;
            break;
        case 'time':
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


    console.log('passkeValue: ' + passkeyValue);
    console.log('username: ' + userInfo.username);
    
    const hashBuffer = await crypto.subtle.digest(
        'SHA-256',
        encoder.encode(passkeyValue)
    );

    const generatedPasskey = Array.from(new Uint8Array(hashBuffer))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');

    console.log('generatedPasskey: ' + generatedPasskey);

    if(await verifyPasskey(generatedPasskey)){
        await setPassKey(generatedPasskey);
        await syncKeychain(serverKeychainCryptoData);
        console.log('keychain synced');
        window.location.href = '/chat'; 
    }
    else{
        console.log("Failed to verify passkey. Please try again.");
    }

}

function downloadTextFile() {

    if(backupHash === ''){
        return;
    }
    // Create a Blob from the text content
    const blob = new Blob([backupHash], { type: 'text/plain' });
    
    // Create a link element
    const link = document.createElement('a');
    
    // Create a URL for the Blob and set it as the href attribute
    link.href = URL.createObjectURL(blob);
    link.download = `${userInfo.username}_Key.txt`; // Set the download attribute with the filename
    
    // Append the link to the document body (won't be visible to the user)
    document.body.appendChild(link);
    
    // Programmatically click the link to trigger the download
    link.click();
    
    // Clean up by removing the link and revoking the object URL
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
}


async function initializeRegistration(){
    cleanupUserData(()=>{
        // console.log('cleaned Up previous user data.');
    });
}

async function onBackupCodeComplete(){
    // const confirmed = await openModal(ModalType.WARNING, 
    //     'Speichere diese Datei an einem sicheren Ort. Damit können wir im Notfall deine Chats wieder herstellen.')
    // if (!confirmed) {
    //     return;
    // }
    completeRegistration();
}

async function completeRegistration() {

    setOverlay(true, true);

    // Generate a key pair (public and private keys)
    const keyPair = await generateKeyPair();

    // Export the public key and private key
    const exportedPublicKey = await window.crypto.subtle.exportKey("spki", keyPair.publicKey);
    const exportedPrivateKey = await window.crypto.subtle.exportKey("pkcs8", keyPair.privateKey);

    publicKeyBase64 = arrayBufferToBase64(exportedPublicKey);
    privateKeyBase64 = arrayBufferToBase64(exportedPrivateKey);


    await keychainSet('publicKey', publicKeyBase64, false, false);
    await keychainSet('privateKey', privateKeyBase64, false, false);

    // Generate and encrypt the aiConvKey and keychain
    const aiConvKey = await generateKey();
    const keychainData = await keychainSet('aiConvKey', aiConvKey, true, false);
    

    // Prepare the data to send to the server
    const dataToSend = {
        publicKey: publicKeyBase64,
        keychain: keychainData.ciphertext,
        KCIV: keychainData.iv, 
        KCTAG: keychainData.tag, 
    };

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Send the registration data to the server
        const response = await fetch('/req/complete_registration', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken
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
            userInfo = data.userData;
            window.location.href = data.redirectUri;
        }

    } catch (error) {
        console.error('Error completing registration:', error);
        throw error;
    }
}



async function verifyEnteredPassKey(provider){

    const slide = provider.closest(".slide");
    const inputField = slide.querySelector("#passkey-input");
    const enteredKey = String(inputField.value.trim());
    const errorMessage = slide.querySelector("#alert-message");

    if (!enteredKey) {
        errorMessage.innerText = 'Please enter your passkey!';
        return;
    }

    if(await verifyPasskey(enteredKey)){
        await setPassKey(enteredKey);
        await syncKeychain(serverKeychainCryptoData);
        console.log('keychain synced');
        window.location.href = '/chat'; 
    }
    else{
        errorMessage.innerText = "Failed to verify passkey. Please try again.";
        setTimeout(() => {
            errorMessage.innerText = "";
        }, 10000);
    }

}

async function verifyPasskey(passkey) {
    try {
        const udSalt = await fetchServerSalt('USERDATA_ENCRYPTION_SALT');
        const keychainEncryptor = await deriveKey(passkey, "keychain_encryptor", udSalt);
    
        const { keychain, KCIV, KCTAG } = JSON.parse(serverKeychainCryptoData);
    
        const decryptedKeychain = await decryptWithSymKey(
            keychainEncryptor,
            keychain,
            KCIV,
            KCTAG,
            false
        );

        return true;
    } catch (error) {
        // You can log the error if needed
        // console.error("Error during verification or decryption:", error);
        return false;
    }
}


function uploadTextFile() {
    // Create a file input element
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.txt'; // Accept only text files
    const msg = document.querySelector('#backup-alert-message');

    // Set up an event listener to handle the file once the user selects it
    input.addEventListener('change', function(event) {
        const file = event.target.files[0]; // Get the first selected file
        if (file) {
            const reader = new FileReader();
            // Once the file is read, invoke the callback with the file content
            reader.onload = function(e) {
                const content = e.target.result;  
                if (isValidBackupKeyFormat(content.trim())) {
                    document.querySelector('#backup-hash-input').value = content;
                } else {
                    msg.innerText = 'The file content does not match the required format.';
                }
            };
            // Read the file as text
            reader.readAsText(file);
        }
    });

    // Trigger the file input dialog
    input.click();
}
function isValidBackupKeyFormat(content) {
    // Define a regular expression to match the format xxxx-xxxx-xxxx-xxxx
    const pattern = /^[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}$/;
    return pattern.test(content);
}

async function extractPasskey(){
    const msg = document.querySelector('#backup-alert-message');
    const backupHash = document.querySelector('#backup-hash-input').value;
    if(!backupHash){
        msg.innerText = 'Enter backupHash or upload your backup file.';
        return;
    }
    if(!isValidBackupKeyFormat){
        msg.innerText = 'Backup key is not valid!';
        return;
    }

    // Get passkey backup from server.
    const passkeyBackup = await requestPasskeyBackup();
    if(!passkeyBackup){
        return;
    }

    // derive Key from entered backupkey
    const passkeyBackupSalt = await fetchServerSalt('BACKUP_SALT');
    const derivedKey = await deriveKey(backupHash, `${userInfo.username}_backup`, passkeyBackupSalt);
    // console.log(derivedKey);
    try{
        //encrypt Passkey as plaintext
        const passkey = await decryptWithSymKey(derivedKey, 
                                                passkeyBackup.ciphertext,
                                                passkeyBackup.iv,
                                                passkeyBackup.tag, 
                                                false);
                                                
        if(verifyPasskey(passkey)){
            setPassKey(passkey);
            switchSlide(3);
            document.querySelector('#passkey-field').innerText = passkey;
        }
        else{
            msg.innerText = "Failed to verify passkey";
        }
    }
    catch (error) {
        msg.innerText = 'Error decrypting passkey with backup code.';
        throw error;
    }

}

async function requestPasskeyBackup(){
        // Request passkey backup from server.
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            // Send the registration data to the server
            const response = await fetch('/req/profile/requestPasskeyBackup', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    "X-CSRF-TOKEN": csrfToken
                },
            });
    
            // Handle the server response
            if (!response.ok) {
                const errorData = await response.json();
                console.error('Server Error:', errorData.error);
                throw new Error(`Server Error: ${errorData.error}`);
            }
    
            const data = await response.json();
            if (data.success) {
                const passKeyJson = data.passkeyBackup;
                return passKeyJson;
            }
    
        } catch (error) {
            console.error('Error downloading passkey backup:', error);
            throw error;
        }
}

async function redirectToChat(){
    await syncKeychain(serverKeychainCryptoData);
    window.location.href = '/chat'; 
}


async function requestProfileReset(){
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        // Send the registration data to the server
        const response = await fetch('/req/profile/reset', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken
            },
        });

        // Handle the server response
        if (!response.ok) {
            const errorData = await response.json();
            console.error('Server Error:', errorData.error);
            throw new Error(`Server Error: ${errorData.error}`);
        }

        const data = await response.json();
        if (data.success) {
            window.location.href = data.redirectUri;
        }

    } catch (error) {
        console.error('Error reseting profile:', error);
        throw error;
    }
}

// OTP Functions
let otpTimer = null;

// Initialize OTP input handlers when container is shown
function initializeOTPInputs() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    
    otpInputs.forEach((input, index) => {
        // Handle input events
        input.addEventListener('input', function(e) {
            const value = e.target.value;
            
            // Only allow numbers
            if (!/^\d$/.test(value)) {
                e.target.value = '';
                return;
            }
            
            // Add filled class
            e.target.classList.add('filled');
            
            // Move to next input
            if (value && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
            
            // Check if all inputs are filled
            checkOTPComplete();
        });
        
        // Handle backspace
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                if (!e.target.value && index > 0) {
                    // Move to previous input if current is empty
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                    otpInputs[index - 1].classList.remove('filled');
                } else if (e.target.value) {
                    // Clear current input
                    e.target.value = '';
                    e.target.classList.remove('filled');
                }
            }
        });
        
        // Handle paste
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').replace(/\D/g, '');
            
            if (pasteData.length === 6) {
                otpInputs.forEach((inp, idx) => {
                    if (idx < pasteData.length) {
                        inp.value = pasteData[idx];
                        inp.classList.add('filled');
                    }
                });
                checkOTPComplete();
            }
        });
        
        // Focus management
        input.addEventListener('focus', function() {
            this.select();
        });
    });
}

function getOTPValue() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    return Array.from(otpInputs).map(input => input.value).join('');
}

function clearOTPInputs() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    otpInputs.forEach(input => {
        input.value = '';
        input.classList.remove('filled', 'error');
    });
}

function setOTPError() {
    const otpInputs = document.querySelectorAll('.otp-digit');
    otpInputs.forEach(input => {
        input.classList.add('error');
    });
    
    // Remove error class after animation
    setTimeout(() => {
        otpInputs.forEach(input => {
            input.classList.remove('error');
        });
    }, 500);
}

function checkOTPComplete() {
    const otp = getOTPValue();
    if (otp.length === 6) {
        // Auto-verify when all digits are entered
        setTimeout(() => {
            const verifyButton = document.getElementById('verify-otp-btn');
            if (verifyButton && !verifyButton.disabled) {
                verifyOTP(verifyButton);
            }
        }, 300);
    }
}

async function sendOTP(button = null) {
    if (!button) button = event.target;
    
    const originalText = button.textContent;
    
    try {
        button.textContent = translations["HS-LoginCodeB2"]; // 'Sende E-Mail...'
        button.disabled = true;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const response = await fetch('/req/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify({
                username: userInfo.username,
                email: userInfo.email
            })
        });
        const data = await response.json();
        
        if (data.success) {
            button.textContent = translations["HS-LoginCodeB5"];
            console.log('OTP sent successfully:', data.message);
            
            // Hide send container and show input container
            document.getElementById('otp-send-container').style.display = 'none';
            document.getElementById('otp-input-container').style.display = 'block';
            
            // Initialize OTP inputs
            initializeOTPInputs();
            
            // Focus first input
            const firstInput = document.querySelector('.otp-digit[data-index="0"]');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Start single OTP timer (using config value)
            startOTPTimer();
            
        } else {
            button.textContent = translations["HS-LoginCodeB6"];
            console.error('OTP sending failed:', data.error);
            showErrorMessage(data.error);
            
            // Re-enable button after error
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '';
                button.disabled = false;
            }, 3000);
        }
    } catch (error) {
        console.error('Error sending OTP:', error);
        button.textContent = translations["HS-LoginCodeB6"];
        showErrorMessage(translations["HS-LoginCodeE2"]);
        
        // Re-enable button after error
        setTimeout(() => {
            button.textContent = originalText;
            button.style.backgroundColor = '';
            button.disabled = false;
        }, 3000);
    }
}

async function resendOTP(button = null) {
    if (!button) button = event.target;
    
    console.log('Resending OTP...');
    
    const originalText = button.textContent;
    
    // Hide resend container and show input elements again
    document.getElementById('resend-container').style.display = 'none';
    document.querySelector('.otp-input-group').style.display = 'flex';
    document.getElementById('verify-otp-btn').style.display = 'block';
    
    // Clear and re-enable OTP inputs
    const otpInputs = document.querySelectorAll('.otp-digit');
    const verifyButton = document.getElementById('verify-otp-btn');
    
    otpInputs.forEach(input => {
        input.disabled = false;
        input.value = '';
        input.classList.remove('filled', 'error');
    });
    
    if (verifyButton) verifyButton.disabled = false;
    
    // Reset timer display
    const timerElement = document.getElementById('otp-timer');
    if (timerElement) {
        timerElement.style.color = 'var(--accent-color)';
    }
    
    try {
        button.textContent = translations["HS-LoginCodeB2"]; // 'Sende E-Mail...'
        button.disabled = true;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const response = await fetch('/req/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify({
                username: userInfo.username,
                email: userInfo.email
            })
        });
        const data = await response.json();
        
        if (data.success) {
            button.textContent = translations["HS-LoginCodeB5"];
            console.log('OTP resent successfully:', data.message);
            
            // Focus first input
            const firstInput = document.querySelector('.otp-digit[data-index="0"]');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Start new OTP timer (using config value)
            startOTPTimer();
            
            // Reset button after successful send
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '';
                button.disabled = false;
            }, 2000);
            
        } else {
            button.textContent = translations["HS-LoginCodeB6"];
            console.error('OTP resending failed:', data.error);
            showErrorMessage(data.error);
            
            // Show resend container again on error
            document.getElementById('resend-container').style.display = 'block';
            
            // Re-enable button after error
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '';
                button.disabled = false;
            }, 3000);
        }
    } catch (error) {
        console.error('Error resending OTP:', error);
        button.textContent = translations["HS-LoginCodeB6"];
        showErrorMessage(translations["HS-LoginCodeE2"]);
        
        // Show resend container again on error
        document.getElementById('resend-container').style.display = 'block';
        
        // Re-enable button after error
        setTimeout(() => {
            button.textContent = originalText;
            button.style.backgroundColor = '';
            button.disabled = false;
        }, 3000);
    }
}

function startOTPTimer() {
    // Use config value as default timeout
    const seconds = otpTimeout || 300; // fallback to 5 minutes if config not available
    
    const timerElement = document.getElementById('otp-timer');
    if (!timerElement) {
        console.error('OTP Timer element not found!');
        return;
    }
    
    console.log('OTP Timer started with', seconds, 'seconds');
    
    // Clear any existing timer first
    if (otpTimer) {
        console.log('Clearing existing OTP timer');
        clearInterval(otpTimer);
    }
    
    let remainingSeconds = seconds;
    
    const updateTimer = () => {
        const minutes = Math.floor(remainingSeconds / 60);
        const secondsDisplay = remainingSeconds % 60;
        const timeDisplay = `${minutes}:${secondsDisplay.toString().padStart(2, '0')}`;
        
        timerElement.textContent = timeDisplay;
        
        // Debug output to console
        console.log('OTP Timer:', {
            remainingSeconds: remainingSeconds,
            minutes: minutes,
            seconds: secondsDisplay,
            display: timeDisplay,
            element: timerElement,
            elementVisible: timerElement.offsetParent !== null,
            timerExists: !!otpTimer
        });
        
        if (remainingSeconds <= 0) {
            console.log('OTP Timer expired - cleaning up');
            clearInterval(otpTimer);
            otpTimer = null;
            
            timerElement.textContent = translations["HS-LoginCodeT3"]; // 'Log-in Code abgelaufen'
            timerElement.style.color = '#dc3545';
            
            // Hide OTP input elements
            const otpInputGroup = document.querySelector('.otp-input-group');
            const verifyButton = document.getElementById('verify-otp-btn');
            const resendContainer = document.getElementById('resend-container');
            
            console.log('Hiding OTP input elements and showing resend container');
            
            if (otpInputGroup) {
                otpInputGroup.style.display = 'none';
            }
            
            if (verifyButton) {
                verifyButton.style.display = 'none';
            }
            
            if (resendContainer) {
                resendContainer.style.display = 'block';
            }
            
            showErrorMessage(translations["HS-LoginCodeE6"]);
            console.log('OTP Timer finished - all cleanup completed');
            return;
        }
        
        remainingSeconds--;
    };
    
    // Run immediately, then set interval
    updateTimer();
    
    otpTimer = setInterval(updateTimer, 1000);
    
    // Store timer reference for debugging
    window.currentOTPTimer = otpTimer;
    console.log('OTP Timer reference stored:', {
        timerId: otpTimer,
        windowTimer: window.currentOTPTimer,
        initialSeconds: seconds
    });
}

// Add function to manually clear OTP timer for debugging
function clearOTPTimer() {
    console.log('Manually clearing OTP timer');
    if (otpTimer) {
        clearInterval(otpTimer);
        otpTimer = null;
        window.currentOTPTimer = null;
        console.log('OTP timer cleared');
    } else {
        console.log('No OTP timer to clear');
    }
}

// Add function to check timer status for debugging
function checkOTPTimerStatus() {
    console.log('OTP Timer Status:', {
        otpTimer: otpTimer,
        windowCurrentOTPTimer: window.currentOTPTimer,
        timerElement: document.getElementById('otp-timer'),
        inputContainer: document.getElementById('otp-input-container'),
        otpInputGroup: document.querySelector('.otp-input-group'),
        verifyButton: document.getElementById('verify-otp-btn'),
        resendContainer: document.getElementById('resend-container')
    });
}

// Make debugging functions available globally
window.clearOTPTimer = clearOTPTimer;
window.checkOTPTimerStatus = checkOTPTimerStatus;

async function verifyOTP(button = null) {
    if (!button) button = event.target;
    const originalText = button.textContent;
    
    const otp = getOTPValue();
    
    if (!otp || otp.length !== 6) {
        showErrorMessage(translations["HS-LoginCodeE3"]);
        setOTPError();
        return;
    }
    
    try {
        button.textContent = translations["HS-LoginCodeB7"]; // 'Verifiziere Log-in Code...'
        button.disabled = true;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const response = await fetch('/req/verify-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify({
                otp: otp
            })
        });
        const data = await response.json();
        console.log(data);
        if (data.success) {
            button.textContent = translations["HS-LoginCodeB8"];
            button.style.backgroundColor = '#28a745';
            console.log('OTP verified successfully:', data.message);
            
            // Clear any error messages
            const errorMessage = document.getElementById('alert-message');
            if (errorMessage) {
                errorMessage.textContent = '';
            }
            
            // Generate new passkey after successful OTP verification
            button.textContent = translations["HS-LoginCodeB9"]; // 'Logge ein...'
            console.log('Regenerating passkey after OTP verification...');
            
            try {
                await verifyGeneratedPassKey();
                console.log('Passkey verification successfully');
                
                // Redirect to chat after passkey generation
                setTimeout(() => {
                    window.location.href = '/chat';
                }, 1000);
                
            } catch (passkeyError) {
                console.error('Error generating passkey:', passkeyError);
                showErrorMessage(translations["HS-LoginCodeE4"]);
                button.textContent = 'Passkey-Fehler';
                button.style.backgroundColor = '#dc3545';
            }
            
        } else {
            button.textContent = translations["HS-LoginCodeB10"];
            button.style.backgroundColor = '#dc3545';
            console.error('OTP verification failed:', data.error);
            showErrorMessage(data.error);
            setOTPError();
        }
    } catch (error) {
        console.error('Error verifying OTP:', error);
        button.textContent = translations["HS-LoginCodeB11"];
        button.style.backgroundColor = '#dc3545';
        showErrorMessage(translations["HS-LoginCodeE5"]);
        setOTPError();
    }
    
    setTimeout(() => {
        button.textContent = originalText;
        button.style.backgroundColor = '';
        button.disabled = false;
    }, 3000);
}

// Make debugging functions available globally
window.clearOTPTimer = clearOTPTimer;
window.checkOTPTimerStatus = checkOTPTimerStatus;

function showErrorMessage(message) {
    const errorElement = document.getElementById('alert-message') || document.getElementById('backup-alert-message');
    if (errorElement) {
        errorElement.textContent = message;
        setTimeout(() => {
            errorElement.textContent = '';
        }, 5000);
    }
}