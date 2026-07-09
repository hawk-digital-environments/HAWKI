<div class="modal"  id="access-token-modal">
	<div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="modal-content">
                <button class="closeButton btn-sm" onclick="closeModal(this)" aria-label="{{ __("Close") }}">
                    <x-icon name="x" aria-hidden="true"/>
                </button>
                <h3>{{ __("ExtAccToken") }}</h3>

                <table id="access-token-chart" class="top-gap-1">

                </table>

                <input type="text" id="newAccessTokenName" class="top-gap-2" maxlength="16">
                <button id="createButton" class="btn-lg-fill align-end top-gap-1" onclick="addNewToken()" >{{ __("CreateToken") }}</button>

            </div>
        </div>
	</div>
</div>
