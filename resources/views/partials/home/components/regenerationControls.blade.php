<div class="regenerate-controls" id="regenerate-controls">
    <div class="reg-wrapper">
        <div class="main-menu" id="reg-menu">
            <button class="btn-xs reg-menu-item reg-submenu-btn" reference="models-list">
                <div class="icon">
                    <x-icon name="layers"/>
                </div>
                <div class="label">
                    {{$translation['Models']}}
                </div>
                <div class="indicator"></div>
                <div class="arrow">
                    <x-icon name="chevron-right"/>
                </div>
            </button>
            <button class="btn-xs reg-menu-item reg-submenu-btn" reference="tools-list">
                <div class="icon">
                    <x-icon name="tool"/>
                </div>
                <div class="label">
                    {{$translation['Tools']}}
                </div>
                <div class="indicator "></div>
                <div class="arrow">
                    <x-icon name="chevron-right"/>
                </div>
            </button>

            <button class="btn-xs reg-menu-item reg-submenu-btn" reference="params-panel" >
                <div class="icon">
                    <x-icon name="sliders"/>
                </div>
                <div class="label">
                    {{$translation['Parameters']}}
                </div>
                <div class="indicator "></div>
                <div class="arrow">
                    <x-icon name="chevron-right"/>
                </div>
            </button>
            <button class="btn-xs confirm">
{{--                <div class="icon">--}}
{{--                    <x-icon name="rotation"/>--}}
{{--                </div>--}}
                <div class="label">
                    {{$translation['RegenerateToolTip']}}
                </div>
            </button>
        </div>
        <div class="sub-menu" id="models-list">
            <div class="model-selection-panel">
                @foreach($models['models'] as $model)
                    <button class="model-selector burger-item"
                            data-model-id="{{ $model['id'] }}"
                            value="{{ json_encode($model)}}"
                            data-status="{{$model['status']}}"
                            @if($model['status'] === 'offline')
                                disabled
                        @endif>

                        @switch($model['status'])
                            @case('online')
                                <span class="dot grn-c"></span>
                                @break
                            @case('unknown')
                                <span class="dot org-c"></span>
                                @break
                            @case('offline')
                                <span class="dot red-c"></span>
                                @break
                            @default
                                <span class="dot red-c"></span>
                        @endswitch
                        <span>{{ $model['label'] }}</span>

                    </button>
                @endforeach
            </div>
        </div>
        <div class="sub-menu" id="tools-list">
            @foreach($toolKit as $tool)
                @if($tool != 'stream' && $tool != 'file_upload' && $tool != 'vision')
                    <button class="burger-item tool-selector"
                            data-reference="{{$tool}}"
                            data-label="{{$translation['Tool_' . $tool] }}"
                    >
            <span class="icon ">
                <x-icon name="tool_{{$tool}}"/>
            </span>
                        <span class="label">{{$translation['Tool_' . $tool] }}</span>
                    </button>
                @endif
            @endforeach
        </div>
        <div class="sub-menu" id="params-panel">
            <div class="params-wrapper">
                <div class="param-section">
                    <label for="temp-input">{{$translation['Temperature']}}</label>
                    <div class="param-input">
                        <input id="temperature-input" data-param="temperature" type="range" step=".1" name="temp-input" min="0" max="1" />
                        <p class="input-indicator">0.5</p>
                    </div>
                </div>

                <div class="param-section">
                    <label for="top-p-input">Top_P</label>
                    <div class="param-input" >
                        <input id="top-p-input" data-param="top_p" type="range" step=".1" name="top-p-input" min="0" max="1" />
                        <p class="input-indicator">0.5</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
