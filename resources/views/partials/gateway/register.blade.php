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
    initializeRegistration();
    window.addEventListener('DOMContentLoaded', switchSlide(1));

    setTimeout(() => {
        if(@json($activeOverlay)){
            setOverlay(false, true)
        }
    }, 100);
</script>







@endsection
