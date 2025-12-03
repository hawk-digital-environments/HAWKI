@extends('layouts.gateway')
@section('content')



<div class="wrapper">

    <div class="container">

        <div class="slide" data-index="1">
            <h3>{{ $translation["HS_EnterPasskeyMsg"] }}</h3>

            <form id="passkey-form"  autocomplete="off">
                <x-passkey-input 
                    id="passkey-input"
                    placeholder="{{ $translation['HS_PH_EnterDatakey'] ?? 'Gib hier deinen Datakey ein' }}"
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
            <h3>{{ $translation["HS-EnterBackupMsg"] }}</h3>

            <form id="backup-recovery-system-form" autocomplete="on" onsubmit="event.preventDefault(); extractPasskeySystem();">
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
                    id="backup-hash-input-system"
                    placeholder="xxxx-xxxx-xxxx-xxxx"
                    :includeUploadButton="false"
                />

                <div class="nav-buttons">
                    <button type="submit" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
                </div>
            </form>
            
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
                // Go directly to chat interface
                verifyGeneratedPassKey();
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
        initializePasskeyInputs(false);
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
