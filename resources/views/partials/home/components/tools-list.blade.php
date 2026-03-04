<div class="tool-selection-panel burger-dropdown" id="tool-selection-panel" >
@foreach($toolKit as $tool)
    @if(!in_array($tool, ['stream', 'file_upload', 'vision', 'tool_calling']))
        @php $label = $toolKitLabels[$tool] ?? ucwords(str_replace('_', ' ', $tool)); @endphp
        <button class="burger-item tool-selector"
                data-reference="{{$tool}}"
                data-label="{{ $label }}"
                onclick="onToolBtn(this)"
        >
            <span class="icon ">
                @php
                    $icon = 'tool_' . $tool;
                    $path = resource_path("icons/{$icon}.svg");
                @endphp
                <x-icon :name="file_exists($path) ? $icon : 'tool'" />
            </span>
            <span class="label">{{ $label }}</span>
        </button>
    @endif
@endforeach
</div>
