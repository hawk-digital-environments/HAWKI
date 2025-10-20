<div class="message-controls">
    <div class="controls">
        <div class="buttons">
            <button id="copy-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent" onclick="CopyMessageToClipboard(this);" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
                <x-icon name="copy"/>
                <div class="reaction">{{ $translation["Copied"] }}</div>
                <div class="tooltip">{{ $translation["CopyToolTip"] }}</div>
            </button>
            <button id="edit-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent" onclick="editMessage(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
                <x-icon name="edit"/>
                <div class="tooltip">{{ $translation["EditToolTip"] }}</div>
            </button>
            <button id="speak-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent" onclick="messageReadAloud(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
                <x-icon name="volume"/>
                <div class="tooltip">{{ $translation["SpeakToolTip"] }}</div>
            </button>
            <button id="regenerate-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent editor-only" onclick="onRegenerateBtn(this)" onmousedown="reactionMouseDown(this)" onmouseup="reactionMouseUp(this);">
                <x-icon name="rotation"/>
                <div class="tooltip">{{ $translation["RegenerateToolTip"] }}</div>
            </button>
            <button id="thread-btn" class="btn-xs reaction-button fast-access-btn tooltip-parent" onclick="onThreadButtonEvent(this)" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
                <x-icon name="message-circle"/>
                <p class="label" id="comment-count"></p>
                <div class="dot-lg" id="unread-thread-icon"></div>
                <div class="tooltip">{{ $translation["ThreadToolTip"] }}</div>
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
            @if($activeModule === 'chat')
            <button id="delete-btn" class="btn-xs" onclick="deleteMessage(this);">
                <x-icon name="trash"/>
            </button>
            @endif
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
