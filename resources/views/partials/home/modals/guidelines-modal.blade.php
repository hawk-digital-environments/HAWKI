<div class="modal"  id="data-protection"> 
	<div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="modal-content">
                <h1>{!! $translation["Guidelines"]; !!}</h1>
                {!! $localizedTexts["guidelines_content"]; !!}
                <br>
                
                <div class="modal-buttons-bar">
                    <button class="btn-lg-stroke align-end" onclick="logout()" >{{ $translation["Cancel"] }}</button>
                    <button class="btn-lg-fill align-end" onclick="modalClick(this)" >{{ $translation["Confirm"]; }}</button>
                </div>

                <br>
                <br>
            </div>
        </div>
	</div>
</div>
