<template id="attachment-thumbnail-template">
    <div class="attachment">
		<div class="content">
            <div class="attachment-icon">
                {{-- <h4 class="file-type"></h4> --}}
                <div class="status-indicator">
                    <div class="status" id="upload-stat">
                        <div id="loading-icon" class="loading">
                            <div class="loading">
                                <x-icon name="loading"/>
                            </div>
                        </div>
                    </div>
                    <div class="status" id="complete-stat">
                        <x-icon name="check"/>
                    </div>
                    <div class="status" id="error-stat">
                        <x-icon name="alert-circle"/>
                    </div>

                </div>
                <img src="" alt="">
            </div>
            <div class="name-tag-cont">
                <p class="name-tag"></p>
            </div>
        </div>
        <div class="controls">
            <button class="btn-sm remove-btn" onclick="removeAtchFromInputList(this)">
                <x-icon name="trash"/>
            </button>
        </div>
        <button class="burger-btn btn-xs">
            <x-icon name="more-horizontal"/>
        </button>
	</div>
</template>
