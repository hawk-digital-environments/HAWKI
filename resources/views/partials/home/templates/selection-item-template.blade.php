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
