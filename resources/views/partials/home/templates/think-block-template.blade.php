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
