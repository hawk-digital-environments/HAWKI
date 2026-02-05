<div class="tool-selection-panel burger-dropdown" id="tool-selection-panel" >
@foreach($toolKit as $tool)
    @if($tool != 'stream' && $tool != 'file_upload' && $tool != 'vision')
        <button class="burger-item tool-selector"
                data-reference="{{$tool}}"
                data-label="{{ucwords(str_replace('_', ' ', $tool))}}"
                onclick="onToolBtn(this)"
        >
            <span class="icon ">
                <x-icon name="tool_{{$tool}}"/>
            </span>
{{--            TODO: ADD TRANSLATION--}}
            <span class="label">{{ucwords(str_replace('_', ' ', $tool))}}</span>
        </button>
    @endif
@endforeach
</div>
