


<div class="input-container admin-only editor-only" id="input-container">
    <div class="input-stats-container">
        <div class="isTypingStatus"></div>
        <div id="input-feedback-msg"></div>
    </div>

    <div class="input-controls" id="input-controls">
        @if(!$lite)
        @php $tooltipId = str()->uuid() @endphp
        <button class="btn-xs expand-btn fast-access-btn tooltip-parent" onclick="toggleRelativePanelClass('input-controls', this,'expanded')" aaria-labelledby="{{ $tooltipId }}">
            <div class="icon" aria-hidden="true">
                <x-icon name="chevron-up"/>
            </div>
            <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("MoreToolTip") }}</div>
        </button>
        @endif

        <div class="minimized-content">
            <div class="left">

                @if($activeModule === 'chat')
                    @php $tooltipId = str()->uuid() @endphp
                    <button class="btn-xs fast-access-btn" onclick="startNewChat()" aria-labelledby="{{ $tooltipId }}">
                        <x-icon name="new" aria-hidden="true"/>
                        <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">
                            {{ __("StartNewChat") }}
                        </div>
                    </button>
                @endif

                @if(!$lite && $activeModule === 'chat')
                    @php $tooltipId = str()->uuid() @endphp
                    <button class="btn-xs fast-access-btn" value="system_prompt_panel" onclick="toggleRelativePanelClass('input-controls', this,'expanded'); switchControllerProp(this, 'system_prompt_panel')" aria-labelledby="{{ $tooltipId }}">
                        <x-icon name="sliders" aria-hidden="true"/>
                        <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">
                            {{ __("SystemPrompt") }}
                        </div>
                    </button>

                @endif

                @if(!$lite)
                    @php $tooltipId = str()->uuid() @endphp
                    <button class="btn-xs fast-access-btn" value="export-panel" onclick="toggleRelativePanelClass('input-controls', this,'expanded'); switchControllerProp(this, 'export-panel')" aria-labelledby="{{ $tooltipId }}">
                        <x-icon name="download" aria-hidden="true"/>
                        <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">
                            {{ __("Export") }}
                        </div>
                    </button>
                @endif




            </div>

            <div class="right">
                <div id="model-selectors">

                    <div class="burger-dropdown anchor-top-right" id="model-selector-burger">
                        @include('partials.home.components.models-list', ['selectModel' => true])
                    </div>
                    @php $tooltipId = str()->uuid() @endphp
                    <button class="burger-btn-arrow burger-btn tooltip-parent" onclick="openInputModelSelector(this)" aria-labelledby="{{ $tooltipId }}">
                        <div class="icon" aria-hidden="true">
                            <x-icon name="chevron-up"/>
                        </div>
                        <div class="label model-selector-label" aria-hidden="true"></div>
                        <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("ModelSelectorToolTip") }}</div>
                    </button>
                </div>

                @php $tooltipId = str()->uuid() @endphp
                <button class="btn-xs model-params-btn tooltip-parent " onclick="openMsgParamsControlPanel(this)" aria-labelledby="{{ $tooltipId }}">
                    <x-icon name="tool" aria-hidden="true"/>
                    <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("ModelParamsControlToolTip") }}</div>
                </button>
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
                            <div class="label">{{ __("StartNewChat") }}</div>
                        </button>
                        @endif

                        <button class="btn-xs menu-item" value="models_panel" onclick="switchControllerProp(this, 'models_panel')">
                            <x-icon name="layers"/>
                            <div class="label">{{ __("Models") }}</div>
                        </button>

                        @if($activeModule === 'chat')
                        <button class="btn-xs menu-item" value="system_prompt_panel" onclick="switchControllerProp(this, 'system_prompt_panel')">
                            <x-icon name="sliders"/>
                            <div class="label">{{ __("SystemPrompt") }}</div>
                        </button>
                        @endif

                        <button class="btn-xs menu-item" value="export-panel" onclick="switchControllerProp(this, 'export-panel')">
                            <x-icon name="download"/>
                            <div class="label">{{ __("Export") }}</div>
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
                            @include('partials.home.components.models-list', ['selectModel' => true])
                        </div>

                        <div id="export-panel" class="prop-content">

                            <button class="burger-item" id="export-btn-print" onclick="exportPrintPage()">
                                <div class="icon"></div>
                                <div class="label">{{ __("Print") }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-pdf" onclick="exportAsPDF()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">PDF {{ __("Download") }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-word" onclick="exportAsWord()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">Word {{ __("Download") }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-csv" onclick="exportAsCsv()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">CSV {{ __("Download") }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-json" onclick="exportAsJson()">
                                <div class="icon"></div>
                                <div class="label">JSON {{ __("Download") }}</div>
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
                <div class="input-scroll-panel">
                <textarea
                    class="input-field"
                    type="text"

                    @if($activeModule === 'chat')

                        placeholder="{{ __('Input_Placeholder_Chat') }}"
                        oninput="resizeInputField(this);"
                        onkeypress="onHandleKeydownConv(event)"

                    @elseif($activeModule === 'groupchat')

                        placeholder="{{ __('Input_Placeholder_Room') ." ". config('hawki.aiHandle')}}"
                        oninput="resizeInputField(this); onGroupchatType()"
                        onkeypress="onHandleKeydownRoom(event)"

                    @endif

                    onfocus="onInputFieldFocus(this); toggleOffRelativeInputControl(this)"
                    onfocusout="onInputFieldFocusOut(this)"></textarea>

                </div>

                <div class="toolkit-bar">

                    <div class="buttons-bar">
                        <button class="btn-xs fast-access-btn file-upload file-upload-btn" onclick="selectFile(this)">
                            <x-icon name="paperclip"/>
                            <div class="tooltip tooltip-left">
                                {{ __("UploadFile") }}
                            </div>
                        </button>


                        <div id="tool-selection-btn" class="btn-xs fast-access-btn tooltip-parent" onclick="openBurgerMenu('tool-selection-panel', this, false, true, true, true)">
                            <x-icon name="plus"/>
                            <div class="label tooltip">
                                {{ __("Add_Tool") }}
                            </div>
                            @include('partials.home.components.tools-list')
                        </div>
                    </div>

                    <div class="tools-bar"></div>
                </div>
            </div>

            <div class="input-main-btn input-send">
                @php $tooltipId = str()->uuid() @endphp
                <button id="send-btn"
                        class="tooltip-parent"

                        @if($activeModule === 'chat')
                            onClick="onSendClickConv(this)"
                        @elseif($activeModule === 'groupchat')
                            onClick="onSendClickRoom(this)"
                        @endif
                        aria-labelledby="{{ $tooltipId }}">

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
                        <div class="label tooltip tt-abs-up" aria-hidden="true" id="{{ $tooltipId }}">
                        {{ __("Send") }}
                        </div>
                </button>
            </div>



        </div>

    </div>

    @include('partials.home.dragDropOverlay')

</div>
