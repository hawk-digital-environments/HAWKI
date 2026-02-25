@if($rightOut ?? false)
<div class="edit-panel stick-right-out admin-only" data-original-display="flex">
@else
<div class="edit-panel editor-only" data-originalDisplay="flex">
@endif
    <button class="btn-xs fast-access-btn tooltip-parent
    @if(($placement ?? false) == 'left') fast-access-btn-left @endif
    "
    id="edit-btn" onclick="editTextPanel(this)" aria-describedby="edittext-tooltip">
        <x-icon name="new" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="edittext-tooltip">{{ $tooltip ?? $translation["EditToolTip"] }}</div>
    </button>
    <button class="btn-xs fast-access-btn tooltip-parent"
    id="edit-confirm"
    @if($callbackFunction ?? false)
    onclick="confirmTextPanelEdit(this);{{ $callbackFunction }}()"
    @endif
    aria-describedby="confirm-tooltip">
        <x-icon name="check" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="confirm-tooltip">{{ $translation["Save"] }}</div>
    </button>
    <button class="btn-xs fast-access-btn tooltip-parent" id="edit-abort" onclick="abortTextPanelEdit(this)" aria-describedby="abort-tooltip">
        <x-icon name="x" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="abort-tooltip">{{ $translation["Abort"] }}</div>
    </button>
</div>