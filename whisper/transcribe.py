import sys
import json
import whisper

# Eingabewerte auslesen
audio_path = sys.argv[1]
language = sys.argv[2]

# Whisper-Modell laden
model = whisper.load_model("base")  # oder "small", "medium", je nachdem was installiert ist

# Transkription starten
result = model.transcribe(audio_path, language=language)

# Ausgabe im JSON-Format
print(json.dumps({
    "text": result["text"]
}))