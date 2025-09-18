<div class="dy-main-content" id="room-control-panel">
    <div class="panel-container">

        <div class="closeButton" onclick="closeRoomCP()">
            <x-icon name="x"/>
        </div>

        <div class="scroll-container chat-info-panel">
            <div class="scroll-panel">


                <div class="row">
                    <div class="prop-panel-grid">

                            <div class="avatar-editable">
                                <img class="icon-img selectable-image" id="info-panel-chat-icon" alt="">
                                <div id="control-panel-chat-initials"></div>
                                <div class="edit admin-only" data-original-display="flex" onclick="selectRoomAvatar(this, true)">
                                    <x-icon name="new"/>
                                </div>
                            </div>

                        <div class="prop-panel-titles">
                            <div class="chat-name-panel text-cont">
                                <h1 class="text-field chat-name zero-v-margin" id="chat-name"></h1>

                                @include('partials.home.components.edit-panel', ['rightOut' => true, 'callbackFunction'=>'submitInfoField'])
                            </div>


                            <p class="zero-v-margin" id="chat-slug"></p>

                        </div>

                    </div>
                </div>


                <div class="row top-gap-3" id="members-panel">
                    <h4 class="label-header">{{ $translation["Members"] }}</h4>
                    <div class="members-list dynamic-grid">


                    </div>
                </div>

                <div class="row top-gap-3" id="description-panel">

                    <label class="label-header">{{ $translation["Description"] }}</label>
                    <div class="text-panel text-cont">
                        <p class="text-field" id="description-field"></p>
                        @include('partials.home.components.edit-panel', ['callbackFunction'=>'submitInfoField'])
                    </div>
                </div>


                <div class="row top-gap-3" id="system-prompt-panel">
                    <label class="label-header">{{ $translation["SystemPrompt"] }}</label>
                    <div class="text-panel text-cont">
                        <p class="text-field" id="system_prompt-field"></p>
                        @include('partials.home.components.edit-panel', ['callbackFunction'=>'submitInfoField'])
                    </div>
                </div>


                <div class="row flex-row top-gap-3 justify-content-end admin-only" id="remove-panel">
                    <button class="btn-lg-fill delete-btn align-end" onclick="requestDeleteRoom()">{{ $translation["Delete"] }}</button>
                </div>


            </div>
        </div>


    </div>
</div>

@include('partials.home.modals.image-selection-modal')
@include('partials.home.modals.member-info-modal')
