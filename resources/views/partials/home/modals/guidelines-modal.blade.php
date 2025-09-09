<div class="modal"  id="data-protection"> 
	<div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="modal-content">
                <h1>{!! $translation["Guidelines"]; !!}</h1>
                {!! $localizedTexts["guidelines_content"]; !!}
                <br>
                
                <div class="modal-buttons-bar">
                    <button class="btn-lg-stroke align-end" onclick="logout()" >{{ $translation["Cancel"] }}</button>
                    {{-- <button class="btn-lg-fill align-end" onclick="modalClick(this)" >{{ $translation["Confirm"]; }}</button>  --}}
                                {{-- Check config for passkey method --}}
                    @if(config('auth.passkey_method') === 'auto')
                        <button class="btn-lg-fill" onclick="switchSlide(0)">{{ $translation["Confirm"] }}</button>
                    @else
                        <button class="btn-lg-fill align-end" onclick="modalClick(this)" >{{ $translation["Confirm"]; }}</button>
                    @endif
                </div>

                <br>
                <br>
            </div>
        </div>
	</div>
</div>
