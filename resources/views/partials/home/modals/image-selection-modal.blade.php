<div class="modal" id="image-selection-modal">
    <div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="closeButton" onclick="closeImageSelector()">
                <x-icon name="x"/>
            </div>
            <div class="modal-content">

                <h2 class="header">{{ $translation['ImgUpload'] }}</h2>
                <p>{!! $translation['ImgUploadDesc'] !!}</p>

                <div style="position: relative;">
                    <div class="image-container edit" id="image-container">
                        <div id="image-field-placeholder"></div>
                    </div>
                    <button class="btn-icon-only delete-btn image-delete-overlay" id="remove-image-btn" onclick="removeCurrentImage()" style="display: none;" title="{{ $translation['Delete'] ?? 'Löschen' }}">
                        <x-icon name="trash"/>
                    </button>
                </div>

                <div class="modal-buttons-bar top-gap-1">
                    <input type="file" id="image-file-input" style="display:none;" />
                    <button class="btn-lg-stroke" onclick="document.getElementById('image-file-input').click()">{{ $translation['Upload'] }}</button>
                    <button class="btn-lg-stroke" onclick="saveCroppedImage()">{{ $translation['Save'] }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
