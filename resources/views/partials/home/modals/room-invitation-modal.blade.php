<div class="modal" id="room-invitation-modal">
    <div class="modal-panel">
        <div class="modal-content-wrapper">

            <div class="closeButton" onclick="closeModal(this)">
                <x-icon name="x"/>
            </div>

            <div class="modal-content">
                <h2 id="modal-title">{{ $translation['RoomInvitation'] ?? 'Room Invitation' }}</h2>

                <div class="modal-body">
                    <p id="invitation-room-name" style="font-size: 1.2em; font-weight: 500; margin-bottom: 1em;"></p>
                    <p id="invitation-message">{{ $translation['DoYouWantToJoinThisRoom'] ?? 'Do you want to join this room?' }}</p>
                    <p id="invitation-error" class="error-msg red-text" style="display: none; margin-top: 1em;"></p>
                </div>

                <div class="row modal-buttons-bar top-gap-2" id="invitation-actions">
                    <button class="btn-lg-stroke" onclick="closeModal(this)">{{ $translation['Cancel'] ?? 'Cancel' }}</button>
                    <button class="btn-lg-fill" id="accept-invitation-btn">{{ $translation['JoinRoom'] ?? 'Join Room' }}</button>
                </div>
                
                <div class="row modal-buttons-bar top-gap-2" id="invitation-error-actions" style="display: none;">
                    <button class="btn-lg-stroke" onclick="closeModal(this)">{{ $translation['Cancel'] ?? 'Cancel' }}</button>
                    <button class="btn-lg-fill-red" id="delete-invitation-btn">{{ $translation['DeleteInvitation'] ?? 'Delete Invitation' }}</button>
                </div>

            </div>
        </div>
    </div>
</div>
