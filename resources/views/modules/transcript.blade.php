@extends('layouts.home')
@section('content')


<div class="main-panel-grid">
	<div class="dy-sidebar expanded" id="transcript-sidebar">
		<div class="dy-sidebar-wrapper">
			<!-- <div class="welcome-panel">
				<h1>{{ Auth::user()->name }}</h1>
			</div> -->
			<div class="header">
				<button class="btn-md-stroke" onclick="startNewChat()">
					<div class="icon">
					<x-icon name="plus"/>
					</div>
					<div class="label"><strong>Neue Transcription</strong></div>
				</button>
				<h3 class="title">{{ $translation["History"] }}</h3>

			</div>
			<div class="dy-sidebar-content-panel">
				<div class="dy-sidebar-scroll-panel">
					<div class="selection-list" id="chats-list">
						<div id="file-transcription-options" style="display: none; padding: 10px;">
							<div class="transcript-sidebar-field">
								<label for="file-path">📄 Dateipfad:</label>
								<input type="text" id="file-path" placeholder="Pfad zur Datei eingeben">
							</div>
						
							<div class="transcript-sidebar-field">
								<label for="start-time">⏱️ Startzeit:</label>
								<input type="text" id="start-time" placeholder="z. B. 00:00:00">
							</div>
						
							<div class="transcript-sidebar-field">
								<label for="end-time">⏹️ Stoppzeit:</label>
								<input type="text" id="end-time" placeholder="z. B. 00:02:00">
							</div>
						
							<div class="transcript-sidebar-field">
								<label for="language-select">🌐 Sprache:</label>
								<select id="language-select">
									<option value="de">Deutsch</option>
									<option value="en">Englisch</option>
									<option value="fr">Französisch</option>
								</select>
							</div>
						
							<div class="transcript-sidebar-field">
								<label for="api-select">🔌 API auswählen:</label>
								<select id="api-select">
									<option value="whisper">OpenAI Whisper</option>
									<option value="google">Google Speech-to-Text</option>
									<option value="custom">Eigene API</option>
								</select>
								<div class="transcript-sidebar-section" id="speaker-recognition-wrapper" style="display: none;">
									<p class="transcript-info">Sprecher*innen erkennen</p>
									<select id="speaker-count" class="sidebar-input">
										<option value="auto">auto</option>
										<option value="1">+1</option>
										<option value="2">+2</option>
										<option value="3">+3</option>
										<option value="4">+4</option>
										<option value="5">+5</option>
									</select>
								</div>
							</div>
							<!-- START-BUTTON: Sichtbar nur bei Dateiupload -->
							<div id="start-upload-wrapper" class="transcript-sidebar-section" style="display: none;">
    							<button id="start-upload-btn">Starten</button>
							</div>
						</div>
						
					</div>
				</div>
			</div>
		
			<div class="dy-sidebar-expand-btn" onclick="togglePanelClass('chat-sidebar', 'expanded')">
				<x-icon name="chevron-right"/>
			</div>

		</div>
	</div>



	<div class="dy-main-panel">

		<div class="dy-main-content" id="chat">

			<div class="chat-info">
				
			</div>


			<div class="chatlog">
				<!-- Auswahl Transkript -->
				<div id="back-button-wrapper" style="display: none;">
					<button class="back-icon-button" onclick="showTranscriptChoice()">←</button>
				</div>
				<div class="transcript-choice" id="transcript-choice">
					<h2>Wählen Sie eine Transkriptionsmethode</h2>
					<div class="choice-buttons">
						<button onclick="showTranscriptMode('file')" class="transcript-button">
							📁 Transkription aus Datei
						</button>
						<button onclick="showTranscriptMode('live')" class="transcript-button">
							🎙️ Live-Transkription starten
						</button>
					</div>
				</div>
			
				<!-- UI Datei Upload -->
				<div id="transcript-file-ui" style="display: none;">
					<div class="transcript-section">
						<button class="back-icon-button" onclick="showTranscriptChoice()" title="Zurück zur Auswahl">←</button>
				
						<p class="transcript-info">Bitte ziehen Sie eine Datei in das Feld oder klicken Sie darauf.</p>
						<div class="drop-zone" id="drop-zone">
							Drag-und-Drop
						</div>
						<hr class="section-divider">
					</div>
				</div>
			
				<!-- UI Live Aufnahme -->
				<div id="transcript-live-ui" style="display: none;">
    <div class="transcript-section live-transcript-ui">
        <img src="https://cdn-icons-png.flaticon.com/512/727/727245.png" alt="Mikrofon" class="microphone-image">

        <div class="recording-controls">
            <button class="control-button record" title="Aufnehmen"></button>
            <button class="control-button pause" title="Pause"></button>
            <button class="control-button stop" title="Stop"></button>
        </div>
    </div>
</div>
			</div>
			<p class="warning">{{ $translation["MistakeWarning"] }}</p>

		</div>
	</div>
</div>


<script>

document.addEventListener('DOMContentLoaded', function () {
	const dropZone = document.querySelector('.drop-zone');

	dropZone.addEventListener('dragenter', (e) => {
		e.preventDefault();
		dropZone.classList.add('hover');
	});

	dropZone.addEventListener('dragleave', (e) => {
		e.preventDefault();
		dropZone.classList.remove('hover');
	});

	dropZone.addEventListener('dragover', (e) => {
		e.preventDefault();
		dropZone.classList.add('hover');
	});


	dropZone.addEventListener('drop', (e) => {
		e.preventDefault();
		dropZone.classList.remove('hover');

		const files = e.dataTransfer.files;
		if (files.length > 0) {
			console.log("Datei wurde fallen gelassen:", files[0]);
			// Hier kannst du deinen Upload oder Verarbeitung starten
		}
	});
});



function showTranscriptMode(mode) {
	document.getElementById('transcript-choice').style.display = 'none';
	document.getElementById('transcript-file-ui').style.display = 'none';
	document.getElementById('transcript-live-ui').style.display = 'none';
	document.getElementById('file-transcription-options').style.display = 'none';

	// Pfeil anzeigen
	document.getElementById('back-button-wrapper').style.display = 'block';

	document.getElementById('start-upload-wrapper').style.display = (mode === 'file') ? 'block' : 'none';

	document.getElementById('speaker-recognition-wrapper').style.display = (mode === 'file') ? 'block' : 'none';

	if (mode === 'file') {
		document.getElementById('transcript-file-ui').style.display = 'block';
		document.getElementById('file-transcription-options').style.display = 'block';
	} else if (mode === 'live') {
		document.getElementById('transcript-live-ui').style.display = 'block';
	}
}
function showTranscriptChoice() {
	document.getElementById('transcript-choice').style.display = 'block';
	document.getElementById('transcript-file-ui').style.display = 'none';
	document.getElementById('transcript-live-ui').style.display = 'none';
	document.getElementById('file-transcription-options').style.display = 'none';

	// Pfeil ausblenden
	document.getElementById('back-button-wrapper').style.display = 'none';

	document.getElementById('start-upload-wrapper').style.display = 'none';

	document.getElementById('speaker-recognition-wrapper').style.display = 'none';

}

/*window.addEventListener('DOMContentLoaded', async function (){

	initializeAiChatModule(@json($userData['convs']))

	const slug = @json($slug);

	if (slug){
		await loadConv(null, slug);
	}
	else{
        switchDyMainContent('chat');
	}
});*/


</script>


@endsection