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
                    <button type="button" class="btn-xs tooltip-parent" id="visibility-toggle" aria-describedby="visibility-tooltip">
                        <x-icon name="eye" id="eye" aria-hidden="true"/>
                        <x-icon name="eye-off" id="eye-off" style="display: none" aria-hidden="true"/>
                        <div class="tooltip tooltip-below" aria-hidden="true" id="visibility-tooltip">{{ $translation["DataKeyShowToolTip"] }}</div>
                    </button>
                </div>
            </form>

            <div class="nav-buttons">
                <button id="verifyEnteredPassKey-btn" onclick="verifyEnteredPassKey(this)" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
            </div>
            <p class="red-text" id="alert-message"></p>
            <button onclick="switchSlide(2)" class="btn-text btn-md">{{ $translation["HS_ForgottenPasskey"] }}</button>
        </div>


        <div class="slide" data-index="2">
            <h3>{{ $translation["HS_EnterBackupMsg"] }}</h3>

            <div class="backup-hash-row">
                <input id="backup-hash-input" type="text">
                <button class="btn-sm border fast-access-btn tooltip-parent" onclick="uploadTextFile()" aria-describedby="uploadTextFile-tooltip">
                    <x-icon name="upload" aria-hidden="true"/>
                    <div class="tooltip" aria-hidden="true" id="uploadTextFile-tooltip">{{ $translation["UploadTextFileTooltip"] }}</div>
                </button>
            </div>

            <div class="nav-buttons">
                <button onclick="extractPasskey()" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>
            </div>

            <p class="red-text" id="backup-alert-message"></p>
            <button onclick="switchSlide(3)" class="btn-md btn-text">{{ $translation["HS_ForgottenBackup"] }}</button>

        </div>

        <div class="slide" data-index="3">
            <h2>{{ $translation["HS_LostBothT"] }}</h2>
            <h3>{{ $translation["HS_LostBothB"] }}</h3>
            <div class="nav-buttons">
                <button onclick="requestProfileReset()" class="btn-lg-fill align-end">{{ $translation["HS_ResetProfile"] }}</button>
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
    const serverKeychainCryptoData = @json($keychainData)

    window.addEventListener('DOMContentLoaded', async function (){

        if(await getPassKey()){
            console.log('keychain synced');
            await syncKeychain(serverKeychainCryptoData);
            window.location.href = '/chat';
        }
        else{
            console.log('opening passkey panel');
            switchSlide(1)
            setTimeout(() => {
                if(@json($activeOverlay)){
                    setOverlay(false, true)
                }
            }, 100);
        }
    });


    document.addEventListener('DOMContentLoaded', function () {
        initializePasskeyInputs(false, @json($allowPaste), @json($charLimit));
    });




</script>


@endsection
