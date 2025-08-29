<template id="copy-btn-template">
	<button id="copy-btn" class="btn-xs reaction-button copy-btn" onmousedown="reactionMouseDown(this);" onmouseup="reactionMouseUp(this)">
		<x-icon name="copy"/>
		<div class="reaction">{{ $translation["Copied"] }}</div>
	</button>
</template>
