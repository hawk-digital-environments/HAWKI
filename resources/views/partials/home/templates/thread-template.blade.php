<template id="thread-template">
	<div class="thread" id="0">
		@include('partials.home.input-field', ['lite' => true])
		@php $tooltipId = str()->uuid() @endphp
		<button class="btn-xs close-thread-btn fast-access-btn tooltip-parent" onclick="onThreadButtonEvent(this)" aria-labelledby="{{ $tooltipId }}">
			<x-icon name="chevron-up" aria-hidden="true"/>
			<div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ $translation["ThreadCloseToolTip"] }}</div>
		</button>
	</div>
</template>
