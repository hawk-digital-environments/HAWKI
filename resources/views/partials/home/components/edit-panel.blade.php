@if($rightOut ?? false)
<div class="edit-panel stick-right-out admin-only" data-original-display="flex">
@else
<div class="edit-panel editor-only" data-originalDisplay="flex">
@endif
    @php $tooltipId = str()->uuid() @endphp
    <button class="btn-xs fast-access-btn tooltip-parent
    @if(($placement ?? false) == 'left') fast-access-btn-left @endif
    "
    id="edit-btn" onclick="editTextPanel(this)" aria-labelledby="{{ $tooltipId }}">
        <x-icon name="new" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ $tooltip ?? $translation["EditToolTip"] }}</div>
    </button>
    @php $tooltipId = str()->uuid() @endphp
    <button class="btn-xs fast-access-btn tooltip-parent"
    id="edit-confirm"
    @if($callbackFunction ?? false)
    onclick="confirmTextPanelEdit(this);{{ $callbackFunction }}()"
    @endif
    aria-labelledby="{{ $tooltipId }}">
        <x-icon name="check" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ $translation["Save"] }}</div>
    </button>
    @php $tooltipId = str()->uuid() @endphp
    <button class="btn-xs fast-access-btn tooltip-parent" id="edit-abort" onclick="abortTextPanelEdit(this)" aria-labelledby="{{ $tooltipId }}">
        <x-icon name="x" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ $translation["Abort"] }}</div>
    </button>
</div>