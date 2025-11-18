@extends('layouts.gateway')
@section('content')



<div class="wrapper">

    <div class="container">

        <div class="slide" data-index="1">
            <h3>{{ $translation["HS_EnterPasskeyMsg"] }}</h3>

            <form id="passkey-form"  autocomplete="off">

                <div class="password-input-wrapper">
                    <input
                        class="passkey-input"
                        placeholder="{{ $translation['Reg_SL5_PH1'] }}"
                        id="passkey-input"
                        type="text"
                        autocomplete="new-password"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                    />
                    <div class="btn-xs" id="visibility-toggle">
                        <x-icon name="eye" id="eye"/>
                        <x-icon name="eye-off" id="eye-off" style="display: none"/>
                    </div>
                </div>
            </form>

            <div class="nav-buttons">
                <button id="verifyEnteredPassKey-btn" onclick="verifyEnteredPassKey(this)" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
            </div>
            <p class="red-text" id="alert-message"></p>
            <button onclick="switchSlide(2)" class="btn-md">{{ $translation["HS-ForgottenPasskey"] }}</button>

        </div>


        <div class="slide" data-index="2">
            <h3>{{ $translation["HS-EnterBackupMsg"] }}</h3>

            <div class="backup-hash-row">
                <input id="backup-hash-input" type="text">
                <button class="btn-sm border" onclick="uploadTextFile()">
                    <x-icon name="upload"/>
                </button>
            </div>

            <div class="nav-buttons">
                <button onclick="extractPasskey()" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
            </div>

            <p class="red-text" id="backup-alert-message"></p>
            <button onclick="switchSlide(4)" class="btn-md">{{ $translation["HS_ForgottenBackup"] }}</button>

        </div>

        <div class="slide" data-index="3">
            <h2>{{ $translation["HS_LostBothT"] }}</h2>
            <h3>{{ $translation["HS_LostBothB"] }}</h3>
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
            <h3>{{ $translation["HS-EnterBackupMsg"] }}</h3>

            <div class="backup-hash-row">
                <input id="backup-hash-input-system" type="text">
            </div>

            <div class="nav-buttons">
                <button onclick="extractPasskeySystem()" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
            </div>

            <p class="red-text" id="backup-alert-message-system"></p>
            <button onclick="switchSlide(7)" class="btn-md">{{ $translation["HS-ForgottenBackup"] }}</button>

        </div>

        {{-- System Passkey Lost Backup Slide --}}
        <div class="slide" data-index="7">
            <h2>{{ $translation["HS-LostBackupT"] ?? "Du hast deinen Wiederherstellungscode verloren?" }}</h2>
            <h3>{{ $translation["HS-LostBackupB"] ?? "Leider können wir, wenn du deinen Wiederherstellungscode verloren hast, deine Profildaten nicht wiederherstellen. Um fortzufahren, müssen wir dein Profil zurücksetzen und dir einen neuen Wiederherstellungscode einrichten. Dadurch werden alle deine vorherigen Daten, einschließlich aller Chats und Räume, entfernt. Möchtest du fortfahren?" }}</h3>
            <div class="nav-buttons">
                <button onclick="requestProfileReset()" class="btn-lg-fill align-end">{{ $translation["HS-ResetProfile"] }}</button>
            </div>
        </div>


        {{-- OTP Slide --}}
        <div class="slide" data-index="0">
            <h3>{{ $translation["HS-LoginCodeH"] }}</h3>
            <p class="slide-subtitle">
                {{ $translation["HS-LoginCodeT"] }}
            </p>

            {{-- Initial OTP Send Button --}}
            <div id="otp-send-container">
                <div class="nav-buttons">
                    <button id="send-otp-btn" onclick="sendOTP(this)" class="btn-lg-fill">{{ $translation["HS-LoginCodeB1"] }}</button>
                </div>
            </div>

            {{-- OTP Input Container (initially hidden) --}}
            <div id="otp-input-container" style="display: none;">
                <p class="slide-subtitle text-center text-primary mb-3">
                    {{ $translation["HS-LoginCodeM"] }} <span id="otp-email-display"></span>
                </p>

                {{-- Individual OTP input fields --}}
                <div class="otp-input-group">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="0">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="1">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="2">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="3">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="4">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="5">
                </div>

                <div class="nav-buttons">
                    <button id="verify-otp-btn" onclick="verifyOTP(this)" class="btn-lg-fill">{{ $translation["HS-LoginCodeB3"] }}</button>
                </div>

                {{-- Resend Button (initially hidden, appears after 60s cooldown) --}}
                <div id="resend-container" style="display: none; margin-top: 15px;">
                    <p class="red-text" id="alert-message" style="text-align: center;">{{ $translation["HS-LoginCodeT3"] }}</p>

                    <div class="nav-buttons">
                        <button id="resend-otp-btn" onclick="resendOTP(this)" class="btn-lg-fill">
                            {{ $translation["HS-LoginCodeB4"] }}
                        </button>
                    </div>
                </div>

                {{-- Timer Element (visible during active OTP session) --}}
                <div style="margin-top: 15px;">
                    <p id="otp-timer" style="text-align: center; color: var(--accent-color); font-weight: bold;">5:00</p>
                </div>
                {{-- --}}

            </div>

            {{-- Logout Button for OTP Slide --}}
            <div class="nav-buttons" style="margin-top: 20px;">
                <button onclick="logout()" class="btn-md">{{ $translation["HS-Logout"] ?? "Abmelden" }}</button>
            </div>

        </div>


        <div class="slide" data-index="4">
            <h2>{{ $translation["HS_PasskeyIs"] }}</h2>
            <h3 id="passkey-field" class="demo-hash"></h3>
            <div class="nav-buttons">
                <button onclick="redirectToChat()" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
            </div>
        </div>




    </div>
</div>

<div class="slide-back-btn" onclick="switchBackSlide()">
    <x-icon name="chevron-left"/>
</div>

<script>
    let userInfo = @json($userInfo);
    let passkeyMethod = @json(config('auth.passkey_method', 'user'));
    const serverKeychainCryptoData = @json($keychainData);
    const translations = @json($translation);
    const otpTimeout = @json(config('auth.passkey_otp_timeout', 300));

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

                // Clear invalid passkey from localStorage
                localStorage.removeItem('passkey');

                // Show appropriate authentication method based on config
                @if(config('auth.passkey_method') === 'system')
                    @if(config('auth.passkey_otp'))
                        // Show OTP dialog for system passkeys
                        switchSlide(0);
                    @else
                        // Show backup recovery for system passkeys without OTP
                        switchSlide(6);
                    @endif
                @else
                    // Show manual passkey input for user-defined passkeys
                    switchSlide(1);
                @endif

                setTimeout(() => {
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
                @if(config('auth.passkey_otp'))
                    // Show otp dialog
                    switchSlide(0);
                @else
                    // Go directly to chat interface
                    verifyGeneratedPassKey();
                @endif
            @else
                switchSlide(1); // Show manual passkey input (default behavior)
            @endif

            setTimeout(() => {
                if(@json($activeOverlay)){
                    setOverlay(false, true)
                }
            }, 100);
        }
    });


    document.addEventListener('DOMContentLoaded', function () {
        const inputWrappers = document.querySelectorAll('.password-input-wrapper');

        inputWrappers.forEach(wrapper => {
            const input = wrapper.querySelector('.passkey-input');
            const toggleBtn = wrapper.querySelector('.btn-xs');
            input.dataset.visible = 'false'

            // Initialize the real value in a dataset
            input.dataset.realValue = '';

            //random name will prevent chrome from auto filling.
            const rand = generateTempHash();
            input.setAttribute('name', rand);

            // Handle Enter key
            input.addEventListener('keypress', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    verifyEnteredPassKey(document.querySelector('#verifyEnteredPassKey-btn'));
                }
            });

            // Mask input and store real value
            input.addEventListener('input', function (e) {
                const realValue = input.dataset.realValue || '';
                const newValue = e.target.value;
                const oldLength = realValue.length;
                const newLength = newValue.length;

                let updated = realValue;
                if (newLength > oldLength) {
                    updated += newValue.slice(oldLength);
                } else if (newLength < oldLength) {
                    updated = updated.slice(0, newLength);
                }

                input.dataset.realValue = updated;

                if(input.dataset.visible === 'false'){
                    input.value = '*'.repeat(updated.length);
                }

            });

            // Prevent copy/cut/paste
            ['copy', 'cut', 'paste'].forEach(evt =>
                input.addEventListener(evt, e => e.preventDefault())
            );

            // Toggle visibility
            toggleBtn.addEventListener('click', function () {
                const real = input.dataset.realValue || '';
                const icons = toggleBtn.querySelectorAll('svg');
                const eye = icons[0];
                const eyeOff = icons[1];

                const isVisible = input.dataset.visible === 'true';
                if (!isVisible) {
                    input.value = real;
                    eye.style.display = 'none';
                    eyeOff.style.display = 'inline-block';
                    input.dataset.visible = 'true';
                }
                else {
                    input.value = '*'.repeat(real.length);
                    eye.style.display = 'inline-block';
                    eyeOff.style.display = 'none';
                    input.dataset.visible = 'false';
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
