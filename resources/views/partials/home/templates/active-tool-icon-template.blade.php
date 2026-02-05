<template id="active-tool-icon-template">
    <div class="active-tool-icon" data-reference="${tool}">
        <button class="remove-btn btn-xs" onclick="deactivateTool(this, '${tool}')">
            <x-icon name="x"/>
        </button>
        <span class="label "></span>
    </div>
</template>
