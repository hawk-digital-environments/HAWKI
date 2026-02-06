<div class="tool-selection-panel burger-dropdown" id="tool-selection-panel" >
@foreach($toolKit as $tool)
    @if($tool != 'stream' && $tool != 'file_upload' && $tool != 'vision')
        <button class="burger-item tool-selector"
                data-reference="{{$tool}}"
                data-label="{{$translation['Tool_' . $tool] }}"
                onclick="onToolBtn(this)"
        >
            <span class="icon ">
                <x-icon name="tool_{{$tool}}"/>
            </span>
            <span class="label">{{$translation['Tool_' . $tool] }}</span>
        </button>
    @endif
@endforeach
</div>
