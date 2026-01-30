@extends('layouts.gateway')
@section('content')



<div class="wrapper">

    <div class="container">

        <div class="slide" data-index="1">
            <h3>{{ $translation["HS-EnterPasskeyMsg"] }}</h3>

            <form id="passkey-form"  autocomplete="off">
                <x-passkey-input 
                    id="passkey-input"
                    placeholder="{{ $translation['HS-PH-EnterDatakey'] ?? 'Enter your Datakey here' }}"
                />
            </form>

            <div class="nav-buttons">
                <button id="verifyEnteredPassKey-btn" onclick="verifyEnteredPassKey(this)" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
            </div>
            <p class="red-text" id="alert-message"></p>
            <button onclick="switchSlide(2)" class="btn-md">{{ $translation["HS-ForgottenPasskey"] }}</button>

        </div>


        <div class="slide" data-index="2">
            <h3>{{ $translation["HS-EnterBackupMsg"] }}</h3>

            <form id="backup-recovery-form" autocomplete="on" onsubmit="event.preventDefault(); extractPasskey();">
                {{-- Hidden username field for password manager context (with backup suffix to create separate credential) --}}
                <input 
                    type="text" 
                    name="username" 
                    autocomplete="username" 
                    value="{{ ($userInfo['username'] ?? '') . '@backup' }}"
                    style="display: none;"
                    readonly
                />

                <x-backup-hash-input 
                    id="backup-hash-input"
                    placeholder="xxxx-xxxx-xxxx-xxxx"
                    :includeUploadButton="true"
                    uploadOnClick="uploadTextFile()"
                />

                <div class="nav-buttons">
                    <button type="submit" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
                </div>
            </form>

            <p class="red-text" id="backup-alert-message"></p>
            <button onclick="switchSlide(4)" class="btn-md">{{ $translation["HS-ForgottenBackup"] }}</button>

        </div>

        <div class="slide" data-index="3">
            <h2>{{ $translation["HS-PasskeyIs"] }}</h2>
            <h3 id="passkey-field" class="demo-hash"></h3>
            <div class="nav-buttons">
                <button onclick="redirectToChat()" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>

            </div>
        </div>

        <div class="slide" data-index="4">
            <h2>{{ $translation["HS-LostBothT"] }}</h2>
            <h3>{{ $translation["HS-LostBothB"] }}</h3>
            <div class="nav-buttons">
                <button onclick="requestProfileReset()" class="btn-lg-fill align-end">{{ $translation["HS-ResetProfile"] }}</button>
            </div>
        </div>

        {{-- Error Slide --}}
        <div class="slide" data-index="5">
            <h2>{{ $translation["HS-ErrorTitle"] ?? "Fehler beim Handshake" }}</h2>
            <h3>{{ $translation["HS-ErrorMessage"] ?? "Der Handshake-Prozess ist fehlgeschlagen. Bitte versuchen Sie es erneut oder melden Sie sich ab." }}</h3>
            <div class="nav-buttons">
                <button onclick="handleHandshakeError()" class="btn-lg-fill align-end">{{ $translation["HS-Logout"] ?? "Abmelden und Daten bereinigen" }}</button>
            </div>
        </div>

        {{-- System Passkey Backup Recovery Slide --}}
        <div class="slide" data-index="6">
            <div style="max-width: 380px; margin: 0 auto; width: 100%; padding: 0 1rem;">
            @if(config('auth.passkey_webauthn') && ($userInfo->webauthn_pk ?? false))
                {{-- User has WebAuthn Passkey - Show auto-authentication --}}
                <div id="webauthn-auto-login-section">
                    <h3>{{ $translation["HS-AuthenticatingWithPasskey"] ?? "Authenticating with Passkey" }}</h3>
                    <p style="text-align: center; margin: 2rem 0; opacity: 0.9;">
                        {{ $translation["HS-PleaseAuthenticate"] ?? "Please authenticate with your passkey..." }}
                    </p>
                    <p class="red-text" id="webauthn-auto-error" style="display: none; text-align: center; width: 100%;"></p>
                </div>
                
                {{-- Fallback manual input (hidden initially, shown on error) --}}
                <div id="manual-backup-section" style="display: none; width: 100%;">
            @else
                {{-- User has NO WebAuthn Passkey - Show manual input --}}
                <div id="manual-backup-section" style="">
            @endif
                    <h3 id="manual-backup-title">{{ $translation["HS-EnterBackupMsg"] }}</h3>
                    
                    <p style="text-align: left;">
                        {{ $translation["HS-EnterBackupDescription"] ?? "To access your encrypted data on this device, you must enter your recovery code. This was sent to you by email during registration." }}
                    </p>

                    {{-- Manual Backup Hash Entry Section --}}
                    <form id="backup-recovery-system-form" autocomplete="on" onsubmit="event.preventDefault(); extractPasskeySystem();" style="width: 100%;">
                        {{-- Hidden username field for password manager context (with backup suffix to create separate credential) --}}
                        <input 
                            type="text" 
                            name="username" 
                            autocomplete="username" 
                            value="{{ ($userInfo['username'] ?? '') . '@backup' }}"
                            style="display: none;"
                            readonly
                        />

                        <div style="width: 100%;">
                            <x-backup-hash-input 
                                id="backup-hash-input-system"
                                placeholder="xxxx-xxxx-xxxx-xxxx"
                                :includeUploadButton="false"
                            />
                        </div>

                        <button type="submit" class="btn-lg-fill" style="width: 100%; display: block; margin-top: 1.5rem;">
                            {{ $translation["Continue"] }}
                        </button>
                    </form>

                    <p class="red-text" id="backup-alert-message-system" style="display: none; text-align: center; margin-top: 1rem; width: 100%;"></p>
        
                    @if(config('auth.passkey_webauthn'))
                    <div style="text-align: center; margin: 1.5rem 0; opacity: 0.5;">
                        <span>{{ $translation["Or"] ?? "or" }}</span>
                    </div>
                    
                    <button onclick="switchSlide('6.1')" class="btn-lg-fill" style=" width: 100%; margin-bottom: 1rem;">
                        {{ $translation["HS-CreatePasskey"] ?? "Create Passkey" }}
                    </button>
                    @endif
                    
                    <button onclick="switchSlide(7)" class="btn-md" style="width: 100%; display: block;">
                        {{ $translation["HS-ForgottenBackup"] }}
                    </button>

                </div>
            </div>
        </div>

        {{-- System Passkey Lost Backup Slide --}}
        <div class="slide" data-index="7">
            <h2>{{ $translation["HS-LostBackupT"] ?? "Du hast deinen Wiederherstellungscode verloren?" }}</h2>
            <h3>{{ $translation["HS-LostBackupB"] ?? "Leider können wir, wenn du deinen Wiederherstellungscode verloren hast, deine Profildaten nicht wiederherstellen. Um fortzufahren, müssen wir dein Profil zurücksetzen und dir einen neuen Wiederherstellungscode einrichten. Dadurch werden alle deine vorherigen Daten, einschließlich aller Chats und Räume, entfernt. Möchtest du fortfahren?" }}</h3>
            <div class="nav-buttons">
                <button onclick="requestProfileReset()" class="btn-lg-fill align-end">{{ $translation["HS-ResetProfile"] }}</button>
            </div>
        </div>

        @if(config('auth.passkey_webauthn'))
        {{-- WebAuthn Passkey Login Slide --}}
        <div class="slide" data-index="6.1">
            <div style="max-width: 380px; margin: 0 auto; width: 100%; padding: 0 1rem;">
                <h3>{{ $translation["HS-CreatePasskey"] ?? "Create Passkey" }}</h3>
                
                <p style="text-align: left; margin: 2rem 0; font-size: 0.95rem; line-height: 1.5; opacity: 0.9;">
                    {{ $translation["HS-CreatePasskeyDescription"] ?? "Sign in to your account easily and securely with a cross-device passkey. Your passkey is securely stored in a password manager on your devices and is never shared with anyone." }}
                </p>
                
                @if($userInfo->webauthn_pk ?? false)
                {{-- Info Banner for users who already have a passkey --}}
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: start; gap: 0.75rem;">
                        <span style="font-size: 1.25rem; flex-shrink: 0;">ℹ️</span>
                        <div style="font-size: 0.9rem; line-height: 1.5; color: #856404;">
                            <strong>{{ $translation["HS-PasskeyAlreadyRegistered"] ?? "You already have a passkey registered." }}</strong><br>
                            {{ $translation["HS-PasskeyResetInfo"] ?? "Your old passkey becomes invalid, for example, when you reset your account. After resetting, you will receive a new backup code by email. To create a working passkey, you must always use the most recent backup code." }}
                        </div>
                    </div>
                </div>
                @endif
                
                {{-- Passkey Registration Section --}}
                <div id="passkey-registration-section" style="width: 100%;">
                    <button 
                        id="show-passkey-registration-btn"
                        type="button"
                        onclick="showPasskeyRegistrationForm()" 
                        class="btn-lg-fill"
                        style="width: 100%; display: block;"
                    >
                        {{ $translation["HS-RegisterNewPasskey"] ?? "Register New Passkey" }}
                    </button>
                    
                    {{-- Hidden registration form --}}
                    <form id="passkey-registration-form" autocomplete="off" style="display: none; margin-top: 1.5rem; width: 100%;" onsubmit="event.preventDefault(); registerNewWebAuthnPasskey();">
                        <p style="text-align: left; margin-bottom: 1rem; font-size: 0.9rem;">
                            {{ $translation["HS-EnterBackupHash"] ?? "Enter the last recovery code you received by email during registration or account reset. On your next login, the code will be automatically filled from the passkey you create here." }}
                        </p>
                        
                        <div style="width: 100%;">
                            {{-- Custom backup hash input without password semantics to prevent save prompt --}}
                            <div class="backup-hash-row">
                                <div class="password-input-wrapper">
                                    <input 
                                        id="backup-hash-input-registration" 
                                        name="backup_code"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="xxxx-xxxx-xxxx-xxxx"
                                        class="backup-hash-input"
                                        style="-webkit-text-security: disc; -moz-text-security: disc; text-security: disc;"
                                    />
                                    <div class="btn-xs backup-visibility-toggle" data-target="backup-hash-input-registration">
                                        <x-icon name="eye" class="eye-icon"/>
                                        <x-icon name="eye-off" class="eye-off-icon" style="display: none;"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-lg-fill" style="width: 100%; display: block; margin-top: 1.5rem;">
                            {{ $translation["HS-CreatePasskey"] ?? "Create Passkey" }}
                        </button>
                    </form>
                    
                    <p class="red-text" id="passkey-registration-error" style="display: none; text-align: center; margin-top: 1rem; width: 100%;"></p>
                    <p class="green-text" id="passkey-registration-success" style="display: none; text-align: center; margin-top: 1rem; width: 100%;"></p>
                </div>
                
                {{-- Login with existing passkey
                <div style="text-align: center; margin: 1.5rem 0; opacity: 0.5;">
                    <span>{{ $translation["Or"] ?? "oder" }}</span>
                </div>
                
                 
                <button 
                    id="passkey-login-btn" 
                    onclick="loginWithBackupHashPasskey()" 
                    class="btn-lg-fill"
                    style="width: 100%; display: block;"
                >
                    {{ $translation["HS-Authenticate"] ?? "Authentifizieren" }}
                </button>
                
                <p class="red-text" id="passkey-alert-message" style="display: none; text-align: center; margin-top: 1rem; width: 100%;"></p>
                <p class="green-text" id="passkey-success-message" style="display: none; text-align: center; margin-top: 1rem; width: 100%;"></p>
            </div>
             --}}
        </div>
        @endif


    </div>
</div>

<div class="slide-back-btn" onclick="switchBackSlide()">
    <x-icon name="chevron-left"/>
</div>

<script>
    let userInfo = @json($userInfo);
    let passkeyMethod = @json(config('auth.passkey_method', 'user'));
    const serverKeychainCryptoData = @json($keychainData);
    window.translations = @json($translation);
    const hasWebAuthnPasskey = @json($userInfo->webauthn_pk ?? false);

    window.addEventListener('DOMContentLoaded', async function (){

        if(await getPassKey()){
            try {
                console.log('Attempting to sync keychain with stored passkey...');
                await syncKeychain(serverKeychainCryptoData);
                console.log('Keychain synced successfully');
                window.location.href = '/chat';
            } catch (error) {
                console.error('Error syncing keychain with stored passkey:', error);
                console.warn('Stored passkey is invalid or outdated. Clearing and requesting new authentication.');
                
                // Clear invalid passkey from localStorage and global variable
                if (typeof passKey !== 'undefined') {
                    passKey = undefined;
                }
                localStorage.removeItem(`${userInfo.username}PK`);
                
            // Show appropriate authentication method based on config
            @if(config('auth.passkey_method') === 'system')
                // Show backup recovery for system passkeys without OTP
                switchSlide(6);
            @else
                // Show manual passkey input for user-defined passkeys
                switchSlide(1);
            @endif                setTimeout(() => {
                    if(@json($activeOverlay)){
                        setOverlay(false, true)
                    }
                }, 100);
            }
        }
        else{
            console.log('No passkey found, opening authentication panel...');
            
            // Check config for passkey method
            @if(config('auth.passkey_method') === 'system')
                @if(config('auth.passkey_webauthn') && ($userInfo->webauthn_pk ?? false))
                    // User has WebAuthn passkey - go to backup recovery (Slide 6)
                    switchSlide(6);
                @else
                    // User has no WebAuthn passkey - use legacy system passkey verification
                    verifyGeneratedPassKey();
                @endif
            @else
                switchSlide(1); // Show manual passkey input (default behavior)
                
                // Auto-fill passkey from WebAuthn if user has webauthn_pk (only for 'user' passkey method to avoid conflicts with Slide 6)
                @if(config('auth.passkey_webauthn') && ($userInfo->webauthn_pk ?? false))
                setTimeout(async () => {
                    await autoFillPasskeyFromWebAuthn();
                }, 500);
                @endif
            @endif

            setTimeout(() => {
                if(@json($activeOverlay)){
                    setOverlay(false, true)
                }
            }, 100);
        }
    });


    document.addEventListener('DOMContentLoaded', function () {
        initializePasskeyInputs(false);
        
        // Initialize backup hash visibility toggles
        const backupToggles = document.querySelectorAll('.backup-visibility-toggle');
        backupToggles.forEach(toggle => {
            toggle.addEventListener('click', function () {
                const inputId = this.getAttribute('data-target');
                const input = document.getElementById(inputId);
                const eye = this.querySelector('.eye-icon');
                const eyeOff = this.querySelector('.eye-off-icon');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    eye.style.display = 'none';
                    eyeOff.style.display = 'inline-block';
                } else {
                    input.type = 'password';
                    eye.style.display = 'inline-block';
                    eyeOff.style.display = 'none';
                }
            });
        });
    });




    // Function to handle handshake errors
    function handleHandshakeError() {
        try {
            logout();
            cleanupUserData();
        } catch (error) {
            console.error('Error during cleanup:', error);
            // Force redirect to login even if cleanup fails
            window.location.href = '/login';
        }
    }
</script>

{{-- Auto Passkey Generation Module --}}
<script src="{{ asset('js/auto_passkey_generation.js') }}"></script>

@endsection
