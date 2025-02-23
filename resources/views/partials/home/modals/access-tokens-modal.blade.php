<div class="modal"  id="access-token-modal"> 
	<div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="modal-content">
                <div class="closeButton" onclick="closeModal(this)">
                    <x-icon name="x"/>  
                </div>
                <h3>{{ $translation["ExtAccToken"] }}</h3>
                
                <table id="access-token-chart" class="top-gap-1">
                    
                </table>

                <input type="text" id="newAccessTokenName" class="top-gap-2" maxlength="16">
                <button id="createButton" class="btn-lg-fill align-end top-gap-1" onclick="addNewToken()" >{{ $translation["CreateToken"] }}</button>
                
            </div>
        </div>
	</div>
</div>