<div class="modal" id="room-removed-modal">
    <div class="modal-panel">
        <div class="modal-content-wrapper">

            <div class="closeButton" onclick="closeModal(this)">
                <x-icon name="x"/>
            </div>

            <div class="modal-content">
                <h2 id="modal-title">{{ $translation['RemovedFromRoom'] ?? 'Removed from Room' }}</h2>

                <div class="modal-body">
                    <p id="removed-room-name" style="font-size: 1.2em; font-weight: 500; margin-bottom: 1em;"></p>
                    <p id="removed-message">{{ $translation['YouHaveBeenRemovedFromThisRoom'] ?? 'You have been removed from this room by an administrator.' }}</p>
                </div>

                <div class="row modal-buttons-bar top-gap-2">
                    <button class="btn-lg-stroke" onclick="closeModal(this)">{{ $translation['Cancel'] ?? 'Cancel' }}</button>
                    <button class="btn-lg-fill-red" id="acknowledge-removal-btn">{{ $translation['DeleteRoom'] ?? 'Remove from List' }}</button>
                </div>

            </div>
        </div>
    </div>
</div>
