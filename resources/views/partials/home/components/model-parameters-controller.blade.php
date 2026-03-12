<div id="model-parameters-control-panel">
    <div class="params-wrapper">

        <div class="param-section">
            <div class="title-panel">
                <div class="title">
                    <label for="temp-input">{{$translation['Temperature']}}</label>
                    <button class="btn-xs hint hint-btn" data-hint-id="temp-hint">
                        <x-icon name="warning"/>
                    </button>
                </div>
                <div class="hint-box" id="temp-hint">
                    {{ $translation['TT_Temp'] }}
                </div>
            </div>
            <div class="param-input">
                <input id="temperature-input" data-param="temperature" type="range" step="0.1" name="temp-input" min="0.0" max="1.0" />
                <p class="input-indicator">0.5</p>
            </div>
        </div>


        <div class="param-section">
            <div class="title-panel">
                <div class="title">
                    <label for="top-p-input">Top_P</label>
                    <button class="btn-xs hint hint-btn" data-hint-id="top-p-hint">
                        <x-icon name="warning"/>
                    </button>
                </div>
                <div class="hint-box" id="top-p-hint">
                    {{ $translation['TT_Top_P'] }}
                </div>
            </div>
            <div class="param-input" >
                <input id="top-p-input" data-param="top_p" type="range" step="0.1" name="top-p-input" min="0.0" max="1.0" />
                <p class="input-indicator">0.5</p>
            </div>
        </div>

        <div class="presets-section">
            <p>{{$translation['Presets']}}</p>
            <div class="presets">
                <button class="preset-btn" onclick="setModelParamPreset(0.2,0.8)">
                    <span class="title">{{$translation['Precise']}}</span>
                    <span class="value">0.2/0.8</span>
                </button>
                <button class="preset-btn" onclick="setModelParamPreset(0.7,0.9)">
                    <span class="title">{{$translation['Balanced']}}</span>
                    <span class="value">0.7/0.9</span>
                </button>
                <button class="preset-btn" onclick="setModelParamPreset(1.0,1.0)">
                    <span class="title">{{$translation['Creative']}}</span>
                    <span class="value">1.0/1.0</span>
                </button>
            </div>
        </div>

        <div class="reset-section">
            <button class="btn-md-stroke" onclick="setModelParamDefault()">
                <div class="icon">
                    <x-icon name="rotation"/>
                </div>
                <div class="label">{{$translation['ResetToDefault']}}</div>
            </button>
            <p class="default-values">
                <span>{{$translation['Default']}}: </span>
                <span class="default-temp"></span>
                <span>/</span>
                <span class="default-top-p"></span>
            </p>
        </div>

    </div>
</div>
