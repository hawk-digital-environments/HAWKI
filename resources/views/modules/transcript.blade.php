@extends('layouts.home')
@section('content')
    <style>
        /* Zeigt Warte-Cursor an, wenn Klasse "loading" auf <body> gesetzt wird */
        .cursor-wait {
            cursor: wait !important;
        }

        .spinner {
            width: 32px;
            height: 32px;
            border: 4px solid rgba(0, 0, 0, 0.2);
            border-top-color: #000;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: auto;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        #drop-zone {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            min-height: 100px;
            border: 2px dashed #ccc;
            cursor: pointer;
        }

        .context-menu {
            position: absolute;
            z-index: 9999;
            background-color: white;
            border: 1px solid #ccc;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            padding: 5px 0;
            border-radius: 4px;
            min-width: 150px;
            display: none;
        }

        .context-menu-item {
            padding: 8px 12px;
            cursor: pointer;
            white-space: nowrap;
        }

        .context-menu-item:hover {
            background-color: #f0f0f0;
        }

        .transcript-sidebar-field {
            display: flex;
            flex-direction: column;
            margin-bottom: 12px;
            width: 100%;
        }

        .transcript-sidebar-field label {
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .transcript-sidebar-field input,
        .transcript-sidebar-field select {
            height: 36px;
            padding: 6px 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }
    </style>


    <div class="main-panel-grid">
        <div class="dy-sidebar expanded" id="transcript-sidebar">
            <div class="dy-sidebar-wrapper">
                <!-- <div class="welcome-panel">
                        <h1>{{ Auth::user()->name }}</h1>
                       </div> -->
                <div class="header">
                    <button class="btn-md-stroke" onclick="showTranscriptChoice()">
                        <div class="icon">
                            <x-icon name="plus" />
                        </div>
                        <div class="label"><strong>Neue Transcription</strong></div>
                    </button>
                    <h3 class="title" id="history-title">{{ $translation['History'] }}</h3>

                </div>
                <div class="dy-sidebar-content-panel">
                    <div class="dy-sidebar-scroll-panel">
                        <div class="selection-list" id="chats-list">
                            <div id="file-transcription-options" style="display: none; padding: 10px;">
                                <div class="transcript-sidebar-field">
                                    <label for="file-path">📄 Dateipfad:</label>
                                    <input type="text" name="file_path" id="file-path"
                                        placeholder="Pfad zur Datei eingeben">
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
                                        <!--<option value="google">Google Speech-to-Text</option>
                                        <option value="custom">Eigene API</option>-->
                                    </select>
                                    <div class="transcript-sidebar-section" id="speaker-recognition-wrapper"
                                        style="display: none;">
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
                    <x-icon name="chevron-right" />
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
                            <button class="back-icon-button" onclick="showTranscriptChoice()"
                                title="Zurück zur Auswahl">←</button>

                            <p class="transcript-info">Bitte ziehen Sie eine Datei in das Feld oder klicken Sie darauf.</p>
                            <div class="drop-zone" id="drop-zone">
                                <span id="drop-text">Drag-und-Drop</span>
                                <div id="loading-spinner" style="display: none;">
                                    <div class="spinner"></div>
                                </div>
                            </div>
                            <form id="transcript-upload-form" enctype="multipart/form-data">
                                <input type="file" name="audio_file" id="audio_file" style="display: none;">
                            </form>
                            <hr class="section-divider">
                            <div id="selected-file-preview" class="transcript-file-preview"
                                style="margin-top: 10px; display: none;">
                                📎 <span id="selected-file-name">Keine Datei ausgewählt</span>
                            </div>
                        </div>
                    </div>

                    <div id="transcription-output" style="display: none; margin-top: 20px; position: relative;">


                        <!-- Kopierbutton oben rechts -->
                        <button id="copy-transcript-btn" class="copy-button-inline"
                            title="In Zwischenablage kopieren">Kopieren</button>

                        <!-- Textcontainer -->
                        <div class="transcription-box" id="transcription-result-container">
                            <div id="transcription-result"></div>
                        </div>
                    </div>


                    <!-- UI Live Aufnahme -->
                    <div id="transcript-live-ui" style="display: none;">
                        <div class="transcript-section live-transcript-ui">
                            <img src="https://cdn-icons-png.flaticon.com/512/727/727245.png" alt="Mikrofon"
                                class="microphone-image">

                            <div class="recording-controls">
                                <button class="control-button record" title="Aufnehmen"></button>
                                <button class="control-button pause" title="Pause"></button>
                                <button class="control-button stop" title="Stop"></button>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="warning">{{ $translation['MistakeWarning'] }}</p>

            </div>
        </div>
    </div>



    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            fileInput.addEventListener('change', function() {
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
            document.getElementById('start-upload-btn').addEventListener('click', function() {
                if (!selectedAudioFile) {
                    alert("Bitte wähle zuerst eine Datei aus.");
                    return;
                }
                document.getElementById('drop-text').style.display = 'none';
                document.getElementById('loading-spinner').style.display = 'block';
                document.body.classList.add('cursor-wait');

                const formData = new FormData();
                formData.append('file', selectedAudioFile);

                /* Erstmal ignorieren
                formData.append('file_path', document.getElementById('file-path').value);
                formData.append('start_time', document.getElementById('start-time').value);
                formData.append('end_time', document.getElementById('end-time').value);
                formData.append('language', document.getElementById('language-select').value);
                formData.append('api', document.getElementById('api-select').value);
                formData.append('speaker_count', document.getElementById('speaker-count').value); */

                fetch('http://localhost:8001/transcribe', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content'),
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('loading-spinner').style.display = 'none';
                        document.getElementById('drop-text').style.display = 'block';
                        document.body.classList.remove('cursor-wait');
                        console.log('✅ Upload abgeschlossen:', JSON.stringify(data, null, 2));

                        if (data.text) {
                            // Zeige die Transkription im DOM an
                            const outputDiv = document.getElementById('transcription-result');

                            // Alle Eingabeflächen ausblenden
                            document.getElementById('transcript-file-ui').style.display = 'none';

                            outputDiv.innerText = data.text;

                            document.getElementById('history-title').style.display = 'none';
                            document.querySelectorAll('.history-entry').forEach(e => e.style.display =
                                'none');

                            saveTranscriptToHistory(data.text);

                            // Blende den gesamten Container sichtbar ein
                            document.getElementById('drop-zone').style.display = 'none';
                            document.getElementById('selected-file-preview').style.display = 'none';
                            // Zeige Transkript und verstecke Drop-Zone + Dateinamen
                            document.getElementById('transcription-output').style.display = 'flex';
                            document.getElementById('transcript-file-ui').style.display = 'none';
                            document.getElementById('selected-file-preview').style.display = 'none';
                            // Zeige Kopier-Button

                            document.getElementById('copy-transcript-btn').onclick = function() {
                                const text = document.getElementById('transcription-result')
                                    .innerText;
                                navigator.clipboard.writeText(text)
                                    .then(() => alert(
                                        'Transkription wurde in die Zwischenablage kopiert!'))
                                    .catch(err => alert('Fehler beim Kopieren: ' + err));
                            };
                        } else {
                            alert("Keine Transkription erhalten.");
                        }
                    })
                    .catch(error => {

                        document.getElementById('loading-spinner').style.display = 'none';
                        document.getElementById('drop-text').style.display = 'block';
                        document.body.classList.remove('cursor-wait');

                        console.error('❌ Upload fehlgeschlagen:', error);
                        alert("Fehler beim Hochladen!");
                    });
            });


            function renderHistory() {
                const list = document.getElementById("chats-list");
                if (!list) return;
                //start - upload
                // Vorherige Einträge entfernen
                list.querySelectorAll(".history-entry").forEach(e => e.remove());

                const history = JSON.parse(localStorage.getItem("transcriptionHistory")) || [];
                const contextMenu = document.getElementById("custom-context-menu");

                history.forEach(entry => {
                    const wrapper = document.createElement("div");
                    wrapper.className = "history-entry selection-item";
                    wrapper.style.cursor = "pointer";
                    wrapper.textContent = entry.title;

                    // Sichtbarkeit steuern
                    wrapper.classList.add("history-entry");

                    // Links-Klick: Transkript laden
                    wrapper.onclick = () => loadTranscript(entry.id);

                    // Rechtsklick: Kontextmenü anzeigen
                    wrapper.oncontextmenu = (e) => {
                        e.preventDefault();

                        contextMenu.innerHTML = "";

                        const renameOption = document.createElement("div");
                        renameOption.textContent = "📝 Umbenennen";
                        renameOption.className = "context-menu-item";
                        renameOption.onclick = () => {
                            const newTitle = prompt("Neuer Titel:", entry.title);
                            if (newTitle) {
                                entry.title = newTitle;
                                localStorage.setItem("transcriptionHistory", JSON.stringify(
                                    history));
                                renderHistory(); // wichtig!
                            }
                            contextMenu.style.display = "none";
                        };

                        const deleteOption = document.createElement("div");
                        deleteOption.textContent = "🗑️ Löschen";
                        deleteOption.className = "context-menu-item";
                        deleteOption.onclick = () => {
                            if (confirm("Diesen Eintrag wirklich löschen?")) {
                                const updated = history.filter(e => e.id !== entry.id);
                                localStorage.setItem("transcriptionHistory", JSON.stringify(
                                    updated));
                                renderHistory(); // wichtig!
                            }
                            contextMenu.style.display = "none";
                        };

                        contextMenu.appendChild(renameOption);
                        contextMenu.appendChild(deleteOption);

                        contextMenu.style.top = `${e.pageY}px`;
                        contextMenu.style.left = `${e.pageX}px`;
                        contextMenu.style.display = "block";
                    };

                    list.appendChild(wrapper);
                });
            }

            // Optional: ESC schließt Menü
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape") {
                    const contextMenu = document.getElementById("custom-context-menu");
                    if (contextMenu) {
                        contextMenu.style.display = "none";
                    }
                }
            });
            renderHistory(true);
        });



        function showTranscriptMode(mode) {
            document.getElementById('transcript-choice').style.display = 'none';
            document.getElementById('history-title').style.display = 'none';
            document.getElementById('history-title').style.display = 'none';
            document.querySelectorAll('.history-entry').forEach(e => e.style.display = 'none');
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
            // Auswahl anzeigen
            document.getElementById('transcript-choice').style.display = 'block';
            document.getElementById('history-title').style.display = 'block';
            document.getElementById('history-title').style.display = 'block';
            document.querySelectorAll('.history-entry').forEach(e => e.style.display = 'block');

            // Alles andere ausblenden
            document.getElementById('transcript-file-ui').style.display = 'none';
            document.getElementById('transcript-live-ui').style.display = 'none';
            document.getElementById('file-transcription-options').style.display = 'none';
            document.getElementById('start-upload-wrapper').style.display = 'none';
            document.getElementById('speaker-recognition-wrapper').style.display = 'none';
            document.getElementById('back-button-wrapper').style.display = 'none';

            // Transkriptionsergebnis zurücksetzen
            const outputDiv = document.getElementById('transcription-output');
            const resultDiv = document.getElementById('transcription-result');
            const copyBtn = document.getElementById('copy-transcript-btn');

            if (outputDiv) outputDiv.style.display = 'none';
            if (resultDiv) resultDiv.innerText = '';
            if (copyBtn) copyBtn.style.display = 'none';

            // Drop-Zone sichtbar machen
            const dropZone = document.getElementById('drop-zone');
            if (dropZone) dropZone.style.display = 'flex'; // NICHT 'none' – wir wollen es wieder anzeigen!

            // Datei-Vorschau und Name zurücksetzen
            const filePreview = document.getElementById('selected-file-preview');
            if (filePreview) filePreview.style.display = 'none';
            const fileNameSpan = document.getElementById('selected-file-name');
            if (fileNameSpan) fileNameSpan.textContent = 'Keine Datei ausgewählt';

            // Zustand zurücksetzen
            selectedAudioFile = null;
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

        function saveTranscriptToHistory(text) {
            const timestamp = new Date().toLocaleString();
            const id = `transcript-${Date.now()}`;
            const entry = {
                id,
                title: `Transkription vom ${timestamp}`,
                content: text
            };

            let history = JSON.parse(localStorage.getItem("transcriptionHistory")) || [];
            history.unshift(entry);
            localStorage.setItem("transcriptionHistory", JSON.stringify(history));

            // renderHistory(false);
        }


        function loadTranscript(id) {
            const history = JSON.parse(localStorage.getItem("transcriptionHistory")) || [];
            const entry = history.find(e => e.id === id);
            if (!entry) return;

            document.getElementById('transcription-result').innerText = entry.content;
            document.getElementById('transcription-output').style.display = 'flex';
            document.getElementById('copy-transcript-btn').style.display = 'block';
            document.getElementById('back-button-wrapper').style.display = 'block';

            // Alles andere ausblenden
            document.getElementById('transcript-choice').style.display = 'none';
            document.getElementById('transcript-file-ui').style.display = 'none';
            document.getElementById('transcript-live-ui').style.display = 'none';
            document.getElementById('file-transcription-options').style.display = 'none';
        }
    </script>

    <div id="custom-context-menu" class="context-menu"></div>
@endsection
