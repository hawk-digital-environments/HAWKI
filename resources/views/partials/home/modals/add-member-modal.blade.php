<div class="modal" id="add-member-modal">
    <div class="modal-panel">
        <div class="modal-content-wrapper">

            <button class="closeButton btn-sm" onclick="closeModal(this)" aria-label="{{ $translation["Close"] }}">
                <x-icon name="x"/>
            </button>

            <div class="modal-content">
                <h2>{{ $translation["MemberInvite"] }}</h2>

                @include('partials.home.components.add-members-section')


                <div class="row modal-buttons-bar top-gap-2">
                    <p class="error-msg red-text"></p>
                    <button class="btn-lg-stroke" onclick="sendInvitation(this)">{{ $translation["Send"] }}</button>
                </div>

            </div>
        </div>
    </div>
</div>
