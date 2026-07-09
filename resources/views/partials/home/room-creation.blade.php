<div class="dy-main-content" id="room-creation">
    <div class="panel-container">

        <div class="scroll-container">
            <div class="scroll-panel">

                <div class="inputs-list">
                    <h1 class="zero-b-margin">{{ __("CreateRoom") }}</h1>

                    <div class="row zero-v-margin">
                        <div class="prop-panel-grid">

                            <div class="avatar-editable" onclick="selectRoomAvatar(this, false)">
                                <img class="selectable-image" id="room-creation-avatar"   alt="">
                                <div class="edit">
                                    <x-icon name="new"/>
                                </div>
                            </div>

                            <div class="prop-panel-titles">
                                <div class="chat-name-panel text-cont flex-col zero-v-margin">
                                    <label>{{ __("RoomName") }}</label>
                                    <input
                                        class="text-field chat-name zero-v-margin singleLineTextarea"
                                        id="chat-name-input"
                                        placeholder="{{ __("PH_ChooseName") }}"
                                        maxlength="50"></input>
                                </div>
                            </div>

                        </div>
                    </div>


                    <div class="row flex-col top-gap-2">
                        <label>{{ __("RoomDesc") }}</label>
                        <textarea
                            class="text-input"
                            placeholder="{{ __("PH_ChooseDescription") }}"
                            name="room-description"
                            id="room-description-input"
                            maxlength="300"
                            oninput="resizeInputField(this)" ></textarea>
                    </div>
                    <div class="row flex-col  top-gap-2">
                        <label>{{ __("SystemPrompt") }}</label>
                        <textarea
                            class="text-input fit-height"
                            placeholder="{{ __("PH_SystemPrompt") }}"
                            name="system-prompt"
                            id="system-prompt-input"
                            {{-- maxlength="300" --}}
                            oninput="resizeInputField(this)"></textarea>
                    </div>
                    <div class="row flex-col  top-gap-2">
                        @include('partials.home.components.add-members-section')
                    </div>

                </div>
            </div>
            <div class="confirm-panel top-gap-3">
                <p class="zero-v-margin red-text" id="alert-message"></p>
                <button class="btn-lg-stroke align-end" onclick="finishRoomCreation()">{{ __("Abort") }}</button>
                <button class="btn-lg-fill align-end" onclick="createNewRoom()">{{ __("CreateRoom") }}</button>
            </div>
        </div>
    </div>
</div>
