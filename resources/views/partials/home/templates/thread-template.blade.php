<template id="thread-template">
    <div class="thread" id="0">
        @php $tooltipId = str()->uuid() @endphp
        <button
            class="thread-following-editor fast-access-btn tooltip-parent"
            onclick="onEditThreadButtonEvent(this)"
            aria-labelledby="{{ $tooltipId }}">
            <x-icon name="edit"/>
            <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">NT: Write in Thread</div>
        </button>
        <div data-id="thread-active-indicator" class="thread-active-indicator">
            <span class="thread-active-dot" aria-hidden="true"></span>
            NT: You are Writing in this thread...
        </div>
        @php $tooltipId = str()->uuid() @endphp
        <button class="btn-xs fast-access-btn tooltip-parent thread-close-btn" onclick="onThreadButtonEvent(this)" aria-labelledby="{{ $tooltipId }}">
            <x-icon name="chevron-up" aria-hidden="true"/>
            <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("ThreadCloseToolTip") }}</div>
        </button>
    </div>
</template>
