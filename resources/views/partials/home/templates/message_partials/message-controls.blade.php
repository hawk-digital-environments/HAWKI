<div class="message-controls">
    <div class="controls">
        <div class="buttons">
            @php $tooltipId = str()->uuid() @endphp
            <button id="copy-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent" onclick="CopyMessageToClipboard(this);" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)" aria-labelledby="{{ $tooltipId }}">
                <x-icon name="copy" aria-hidden="true"/>
                <div class="reaction" aria-hidden="true">{{ __("Copied") }}</div>
                <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("CopyToolTip") }}</div>
            </button>
            @php $tooltipId = str()->uuid() @endphp
            <button id="thread-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent" onclick="onThreadButtonEvent(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)" aria-labelledby="{{ $tooltipId }}">
                <x-icon name="message-circle" aria-hidden="true"/>
                <p class="label" aria-hidden="true" id="comment-count"></p>
                <div class="dot-lg" aria-hidden="true" id="unread-thread-icon"></div>
                <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("ThreadOpenToolTip") }}</div>
            </button>
            @php $tooltipId = str()->uuid() @endphp
            <button id="speak-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent" onclick="messageReadAloud(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)" aria-labelledby="{{ $tooltipId }}">
                <x-icon name="volume" aria-hidden="true"/>
                <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("SpeakToolTip") }}</div>
            </button>
            @php $tooltipId = str()->uuid() @endphp
            <button id="edit-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent editor-only" onclick="editMessage(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)" aria-labelledby="{{ $tooltipId }}">
                <x-icon name="edit" aria-hidden="true"/>
                <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("EditToolTip") }}</div>
            </button>
            @php $tooltipId = str()->uuid() @endphp
            <button id="regenerate-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent editor-only" onclick="onRegenerateBtn(this)" onmousedown="reactionMouseDown(this)" onmouseup="reactionMouseUp(this);" aria-labelledby="{{ $tooltipId }}">
                <x-icon name="rotation" aria-hidden="true"/>
                <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("RegenerateToolTip") }}</div>
            </button>
            @if($activeModule === 'chat')
                @php $tooltipId = str()->uuid() @endphp
                <button id="delete-btn" class="btn-xs reaction-button fast-access-btn  tooltip-parent" onclick="deleteMessage(this);" onmousedown="reactionMouseDown(this)" onmouseup="reactionMouseUp(this);" aria-labelledby="{{ $tooltipId }}">
                    <x-icon name="trash" aria-hidden="true"/>
                    <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">NT: Die Nachricht löschen</div>
                </button>
            @endif
        </div>

        <div class="message-status">
            @if($activeModule === 'chat')
                <div id="incomplete-msg-icon">
                    <x-icon name="alert-circle"/>
                </div>
            @elseif($activeModule === 'groupchat')
                <div id="unread-message-icon" class="dot-lg"></div>
            @endif
            <p id="msg-timestamp"></p>
            <div id="sent-status-icon">
                <x-icon name="check"/>
            </div>
        </div>
    </div>
</div>
