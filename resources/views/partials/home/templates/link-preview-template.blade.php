<template id="link-preview-template">
    <div class="link-preview-panel">
        <div class="preview-loading">
            <div class="spinner"></div>
            <span>Loading preview...</span>
        </div>
        <div class="preview-content" style="display: none;">
            <div class="preview-image-container">
                <img src="" alt="" class="preview-image">
            </div>
            <div class="preview-details">
                <h4 class="preview-title"></h4>
                <p class="preview-description"></p>
                <div class="preview-footer">
                    <img src="" alt="" class="preview-favicon">
                    <span class="preview-domain"></span>
                </div>
            </div>
        </div>
        <div class="preview-error" style="display: none;">
            <span>Preview unavailable</span>
        </div>
    </div>
</template>
