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
            <p class="red-text" id="alert-message"></p>

            <div class="nav-buttons">
                <button class="btn-lg-fill" onclick="checkPasskey()">{{ $translation["Save"] }}</button>
            </div>

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
        initializePasskeyInputs(true);
    });

    setTimeout(() => {
        if(@json($activeOverlay)){
            setOverlay(false, true)
        }
    }, 100);
</script>







@endsection
