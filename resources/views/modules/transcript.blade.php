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
								<input type="text" name="file_path" id="file-path" placeholder="Pfad zur Datei eingeben">
							</div>
						
							<div class="transcript-sidebar-field">
								<label for="start-time">⏱️ Startzeit:</label>
								<input type="text" name="start_time" id="start-time" placeholder="z. B. 00:00:00">
							</div>
						
							<div class="transcript-sidebar-field">
								<label for="end-time">⏹️ Stoppzeit:</label>
								<input type="text" name="end_time" id="end-time" placeholder="z. B. 00:02:00">
							</div>
						
							<div class="transcript-sidebar-field">
								<label for="language-select">🌐 Sprache:</label>
								<select id="language-select" name="language">
									<option value="de">Deutsch</option>
									<option value="en">Englisch</option>
									<option value="fr">Französisch</option>
								</select>
							</div>
						
							<div class="transcript-sidebar-field">
								<label for="api-select">🔌 API auswählen:</label>
								<select id="api-select" name="api">
									<option value="whisper">OpenAI Whisper</option>
									<option value="google">Google Speech-to-Text</option>
									<option value="custom">Eigene API</option>
								</select>
								<div class="transcript-sidebar-section" id="speaker-recognition-wrapper" style="display: none;">
									<p class="transcript-info">Sprecher*innen erkennen</p>
									<select id="speaker-count" name="speaker_count" class="sidebar-input">
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
						<form id="transcript-upload-form" enctype="multipart/form-data">
							<input type="file" name="audio_file" id="audio_file" style="display: none;">
						</form>
						<hr class="section-divider">
						<div id="selected-file-preview" class="transcript-file-preview" style="margin-top: 10px; display: none;">
							📎 <span id="selected-file-name">Keine Datei ausgewählt</span>
						</div>
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
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('audio_file');

	function formatSecondsToTime(seconds) {
	const hrs = String(Math.floor(seconds / 3600)).padStart(2, '0');
	const mins = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
	const secs = String(Math.floor(seconds % 60)).padStart(2, '0');
	return `${hrs}:${mins}:${secs}`;
}

    // Klick öffnet Dateiauswahl
    dropZone.addEventListener('click', () => {
        console.log("Drop-Zone wurde geklickt");
        fileInput.click();
    });

    let selectedAudioFile = null;

fileInput.addEventListener('change', function () {
	if (fileInput.files.length > 0) {
		selectedAudioFile = fileInput.files[0];
		

		const filePreview = document.getElementById('selected-file-preview');
		const fileNameSpan = document.getElementById('selected-file-name');

		document.getElementById('start-time').value = '00:00:00';

		fileNameSpan.textContent = selectedAudioFile.name;
		filePreview.style.display = 'flex';

		console.log("Datei vorgemerkt, aber noch nicht hochgeladen:", selectedAudioFile);
	}
	// Datei als temporäres Audio laden
const audioElement = document.createElement('audio');
audioElement.src = URL.createObjectURL(selectedAudioFile);
audioElement.addEventListener('loadedmetadata', () => {
	const duration = audioElement.duration; // Sekunden als float
	const formattedDuration = formatSecondsToTime(duration);
	document.getElementById('end-time').value = formattedDuration;
});
});

    // Drag-and-Drop Verhalten
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
		selectedAudioFile = files[0];

		const filePreview = document.getElementById('selected-file-preview');
		const fileNameSpan = document.getElementById('selected-file-name');
		document.getElementById('start-time').value = '00:00:00';

		fileNameSpan.textContent = selectedAudioFile.name;
		filePreview.style.display = 'flex';

		console.log("Datei durch Drag-and-Drop vorgemerkt:", selectedAudioFile);
	}
	// Datei als temporäres Audio laden
const audioElement = document.createElement('audio');
audioElement.src = URL.createObjectURL(selectedAudioFile);
audioElement.addEventListener('loadedmetadata', () => {
	const duration = audioElement.duration; // Sekunden als float
	const formattedDuration = formatSecondsToTime(duration);
	document.getElementById('end-time').value = formattedDuration;
});
});
	document.getElementById('start-upload-btn').addEventListener('click', function () {
	if (!selectedAudioFile) {
		alert("Bitte wähle zuerst eine Datei aus.");
		return;
	}

	const formData = new FormData();
	formData.append('audio_file', selectedAudioFile);

	formData.append('file_path', document.getElementById('file-path').value);
	formData.append('start_time', document.getElementById('start-time').value);
	formData.append('end_time', document.getElementById('end-time').value);
	formData.append('language', document.getElementById('language-select').value);
	formData.append('api', document.getElementById('api-select').value);
	formData.append('speaker_count', document.getElementById('speaker-count').value);

	fetch('/transcript/upload', {
		method: 'POST',
		headers: {
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
		},
		body: formData
	})
	.then(response => response.json())
	.then(data => {
		console.log('✅ Upload abgeschlossen:', data);
		alert("Datei erfolgreich hochgeladen!");
	})
	.catch(error => {
		console.error('❌ Upload fehlgeschlagen:', error);
		alert("Fehler beim Hochladen!");
	});
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