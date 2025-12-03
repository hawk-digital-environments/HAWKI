<div class="burger-dropdown" id="quick-actions">
	<div class="burger-expandable">

		@if($activeModule === 'groupchat')
			<button class="burger-item" id="burger-info-btn" onclick="handleBurgerAction('info')">{{ $translation["Info"] }}</button>
			<button class="burger-item" id="burger-mark-read-btn" onclick="handleBurgerAction('markAllRead')" disabled>{{ $translation["MarkAllRead"] }}</button>
			<button class="burger-item red-text" id="burger-leave-btn" onclick="handleBurgerAction('leave')">{{ $translation["LeaveRoom"] }}</button>
			<button class="burger-item red-text" id="burger-decline-btn" onclick="handleBurgerAction('decline')" style="display: none;">{{ $translation["DeclineInvitation"] }}</button>
		@endif

		{{-- <button class="burger-item">Teilen</button> --}}
		{{-- <button class="burger-item">Export</button> --}}

	@if($activeModule === 'chat')
		<button class="burger-item" onclick="editChatTitle()">{{ $translation["RenameChat"] }}</button>
		<button class="burger-item red-text" onclick="requestDeleteConv()">{{ $translation["DeleteChat"] }}</button>
	@endif
	</div>
</div>

<script>
async function handleBurgerAction(action) {
	const burgerMenu = document.getElementById('quick-actions');
	const roomSlug = burgerMenu ? burgerMenu.getAttribute('data-room-slug') : null;
	
	// Close burger menu first
	closeBurgerMenus();
	
	// If no slug specified, use current activeRoom
	if (!roomSlug) {
		executeRoomAction(action, activeRoom);
		return;
	}
	
	// Find the room object
	const room = rooms.find(r => r.slug === roomSlug);
	
	if (!room) {
		console.error('Room not found:', roomSlug);
		return;
	}
	
	// If it's already the active room, execute directly
	if (activeRoom && activeRoom.slug === roomSlug) {
		executeRoomAction(action, room);
		return;
	}
	
	// For decline action on new invitations, don't load the room
	if (action === 'decline' && room.isNewRoom) {
		executeRoomAction(action, room);
		return;
	}
	
	// For info action, load room and directly open control panel (skip chat view)
	if (action === 'info') {
		await loadRoom(null, roomSlug, true); // true = open control panel directly
		executeRoomAction(action, room);
		return;
	}
	
	// Otherwise, load the room first, then execute the action
	await loadRoom(null, roomSlug);
	
	// Wait a bit for the room to be fully loaded
	setTimeout(() => {
		executeRoomAction(action, room);
	}, 200);
}

function executeRoomAction(action, room) {
	switch(action) {
		case 'info':
			openRoomCP();
			break;
		case 'markAllRead':
			markAllAsRead();
			break;
		case 'leave':
			leaveRoom();
			break;
		case 'decline':
			if (typeof deleteRoomInvitation === 'function') {
				deleteRoomInvitation(room);
			}
			break;
	}
}
</script>
