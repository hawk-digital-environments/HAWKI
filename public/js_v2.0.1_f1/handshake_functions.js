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
    const enteredPasskey = String(document.getElementById('passkey-input').dataset.realValue);

    // if passkey field is left empty.
    if(enteredPasskey === ''){
        msg.innerText = translation.HS_EnterPasskeyMsg
        return;
    }

    const repeatWrapper = document.getElementById('passkey-repeat');

    //Show Repeat Passkey
    if(repeatWrapper.style.display === 'none'){
        repeatWrapper.style.display = 'flex';
        repeatWrapper.querySelector('input').focus();
        return;
    }
    const repeatField = repeatWrapper.querySelector('.passkey-input')
    const repeatedKey = String(repeatField.dataset.realValue);


    //if repeat passkey is empty
    if(repeatedKey === ''){
        msg.innerText = translation.HS_RepeatPassKey
        return;
    }

    //if the inputs are not the same.
    if(enteredPasskey != repeatedKey){
        msg.innerText = translation.HS_DifferentEntries
        return;
    }

    let serverVerified = false;

    try {
        serverVerified = await validatePasskeyByServer(enteredPasskey);
    } catch (error) {
        console.error('Error verifying passkey with server:', error);
        msg.innerText = "Error verifying passkey with server"
        return;
    }

    if(!serverVerified){
        msg.innerText = "PassKey could not be verified by the server"
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


async function validatePasskeyByServer(enteredKey){

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        // Send the registration data to the server
        const response = await fetch('/req/profile/validatePasskey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                "X-CSRF-TOKEN": csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({passkey: enteredKey})
        });

        // Handle the server response
        if (!response.ok) {
            const errorData = await response.json();
            console.error('Server Error:', errorData.error);
            throw new Error(`Server Error: ${errorData.error}`);
        }

        const data = await response.json();
        if (data.success) {
            return {
                success: true,
                message: data.message
            };
        }
        else{
            return {
                success: false,
                message: data.message
            };
        }

    } catch (error) {
        console.error('Error Creating Passkey Backup:', error);
        throw error;
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
    const enteredKey = String(inputField.dataset.realValue.trim());
    const errorMessage = slide.querySelector("#alert-message");

    if (!enteredKey) {
        errorMessage.innerText = 'Please enter your passkey!';
        return;
    }

    isVerified = await verifyPasskey(enteredKey);

    if(isVerified){
        await setPassKey(enteredKey);
        await syncKeychain(serverKeychainCryptoData);
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
                    "X-CSRF-TOKEN": csrfToken,
                    'Accept': 'application/json',
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
                "X-CSRF-TOKEN": csrfToken,
                'Accept': 'application/json',
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
