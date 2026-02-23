<div class="modal"  id="file-viewer-modal" style="display: none">
	<div class="modal-panel" style="height: 90vh">
            <button class="closeButton btn-sm" onclick="closeModal(this)" aria-label="{{ $translation["Close"] }}">
                <x-icon name="x" aria-hidden="true"/>
            </button>
            <div class="scroll-container" id="file-scroll-container">
                <div class="scroll-panel" id="file-preview-container"></div>
            </div>
	</div>
</div>

