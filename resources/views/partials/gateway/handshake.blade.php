@extends('layouts.gateway')
@section('content')



<div class="wrapper">

    <div class="container">
            
        <div class="slide" data-index="1">
            <h3>{{ $translation["HS-EnterPasskeyMsg"] }}</h3>

            <input id="passkey-input" type="text">

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

        </div>

        <div class="slide" data-index="3">
            <h2>{{ $translation["HS-PasskeyIs"] }}</h2>
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
            await syncKeychain(serverKeychainCryptoData);
            // console.log('keychain synced');
            window.location.href = '/chat';
        }
        else{
            // console.log('opening passkey enter');
            window.addEventListener('DOMContentLoaded', switchSlide(1));
            setTimeout(() => {
                if(@json($activeOverlay)){
                    setOverlay(false, true)
                }
            }, 100);
        }
    });
</script>


@endsection