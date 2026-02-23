<template id="thread-template">
	<div class="thread" id="0">
		@include('partials.home.input-field', ['lite' => true])
		<button class="btn-xs close-thread-btn fast-access-btn tooltip-parent" onclick="onThreadButtonEvent(this)" aria-describedby="closethread-tooltip">
			<x-icon name="chevron-up" aria-hidden="true"/>
			<div class="tooltip" aria-hidden="true" id="closethread-tooltip">{{ $translation["ThreadCloseToolTip"] }}</div>
		</button>
	</div>
</template>
