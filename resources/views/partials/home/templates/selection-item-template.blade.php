<template id="selection-item-template">
	@if($activeModule === 'chat')
		<div class="selection-item" slug="" onclick="loadConv(this, null)">
	@elseif($activeModule === 'groupchat')
		<div class="selection-item" slug="" onclick="loadRoom(this, null)">
			<div class="room-icon-wrapper">
				<div class="notification-badge" id="unread-msg-flag"></div>
				<img class="room-icon" id="room-icon" alt="">
				<div class="room-initials" id="room-initials"></div>
			</div>
	@endif
			<div class="label singleLineTextarea"></div>
			<div class="btn-xs options burger-btn" onclick="handleBurgerMenuClick(event, this)">
				<x-icon name="more-horizontal"/>
			</div>
		</div>
</template>

<script>
function handleBurgerMenuClick(event, element) {
	event.stopPropagation();
	
	// Get the slug from the parent selection-item
	const selectionItem = element.closest('.selection-item');
	const slug = selectionItem ? selectionItem.getAttribute('slug') : null;

	// Store the slug in the burger menu for later use
	const burgerMenu = document.getElementById('quick-actions');
	if (burgerMenu && slug) {
		burgerMenu.setAttribute('data-room-slug', slug);
	}
	
	// Check if we're in groupchat or chat module
	const isGroupchat = typeof rooms !== 'undefined';
	
	if (isGroupchat) {
		// Groupchat logic
		const room = rooms.find(r => r.slug === slug);
		const isNewInvitation = room && room.isNewRoom;
		const isRemoved = room && room.isRemoved;
		const hasUnreadMessages = room && room.hasUnreadMessages;
		
		// If room is removed, don't open burger menu - clicking room opens modal instead
		if (isRemoved) {
			return;
		}
		
		if (burgerMenu && slug) {
			// Show/hide appropriate buttons based on room status
			const leaveBtn = burgerMenu.querySelector('#burger-leave-btn');
			const declineBtn = burgerMenu.querySelector('#burger-decline-btn');
			const infoBtn = burgerMenu.querySelector('#burger-info-btn');
			const markReadBtn = burgerMenu.querySelector('#burger-mark-read-btn');
			
			if (isNewInvitation) {
				// New invitation: show decline, hide leave, info and mark-as-read
				if (leaveBtn) leaveBtn.style.display = 'none';
				if (declineBtn) declineBtn.style.display = 'block';
				if (infoBtn) infoBtn.style.display = 'none';
				if (markReadBtn) {
					markReadBtn.style.display = 'none';
					markReadBtn.disabled = true;
				}
			} else {
				// Normal room: show leave, hide decline
				if (leaveBtn) leaveBtn.style.display = 'block';
				if (declineBtn) declineBtn.style.display = 'none';
				if (infoBtn) infoBtn.style.display = 'block';
				
				// Mark as read: only show if there are unread messages
				if (markReadBtn) {
					if (hasUnreadMessages) {
						markReadBtn.style.display = 'block';
						markReadBtn.disabled = false;
					} else {
						markReadBtn.style.display = 'none';
						markReadBtn.disabled = true;
					}
				}
			}
		}
	}
	
	openBurgerMenu('quick-actions', element, true);
}
</script>
