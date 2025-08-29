<div class="message-controls">
    <div class="controls">
        <div class="buttons">
            <button id="copy-btn" class="btn-xs reaction-button" onclick="CopyMessageToClipboard(this);" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
                <x-icon name="copy"/>
                <div class="reaction">{{ $translation["Copied"] }}</div>
            </button>
            <button id="edit-btn" class="btn-xs reaction-button" onclick="editMessage(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
                <x-icon name="edit"/>
            </button>
            <button id="speak-btn" class="btn-xs reaction-button" onclick="messageReadAloud(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
                <x-icon name="volume"/>
            </button>
            <button id="regenerate-btn" class="btn-xs reaction-button editor-only" onclick="onRegenerateBtn(this)" onmousedown="reactionMouseDown(this)" onmouseup="reactionMouseUp(this);">
                <x-icon name="rotation"/>
            </button>
            <button id="thread-btn" class="btn-xs reaction-button" onclick="onThreadButtonEvent(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
                <x-icon name="message-circle"/>
                <p class="label" id="comment-count"></p>
                <div class="dot-lg" id="unread-thread-icon"></div>
            </button>
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
            <div id="sent-status-icon" >
                <x-icon name="check"/>
            </div>
        </div>
    </div>

    <div class="edit-bar">
        <div class="edit-bar-section">
            <button id="prompt-imprv" class="btn-xs" onclick="requestPromptImprovement(this, 'message')">
                <x-icon name="vector"/>
            </button>
            <button id="delete-btn" class="btn-xs" onclick="deleteMessage(this);">
                <x-icon name="trash"/>
            </button>
        </div>
        <div class="edit-bar-section">
            <button id="confirm-btn" class="btn-xs" onclick="confirmEditMessage(this);">
                <x-icon name="check"/>
            </button>
            <button id="cancel-btn" class="btn-xs" onclick="abortEditMessage(this);">
                <x-icon name="x"/>
            </button>
        </div>
    </div>
</div>
