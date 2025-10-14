<template id="thread-template">
	<div class="thread" id="0">
		@include('partials.home.input-field', ['lite' => true])
		<button class="btn-xs close-thread-btn" onclick="onThreadButtonEvent(this)">
			<x-icon name="chevron-up"/>
		</button>
	</div>
</template>
