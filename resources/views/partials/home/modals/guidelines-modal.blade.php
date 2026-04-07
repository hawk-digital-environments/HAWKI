<div class="modal"  id="data-protection">
	<div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="modal-content">
                {{-- POLICY CONTENT DYNAMICALLY LOADS HERE --}}
                <div id="policy-content"></div>

                <div class="modal-buttons-bar">
                    <button id="declineBtn" class="btn-lg-stroke align-end">{{ __("Cancel") }}</button>
                    <button id="confirmBtn" class="btn-lg-fill align-end" onclick="modalClick(this)" >{{ __("Confirm") }}</button>
                </div>

                <br>
                <br>
            </div>
        </div>
	</div>
</div>
