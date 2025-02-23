<div class="modal" id="add-member-modal">
    <div class="modal-panel">
        <div class="modal-content-wrapper">

            <div class="closeButton" onclick="closeModal(this)">
                <x-icon name="x"/>  
            </div>

            <div class="modal-content">
                <h2>{{ $translation["MemberInvite"] }}</h2>

                @include('partials.home.components.add-members-section')


                <div class="row modal-buttons-bar top-gap-2">
                    <div></div>
                    <button class="btn-lg-stroke" onclick="sendInvitation(this)">{{ $translation["Send"] }}</button>
                </div>

            </div>
        </div>
    </div>
</div>
