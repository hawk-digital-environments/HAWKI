@extends('layouts.gateway')
@section('content')



<div class="wrapper">

    <div class="container">

        <div class="slide" data-index="1">
            <h3>{{ $translation["HS-EnterPasskeyMsg"] }}</h3>

            <input id="passkey-input" type="password">

            <div class="nav-buttons">
                <button onclick="verifyEnteredPassKey(this)" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
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


    </div>
</div>

<div class="slide-back-btn" onclick="switchBackSlide()">
    <x-icon name="chevron-left"/>
</div>

<script>
    let userInfo = @json($userInfo);
    let passkeySecret = @json($passkeySecret);
    const serverKeychainCryptoData = @json($keychainData);
    const translations = @json($translation);
    const otpTimeout = @json(config('auth.passkey_otp_timeout', 300));

    window.addEventListener('DOMContentLoaded', async function (){

        if(await getPassKey()){
            console.log('keychain synced');
            await syncKeychain(serverKeychainCryptoData);
            window.location.href = '/chat';
        }
        else{
            console.log('opening passkey panel');
            
            // Check config for passkey method
            @if(config('auth.passkey_method') === 'auto')
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
<script src="{{ asset('js_v2.1.0/auto_passkey_generation.js') }}"></script>

{{-- OTP Functions Module --}}
<script src="{{ asset('js_v2.1.0/otp_functions.js') }}"></script>


@endsection
