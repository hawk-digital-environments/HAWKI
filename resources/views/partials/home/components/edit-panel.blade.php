@if($rightOut ?? false)
<div class="edit-panel stick-right-out admin-only" data-original-display="flex">
@else
<div class="edit-panel editor-only" data-originalDisplay="flex">
@endif
    <button class="btn-xs fast-access-btn tooltip-parent" id="edit-btn" onclick="editTextPanel(this)" aria-describedby="changename-tooltip">
        <x-icon name="new" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="changename-tooltip">{{ $tooltip ?? $translation["EditToolTip"] }}</div>
    </button>
    @if($callbackFunction ?? false)
    <button class="btn-xs fast-access-btn tooltip-parent
    @if(($alignTooltip ?? false) == 'left') fast-access-btn-left @endif
    "
    id="edit-confirm" onclick="confirmTextPanelEdit(this);{{ $callbackFunction }}()" aria-describedby="confirm-tooltip">
    @else
    <button class="btn-xs fast-access-btn tooltip-parent" id="edit-confirm" onclick="confirmTextPanelEdit(this)" aria-describedby="confirm-tooltip">
    @endif
        <x-icon name="check" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="confirm-tooltip">{{ $translation["Save"] }}</div>
    </button>
    <button class="btn-xs fast-access-btn tooltip-parent" id="edit-abort" onclick="abortTextPanelEdit(this)" aria-describedby="abort-tooltip">
        <x-icon name="x" aria-hidden="true"/>
        <div class="tooltip" aria-hidden="true" id="abort-tooltip">{{ $translation["Abort"] }}</div>
    </button>
</div>