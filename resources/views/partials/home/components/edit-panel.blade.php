@if($rightOut ?? false)
<div class="edit-panel stick-right-out admin-only" data-original-display="flex">
@else
<div class="edit-panel admin-only" data-original-display="flex">
@endif
    <button class="btn-xs" id="edit-btn" onclick="editTextPanel(this)">
        <x-icon name="new"/>
    </button>
    @if($callbackFunction ?? false)
    <button class="btn-xs" id="edit-confirm" onclick="confirmTextPanelEdit(this);  {{ $callbackFunction }}()">
    @else
    <button class="btn-xs" id="edit-confirm" onclick="confirmTextPanelEdit(this);">
    @endif
        <x-icon name="check"/>
    </button>
    <button class="btn-xs" id="edit-abort" onclick="abortTextPanelEdit(this)">
        <x-icon name="x"/>
    </button>
</div>