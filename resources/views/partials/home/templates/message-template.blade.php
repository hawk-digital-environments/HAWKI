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

			<div class="attachments"></div>

			<div class="message-content">
				<span class="assistant-mention"></span>
				<span class="message-text"></span>
			</div>
			@include('partials.home.templates.message_partials.message-controls')
		</div>
	</div>
</template>
