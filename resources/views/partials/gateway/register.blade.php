@extends('layouts.gateway')
@section('content')


<div class="wrapper">

    <div class="container">

        <div class="slide" data-index="0">
        </div>

        <div class="slide" data-index="1">
            <h1>{{ $translation["Reg_SL1_H"] }}</h1>
            <div class="slide-content">
                <p>{{ $translation["Reg_SL1_T"] }}</p>
            </div>
            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="switchSlide(2)">{{ $translation["Reg_SL1_B"] }}</button>
            </div>
        </div>

        <div class="slide" data-index="2">
            <h1>{{ $translation["Reg_SL2_H"] }}</h1>
            <div class="slide-content">
                <p>
                    {{ $translation["Reg_SL2_T"] }}
                </p>
            </div>
            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="switchSlide(3)">{{ $translation["Reg_SL2_B"] }}</button>
            </div>
        </div>

        <div class="slide" data-index="3" id="policy">
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
        </div>



        <div class="slide" data-index="5">
            <h1>{{ $translation["Reg_SL5_H"] }}</h1>
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
                        name="not_a_password_input"
                    />
                    <div class="btn-xs" id="visibility-toggle">
                        <x-icon name="eye" id="eye"/>
                        <x-icon name="eye-off" id="eye-off" style="display: none"/>
                    </div>
                </div>
                
                <div id="passkey-repeat" class="password-input-wrapper top-gap-2" style="display:none" >
                    <input
                        class="passkey-input"
                        placeholder="{{  $translation["Reg_SL5_PH2"] }}"
                        type="text"
                        autocomplete="new-password"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        name="not_a_password_input"

                    />    
                    <div class="btn-xs" id="visibility-toggle">
                        <x-icon name="eye" id="eye"/>
                        <x-icon name="eye-off" id="eye-off" style="display: none"/>
                    </div>
                </div>
            </form>
            <p class="slide-subtitle top-gap-2">
                {!! $translation["Reg_SL5_T"] !!}
            </p>
            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="checkPasskey()">{{ $translation["Save"] }}</button>
            </div>
            <p class="red-text" id="alert-message"></p>

        </div>

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
    const translation = @json($translation);

    initializeRegistration();
    document.addEventListener('DOMContentLoaded', function(){
        switchSlide(1);
        cleanupUserData();
    });
    
    document.addEventListener('DOMContentLoaded', function () {
        const inputWrappers = document.querySelectorAll('.password-input-wrapper');

        inputWrappers.forEach(wrapper => {
            const input = wrapper.querySelector('.passkey-input');
            const toggleBtn = wrapper.querySelector('.btn-xs');
            input.dataset.visible = 'false'

            //random name will prevent chrome from auto filling.
            const rand = generateTempHash();
            input.setAttribute('name', rand);

            // Initialize the real value in a dataset
            input.dataset.realValue = '';

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
                        }, 200);
                    }
                }
            });

            // Handle Enter key
            input.addEventListener('keypress', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    checkPasskey();
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




    setTimeout(() => {
        if(@json($activeOverlay)){
            setOverlay(false, true)
        }
    }, 100);
</script>







@endsection
