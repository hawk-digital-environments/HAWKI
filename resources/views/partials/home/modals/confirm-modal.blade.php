
<div class="modal" id="confirm-modal">
    <div class="modal-panel">
        <div class="modal-content-wrapper">

            <div class="modal-content">

                <h3 id="modal-header"></h3>
                <div id="modal-message"></div>


                <div class="row modal-buttons-bar top-gap-3" id="confirm-btn-bar">
                    <button class="btn-lg-stroke" id="modal-cancel-button">{{ $translation["Cancel"] }}</button>
                    <button class="btn-lg-fill red-text" id="modal-confirm-button">{{ $translation["Confirm"] }}</button>
                </div>
                <div class="row  top-gap-3" id="info-btn-bar">
                    <button class="btn-lg-stroke red-text" id="modal-close-button" style="justify-self: end">{{ $translation["Close"] }}</button>
                </div>

            </div>
        </div>
    </div>
</div>


<script>

    const ModalType = {
        CONFIRM: { header: 'Confirm' },
        WARNING: { header: 'Warning!' },
        ERROR: { header: 'Error!!!' },
        INFO: { header: 'Info:' },
    };


    // type = Info, Warning, Error, Confirm
    function openModal(modalType, message, header = null) {

        if (!Object.values(ModalType).includes(modalType)) {
            throw new Error('Invalid type passed to openModal.');
        }

        // Set the message in the h3 tag
        if(!header){
            document.getElementById("modal-header").innerHTML = `${modalType.header}`;
        }
        else{
            document.getElementById("modal-header").innerHTML = header;
        }
        document.getElementById("modal-message").innerHTML = message;

        // Show the modal
        const modal = document.getElementById("confirm-modal");
        modal.style.display = "flex";

        return new Promise((resolve) => {

            if(modalType === ModalType.CONFIRM || modalType === ModalType.WARNING){

                modal.querySelector("#confirm-btn-bar").style.display = 'flex';
                modal.querySelector("#info-btn-bar").style.display = 'none';

                // Add click event listeners for Confirm and Cancel buttons
                const confirmButton = modal.querySelector("#modal-confirm-button");
                const cancelButton = modal.querySelector("#modal-cancel-button");

                // Confirm action
                confirmButton.onclick = () => {
                    resolve(true); // Resolve promise with `true` if confirmed
                    closeConfirmModal();
                };

                // Cancel action
                cancelButton.onclick = () => {
                    resolve(false); // Resolve promise with `false` if canceled
                    closeConfirmModal();
                };
            }
            else{

                modal.querySelector("#confirm-btn-bar").style.display = 'none';
                modal.querySelector("#info-btn-bar").style.display = 'grid';

                const closeBtn = modal.querySelector('#modal-close-button');

                closeBtn.onclick = () => {
                    resolve(null);
                    closeConfirmModal();
                };

            }

        });
    }

    function closeConfirmModal() {
        document.getElementById("confirm-modal").style.display = "none";
    }

</script>
