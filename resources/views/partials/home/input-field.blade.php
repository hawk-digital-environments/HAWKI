


<div class="input-container admin-only editor-only" id="input-container">
    <div class="input-stats-container">
        <div class="isTypingStatus"></div>
        <div id="input-feedback-msg"></div>
    </div>

    <div class="input-controls" id="input-controls">
        @if(!$lite)
        <button class="btn-xs expand-btn fast-access-btn tooltip-parent" onclick="toggleRelativePanelClass('input-controls', this,'expanded')" aria-describedby="more-tooltip">
            <div class="icon" aria-hidden="true">
                <x-icon name="chevron-up"/>
            </div>
            <div class="tooltip" aria-hidden="true" id="more-tooltip">{{ $translation["MoreToolTip"] }}</div>
        </button>
        @endif

        <div class="minimized-content">
            <div class="left">

                @if($activeModule === 'chat')
                    <button class="btn-xs fast-access-btn" onclick="startNewChat()" aria-describedby="startnewchat-tooltip">
                        <x-icon name="new" aria-hidden="true"/>
                        <div class="tooltip" aria-hidden="true" id="startnewchat-tooltip">
                            {{ $translation["StartNewChat"] }}
                        </div>
                    </button>
                @endif

                @if(!$lite && $activeModule === 'chat')
                    <button class="btn-xs fast-access-btn" value="system_prompt_panel" onclick="toggleRelativePanelClass('input-controls', this,'expanded'); switchControllerProp(this, 'system_prompt_panel')" aria-describedby="systemprompt-tooltip">
                        <x-icon name="sliders" aria-hidden="true"/>
                        <div class="tooltip" aria-hidden="true" id="systemprompt-tooltip">
                            {{ $translation["SystemPrompt"] }}
                        </div>
                    </button>

                @endif

                @if(!$lite)
                    <button class="btn-xs fast-access-btn" value="export-panel" onclick="toggleRelativePanelClass('input-controls', this,'expanded'); switchControllerProp(this, 'export-panel')" aria-describedby="export-tooltip">
                        <x-icon name="download" aria-hidden="true"/>
                        <div class="tooltip" aria-hidden="true" id="export-tooltip">
                            {{ $translation["Export"] }}
                        </div>
                    </button>
                @endif

                @if($webSearchAvailable)
                <button id="websearch-btn" class="btn-xs fast-access-btn" onclick="selectWebSearchModel(this)" aria-describedby="websearch-tooltip">
                    <x-icon class="websearch-icon" name="world" aria-hidden="true"/>
                    <div class="tooltip" aria-hidden="true" id="websearch-tooltip">
                        {{ $translation["WebSearch"] }}
                    </div>
                </button>
                @endif



                <button class="btn-xs fast-access-btn file-upload file-upload-btn" onclick="selectFile(this)" aria-describedby="uploadfile-tooltip">
                    <x-icon name="paperclip" aria-hidden="true"/>
                    <div class="tooltip" aria-hidden="true" id="uploadfile-tooltip">
                        {{ $translation["UploadFile"] }}
                    </div>
                </button>


            </div>

            <div class="right">
                <div id="model-selectors">
                    <div class="burger-dropdown anchor-top-right" id="model-selector-burger">
                        @include('partials.home.components.models-list')
                    </div>
                    <button class="burger-btn-arrow burger-btn fast-access-btn tooltip-parent" onclick="openBurgerMenu('model-selector-burger', this, false, true, true)" aria-describedby="modelselector-tooltip">
                        <div class="icon" aria-hidden="true">
                            <x-icon name="chevron-up"/>
                        </div>
                        <div class="label model-selector-label" aria-hidden="true"></div>
                        <div class="tooltip" aria-hidden="true" id="modelselector-tooltip">{{ $translation["ModelSelectorToolTip"] }}</div>
                    </button>
                </div>
            </div>
        </div>

        @if(!$lite)
        <div class="expanded-content">

            <div class="expanded-left">
                <div class="controls-container scroll-container" tabindex="-1">

                    <div class="control-buttons scroll-panel">
                        @if($activeModule === 'chat')

                        <button class="btn-xs menu-item" value="" onclick="switchControllerProp(this); startNewChat(); toggleRelativePanelClass('input-controls', this,'expanded');">
                            <x-icon name="new"/>
                            <div class="label">{{ $translation["StartNewChat"] }}</div>
                        </button>
                        @endif

                        <button class="btn-xs menu-item" value="models_panel" onclick="switchControllerProp(this, 'models_panel')">
                            <x-icon name="layers"/>
                            <div class="label">{{ $translation["Models"] }}</div>
                        </button>

                        @if($activeModule === 'chat')
                        <button class="btn-xs menu-item" value="system_prompt_panel" onclick="switchControllerProp(this, 'system_prompt_panel')">
                            <x-icon name="sliders"/>
                            <div class="label">{{ $translation["SystemPrompt"] }}</div>
                        </button>
                        @endif

                        <button class="btn-xs menu-item" value="export-panel" onclick="switchControllerProp(this, 'export-panel')">
                            <x-icon name="download"/>
                            <div class="label">{{ $translation["Export"] }}</div>
                        </button>

                        </button>
                    </div>

                </div>
            </div>
            <div class="expanded-right">
                <div class="controls-props scroll-container">

                    <div class="scroll-panel" id="input-controls-props-panel">

                        <div id="system_prompt_panel" class="prop-content">
                            <div contenteditable class="system_prompt_field" id="system_prompt_field"></div>
                        </div>

                        <div id="models_panel" class="prop-content">
                            @include('partials.home.components.models-list')
                        </div>

                        <div id="export-panel" class="prop-content">

                            <button class="burger-item" id="export-btn-print" onclick="exportPrintPage()">
                                <div class="icon"></div>
                                <div class="label">{{ $translation["Print"] }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-pdf" onclick="exportAsPDF()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">PDF {{ $translation["Download"] }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-word" onclick="exportAsWord()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">Word {{ $translation["Download"] }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-csv" onclick="exportAsCsv()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">CSV {{ $translation["Download"] }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-json" onclick="exportAsJson()">
                                <div class="icon"></div>
                                <div class="label">JSON {{ $translation["Download"] }}</div>
                            </button>
                        </div>

                    </div>

                </div>

            </div>
        </div>
        @endif

    </div>
    <div class="input" id="0">
        <input type="file" class="file-upload-input" id="file-upload-input" style="display:none;"/>
        <div class="file-attachments">
            <div class="attachments-list">
            </div>
        </div>



        <div class="input-content">

            <div class="input-wrapper">
                <textarea
                    class="input-field"
                    type="text"

                    @if($activeModule === 'chat')

                        placeholder="{{ $translation['Input_Placeholder_Chat'] }}"
                        oninput="resizeInputField(this);"
                        onkeypress="onHandleKeydownConv(event)"

                    @elseif($activeModule === 'groupchat')

                        placeholder="{{ $translation['Input_Placeholder_Room'] ." ". config('hawki.aiHandle')}}"
                        oninput="resizeInputField(this); onGroupchatType()"
                        onkeypress="onHandleKeydownRoom(event)"

                    @endif

                    onfocus="onInputFieldFocus(this); toggleOffRelativeInputControl(this)"
                    onfocusout="onInputFieldFocusOut(this)"></textarea>
            </div>

            {{-- <div class="input-main-btn file-upload tooltip-parent">
                <input type="file" id="file-upload-input" style="display:none;" />
                <div class="file-upload-btn" onclick="selectFile()">
                    <x-icon name="paperclip"/>
                    <div class="label tooltip tt-abs-up">
                        upload file
                    </div>
                </div>
            </div> --}}

            <div class="input-main-btn input-send tooltip-parent">
                @if($activeModule === 'chat')
                    <button id="send-btn" onClick="onSendClickConv(this)" aria-describedby="send-tooltip">
                @elseif($activeModule === 'groupchat')
                    <button id="send-btn" onClick="onSendClickRoom(this)" aria-describedby="send-tooltip">
                @endif
                        <div id="send-icon" class="send-btn-icon" aria-hidden="true">
                            <x-icon name="arrow-up"/>
                        </div>
                        <div id="stop-icon" class="send-btn-icon" style="display:none" aria-hidden="true">
                            <x-icon name="stop"/>
                        </div>
                        <div id="loading-icon" class="send-btn-icon loading loading-lg" style="display:none" aria-hidden="true">
                            <div class="loading">
                                <x-icon name="loading"/>
                            </div>
                        </div>
                        <div class="label tooltip tt-abs-up" aria-hidden="true" id="send-tooltip">
                        {{ $translation["Send"] }}
                        </div>
                </button>
            </div>


            <button class="prompt-improvement-btn tooltip-parent" onclick="requestPromptImprovement(this, 'input')" aria-describedby="promptimprovement-tooltip">
                <div class="input-main-btn">
                    <x-icon name="vector" aria-hidden="true"/>
                    <div class="label tooltip tt-abs-up" aria-hidden="true" id="promptimprovement-tooltip">
                        {{ $translation["PromptImprovement"] }}
                    </div>
                </div>
            </button>

        </div>


    </div>

    @include('partials.home.dragDropOverlay')

</div>
