@extends('layouts.home')
@section('content')

<div class="scroll-container">
    <div class="scroll-panel">
        <div class="inputs-list profile-container" id="profile">

            <h1 class="zero-b-margin">{{ $translation["Profile"] }}</h1>
            <h3 class="label-header">{{ $translation["General"] }}</h3>
            <div class="userinfo-row">
                <div class="avatar-editable" onclick="selectProfileAvatar(this)">
                    <img class="icon-img selectable-image" alt="">
                    <div class="user-inits" style="display: none"></div>
                    <div class="edit">
                        <x-icon name="new"/>
                    </div>
                </div>

                <div class="">
                    <div class="chat-name-panel text-cont">
                        <h1 class="text-field zero-v-margin" id="profile-name"></h1>
                        @include('partials.home.components.edit-panel', ['rightOut' => true, 'callbackFunction' => 'updateUserInformation'])
                    </div>

                    <p class="zero-v-margin" id="profile-username"></p>

                </div>
            </div>

            <div class="row">
                <h4 class="label-header">{{ $translation["Bio"] }}</h4>
                <textarea
                    class="text-input fit-height"
                    placeholder="{{  $translation["PH_AboutMe"] }}"
                    name="bio"
                    id="bio-input"
                    maxlength="300"
                    oninput="resizeInputField(this); checkBioUpdate()"></textarea>

                <button class="btn-md-stroke save-btn" onclick="updateUserInformation()">{{ $translation["Save"] }}</button>
            </div>


            <h3 class="label-header top-gap-3">{{ $translation["PersonalData"] }}</h3>
            <div class="">
                @if(config('sanctum.allow_user_token') && config('sanctum.allow_external_communication'))
                    <button class="btn-md-txt" onclick="toggleAccessTokensPanel(true)">{{ $translation["AccessTokens"] }}</button>
                @else
                    <p class="gray-text zero-v-margin">{{ $translation["AccessTokens"] }}</p>
                    <p class="sub-descript">{{ $translation["Api_Warning"] }}</p>
                @endif

                <button class="btn-md-txt red-text top-gap-2" onclick="clearPersonalData()">{{ $translation["ClearLocalData"] }}</button>
            </div>

        </div>
    </div>
</div>

    @if(config('sanctum.allow_user_token') && config('sanctum.allow_external_communication'))
        @include('partials.home.modals.access-tokens-modal')
    @endif
    @include('partials.home.modals.image-selection-modal')


<script>
    window.addEventListener('DOMContentLoaded', async function (){
        initializeUserProfile();
    });
</script>
@endsection
