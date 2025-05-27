<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str; 

class TranscriptController extends Controller
{
    public function upload(Request $request) {
        if (!$request->hasFile('audio_file')) {
            return response()->json(['error' => 'Keine Datei hochgeladen'], 400);
        }
        $file = $request->file('audio_file');
        $filename = Str::random(10) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('transcriptions/input', $filename);

            // Voller Pfad zur gespeicherten Datei
    $fullPath = storage_path('app/' . $path);

    // Sprache übernehmen aus Formular, sonst 'de'
    $language = $request->input('language', 'de');

    // Whisper-Kommando zusammensetzen
    $command = "python3 -m whisper " . escapeshellarg($fullPath) . " --language " . escapeshellarg($language);

    // Whisper ausführe
    exec($command, $output, $resultCode);

    if ($resultCode !== 0) {
        return response()->json([
            'error' => 'Transkription fehlgeschlagen',
            'details' => $output
        ], 500);
    }

    return response()->json([
        'message' => 'Transkription abgeschlossen',
        'output' => implode("\n", $output)
    ]);
}

        return response()->json([
            'message' => 'Datei erfolgreich empfangen',
            'filename' => $filename,
            'path' => $path
        ]);
        // Pfad zur Datei und Sprache
$fullPath = storage_path('app/' . $path);
$language = $request->input('language', 'de'); // fallback: Deutsch

// Python-Skript ausführen
$command = escapeshellcmd("python3 whisper/transcribe.py \"$fullPath\" $language");
$output = shell_exec($command);

// Antwort verarbeiten
$data = json_decode($output, true);

return response()->json([
    'message' => 'Transkription abgeschlossen',
    'transcript' => $data['text'] ?? 'Keine Ausgabe'
]);
    }
}
