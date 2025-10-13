<!DOCTYPE html>
<html>
<head>
    <title>Whisper Transkription Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { padding: 20px; font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        .progress { display: none; margin-top: 20px; }
        .result { margin-top: 20px; padding: 15px; border: 1px solid #ddd; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Whisper Transkription Test</h1>
        
        <form id="uploadForm" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="audio">Audiodatei auswählen:</label>
                <input type="file" id="audio" name="audio" accept="audio/*" required>
            </div>
            
            <div class="form-group">
                <label for="language">Sprache (optional):</label>
                <select name="language" id="language">
                    <option value="">Auto-Erkennung</option>
                    <option value="de">Deutsch</option>
                    <option value="en">Englisch</option>
                    <option value="fr">Französisch</option>
                </select>
            </div>
            
            <button type="submit">Transkribieren</button>
        </form>
        
        <div class="progress">
            <p>Transkription läuft...</p>
        </div>
        
        <div class="result">
            <h3>Ergebnis:</h3>
            <pre id="transcription"></pre>
        </div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const progress = document.querySelector('.progress');
            const result = document.querySelector('.result');
            const transcriptionElement = document.getElementById('transcription');
            
            progress.style.display = 'block';
            result.style.display = 'none';
            
            try {
                const response = await fetch('/transcribe/test', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    transcriptionElement.textContent = data.text;
                    result.style.display = 'block';
                } else {
                    throw new Error(data.message || 'Fehler bei der Transkription');
                }
            } catch (error) {
                alert('Fehler: ' + error.message);
            } finally {
                progress.style.display = 'none';
            }
        });
    </script>
</body>
</html>
