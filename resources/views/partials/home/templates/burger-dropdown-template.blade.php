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
