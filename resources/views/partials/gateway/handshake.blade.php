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
            <button onclick="switchSlide(2)" class="btn-md">{{ $translation["HS_ForgottenPasskey"] }}</button>

        </div>


        <div class="slide" data-index="2">
            <h3>{{ $translation["HS_EnterBackupMsg"] }}</h3>

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
            <h2>{{ $translation["HS_PasskeyIs"] }}</h2>
            <h3 id="passkey-field" class="demo-hash"></h3>
            <div class="nav-buttons">
                <button onclick="redirectToChat()" class="btn-lg-fill align-end">{{ $translation["Continue"] }}</button>

            </div>
        </div>

        <div class="slide" data-index="4">
            <h2>{{ $translation["HS_LostBothT"] }}</h2>
            <h3>{{ $translation["HS_LostBothB"] }}</h3>
            <div class="nav-buttons">
                <button onclick="requestProfileReset()" class="btn-lg-fill align-end">{{ $translation["HS_ResetProfile"] }}</button>
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

            // Input filter for allowed characters
            input.addEventListener('beforeinput', function (event) {
                if (event.inputType.startsWith('insert')) {
                    if (!/^[A-Za-z0-9!@#$%^&*()_+-]+$/.test(event.data)) {
                        event.preventDefault();
                        console.log('bad input');
                        input.parentElement.style.border = '1px solid red'

                        setTimeout(() => {
                            input.parentElement.style.border = 'var(--border-stroke-thin)';
                            console.log('back');
                        }, 100);
                    }
                }
            });

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




</script>


@endsection