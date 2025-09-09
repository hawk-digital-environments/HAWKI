@extends('layouts.gateway')
@section('content')


<div class="wrapper">

    <div class="container" style="overflow:auto">

        <div class="slide" data-index="0">
                <h1>{{ $translation["Reg_SL0_H"] }}</h1>
                <div class="slide-content">
                    <p>
                        {!! $translation["Reg_SL0_T"] !!}
                    </p>
                </div>
                <div class="nav-buttons">
                    <button class="btn-lg-fill" onclick="autoGeneratePasskey()">{{ $translation["Reg_SL0_B"] }}</button>
                </div>                
                <script>console.log('passkey_method = auto');</script> 
        </div>

        <div class="slide" data-index="1">
            <h1>{{ $translation["Reg_SL1_H"] }}</h1>
            <div class="slide-content">
                <p>{{ $translation["Reg_SL1_T"] }}</p>
            </div>
            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="navigateToSlide(2)">{{ $translation["Reg_SL1_B"] }}</button>
            </div>
        </div>

        <div class="slide" data-index="2" @if(!config('app.groupchat_active', true)) style="display: none;" @endif>
            <!-- Groupchat Slide -->
            <h1>{{ $translation["Reg_SL2_H"] }}</h1>
            <div class="slide-content">
                <p>
                    {{ $translation["Reg_SL2_T"] }}
                </p>
            </div>
            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="navigateToSlide(3)">{{ $translation["Reg_SL2_B"] }}</button>
            </div>
        </div>

        <div class="slide" data-index="3" >
            @include('partials.home.modals.guidelines-modal')
        </div>



        <div class="slide" data-index="4">
                <h1>{{ $translation["Reg_SL4_H"] }}</h1>
                <div class="slide-content">
                    <p>
                        {!! $translation["Reg_SL4_T"] !!}
                    </p>
                </div>
                <div class="nav-buttons">
                    <button class="btn-lg-fill" onclick="switchSlide(5)">{{ $translation["Reg_SL4_B"] }}</button>
                </div>
                <script>console.log('passkey_method = user');</script> 
        </div>
        
        <div class="slide" data-index="5">
            <h1>{{ $translation["Reg_SL5_H"] }}</h1>
            <input placeholder="{{  $translation["Reg_SL5_PH1"] }}" id="passkey-input" type="password">
            <input placeholder="{{  $translation["Reg_SL5_PH2"] }}" id="passkey-repeat" type="password" class="top-gap-2" style="display:none">
            <p class="slide-subtitle top-gap-2">
                {!! $translation["Reg_SL5_T"] !!}
            </p>
            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="checkPasskey()">{{ $translation["Save"] }}</button>
            </div>
            <p class="red-text" id="alert-message"></p>

        </div>

        {{-- Password Change Slide for Local Users who need password reset --}}
        @if(($isFirstLoginLocalUser ?? false) && ($needsPasswordReset ?? false))
        <div class="slide" data-index="5.5" style="display:none" id="password-change-slide">
            <h1>{{ $translation["change_password"] ?? "Change Password" }}</h1>
            <p class="slide-subtitle">
                {{ $translation["change_password_text"] ?? "Please set a new password for your account." }}
            </p>
            <input placeholder="{{ $translation["new_password"] ?? "New Password" }}" id="new-password-input" type="password" class="top-gap-2">
            <input placeholder="{{ $translation["confirm_password"] ?? "Confirm Password" }}" id="confirm-password-input" type="password" class="top-gap-2">
            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="validateAndSavePassword()">{{ $translation["Save"] ?? "Save" }}</button>
            </div>
            <p class="red-text" id="password-alert-message"></p>
        </div>
        @endif

        <div class="slide" data-index="6">
            <h1 class="zero-b-margin">{{ $translation["Reg_SL6_H"] }}</h1>
            <p class="slide-subtitle top-gap-2">
                {{ $translation["Reg_SL6_T"] }}
            </p>
            <div class="backup-hash-row">
                <h3 id="backup-hash" class="demo-hash"></h3>
                <button class="btn-sm border" onclick="downloadTextFile()">
                    <x-icon name="download"/>
                </button>
            </div>
            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="onBackupCodeComplete()">{{ $translation["Continue"] }}</button>
            </div>
        </div>

    </div>
  
</div>
<div class="slide-back-btn" onclick="switchBackSlide()">
    <x-icon name="chevron-left"/>
</div>
@include('partials.home.modals.confirm-modal')




<script>
    let userInfo = @json($userInfo);
    let passkeySecret = @json($passkeySecret);
    let isFirstLoginLocalUser = @json($isFirstLoginLocalUser ?? false);
    let needsPasswordReset = @json($needsPasswordReset ?? false);
    let groupchatActive = @json(config('app.groupchat_active', true));
    
    initializeRegistration();
    
    // Helper function to navigate slides while respecting groupchat settings
    function navigateToSlide(targetSlide) {
        // If trying to navigate to slide 2 (groupchat) and groupchat is disabled, skip to slide 3
        if (targetSlide === 2 && !groupchatActive) {
            switchSlide(3);
        }
        // If navigating from slide 1 to slide 2 and groupchat is disabled, go to slide 3 instead
        else if (targetSlide === 2 && !groupchatActive) {
            switchSlide(3);
        }
        // If navigating backwards from slide 3 and groupchat is disabled, go to slide 1 instead of slide 2
        else if (targetSlide === 2 && currentSlideIndex === 3 && !groupchatActive) {
            switchSlide(1);
        }
        else {
            switchSlide(targetSlide);
        }
    }
    
    // Override the switchBackSlide function to handle groupchat skipping
    function switchBackSlideWithGroupchatCheck(){
        let targetIndex = currentSlideIndex - 1;
        
        // If we're going back to slide 2 and groupchat is disabled, go to slide 1 instead
        if (targetIndex === 2 && !groupchatActive) {
            targetIndex = 1;
        }
        
        switchSlide(targetIndex);
    }
    
    // Override the global switchBackSlide function
    window.switchBackSlide = switchBackSlideWithGroupchatCheck;
    
    // For local users who need password reset, start with password change
    // For other users (including self-service local users), start with normal flow
    if (isFirstLoginLocalUser && needsPasswordReset) {
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password-change-slide').style.display = 'block';
            switchSlide(5.5);
        });
    } else {
        window.addEventListener('DOMContentLoaded', switchSlide(1));
    }

    setTimeout(() => {
        if(@json($activeOverlay)){
            setOverlay(false, true)
        }
    }, 100);

    // Password validation and saving for local users
    function validateAndSavePassword() {
        const newPassword = document.getElementById('new-password-input').value;
        const confirmPassword = document.getElementById('confirm-password-input').value;
        const alertElement = document.getElementById('password-alert-message');

        // Clear previous alerts
        alertElement.textContent = '';

        // Validate passwords
        if (!newPassword || newPassword.length < 6) {
            alertElement.textContent = 'Password must be at least 6 characters long.';
            return;
        }

        if (newPassword !== confirmPassword) {
            alertElement.textContent = 'Passwords do not match.';
            return;
        }

        // Store new password globally for completeRegistration
        window.localUserNewPassword = newPassword;
        
        // After password is set, continue with normal registration flow
        // Start with slide 1 (welcome/intro) then proceed to passkey generation
        switchSlide(1);
    }

    // For local users, override completeRegistration to include new password
    if (isFirstLoginLocalUser) {
        // Store the original function
        const originalCompleteRegistration = window.completeRegistration;
        
        // Override with our version that adds the password
        window.completeRegistration = async function() {
            console.log('Local user completeRegistration called with password:', window.localUserNewPassword);
            
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
            
            // Add new password for local users
            if (window.localUserNewPassword) {
                dataToSend.newPassword = window.localUserNewPassword;
                console.log('Added newPassword to dataToSend');
            }

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
                    return;
                }

                const jsonData = await response.json();
                if (jsonData.success) {
                    console.log('Registration successful, redirecting...');
                    window.location.href = jsonData.redirectUri || '/chat';
                } else {
                    console.error('Registration failed:', jsonData);
                }
            } catch (error) {
                console.error('Registration request failed:', error);
                setOverlay(false, false);
            }
        };
    }
</script>


@endsection