<div class="burger-dropdown" id="quick-actions">
	<div class="burger-expandable">

		@if($activeModule === 'groupchat')
			<button class="burger-item" onclick="closeBurgerMenus(); openRoomCP()">{{ $translation["Info"] }}</button>
			<button class="burger-item" id="mark-as-read-btn" onclick="markAllAsRead()" disabled>{{ $translation["MarkAllRead"] }}</button>
		@endif

			{{-- <button class="burger-item">Teilen</button> --}}
			{{-- <button class="burger-item">Export</button> --}}

		@if($activeModule === 'chat')
			<button class="burger-item red-text" onclick="requestDeleteConv()">{{ $translation["DeleteChat"] }}</button>
		@elseif($activeModule === 'groupchat')
			<button class="burger-item red-text" onclick="leaveRoom()">{{ $translation["LeaveRoom"] }}</button>
		@endif


	</div>
</div>

<template id="selection-item-template">
	@if($activeModule === 'chat')
		<div class="selection-item" slug="" onclick="loadConv(this, null)">
	@elseif($activeModule === 'groupchat')
		<div class="selection-item" slug="" onclick="loadRoom(this, null)">
			<div class="dot-lg" id="unread-msg-flag"></div>
	@endif
			<div class="label singleLineTextarea"></div>
			<div class="btn-xs options burger-btn" onclick="openBurgerMenu('quick-actions', this, true)">
				<x-icon name="more-horizontal"/>
			</div>
		</div>
</template>


<template id="thread-template">
	<div class="thread" id="0">
		@include('partials.home.input-field', ['lite' => true])
		<button class="btn-xs close-thread-btn" onclick="onThreadButtonEvent(this)">
			<x-icon name="chevron-up"/>
		</button>
	</div>
</template>

<template id="message-template">
	<div class="message" id="">
		<div class="message-wrapper">
			<div class="message-header">
				<div class="message-icon round-icon">
					<span class="user-inits"></span>
					<img class="icon-img"   alt="">
				</div>
				<div class="dot"></div>
				<div class="message-author"></div>
			</div>

			<div class="message-content">
				<div class="assistant-mention"></div>
				<div class="message-text"></div>
			</div>
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

				<div class="edit-controls">
					<button id="confirm-btn" class="btn-xs" onclick="confirmEditMessage(this);">
						<x-icon name="check"/>
					</button>
					<button id="cancel-btn" class="btn-xs" onclick="abortEditMessage(this);">
						<x-icon name="x"/>
					</button>
				</div>
			</div>
		</div>
	</div>
</template>


<template id="member-listBtn-template">
	<button id="member-btn" class="round-icon-btn member-btn" data-memberObj="" onclick="openMemberInfoPanel(this)">
		<img class="icon-img" id="member-icon"   alt="">
        <p id="member-init"></p>
	</button>
</template>

<template id="copy-btn-template">
	<button id="copy-btn" class="btn-xs reaction-button copy-btn" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
		<x-icon name="copy"/>
		<div class="reaction">{{ $translation["Copied"] }}</div>
	</button>
</template>


<template id="added-member-template">
	<div class="added-member">
		<p></p>
		<div class="remove-member" onclick="removeAddedMember(this)">
			<x-icon name="trash"/>
		</div>
	</div>
</template>

<template id="think-block-template">
	<div class="think" id="think">
		<div class="think-header">
			<p>{{ $translation["Think"] }}</p>
			<button class="btn-xs think-expand-btn" onclick="toggleRelativePanelClass('think', this,'expanded')">
				<x-icon name="chevron-down"/>
			</button>
		</div>
		<div class="content-container">
			<div class="content"></div>
		</div>
	</div>
</template>


<template id="token-list-row-temp">
	<tr id="token-item">
        <th class="index"></th>
        <th class="token-name"></th>
        <th>
            <button class="btn-xs delete-btn-svg" onclick="requestTokenRevoke(this)">
                <x-icon name="trash"/>
            </button>
        </th>
    </tr>
</template>


<template id="gen-stat-template">
	<div class="gen-stat-element">
		<p class="stat-txt"></p>
	</div>
</template>


<template id="inline-link-content-template">
    <div class="inline-link" href="" target="_blank" rel="noopener noreferrer">
        <div class="content">
            <p class="title"></p>
            <div class="adr-bar">
                <img src="" alt="" class="adr-icon">
                <div class="adr-url" href></div>
            </div>
            <img src="" alt="" class="link-cover">
        </div>
    </div>
</template>

