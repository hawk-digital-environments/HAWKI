<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class TranscriptionService
{
    protected $apiUrl;
    
    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->apiUrl = config('model_providers.whisper.api_url', 'http://localhost:8000');
        $this->validateEnvironment();
    }

    /**
     * Überprüft, ob die Umgebung korrekt konfiguriert ist
     */
    protected function validateEnvironment()
    {
        try {
            // Teste API-Verbindung
            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->apiUrl . '/health');
            
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException("Whisper API nicht erreichbar unter: " . $this->apiUrl);
            }
        } catch (Exception $e) {
            Log::error('Environment validation error: ' . $e->getMessage());
            throw new RuntimeException("Whisper API nicht verfügbar: " . $e->getMessage());
        }
    }

    /**
     * Transkribiert eine Audiodatei
     */
    public function transcribeAudio($audioFile, $language = null)
    {
        try {
            // Temporärer Pfad für die Audiodatei
            $tempPath = storage_path('app/temp/' . uniqid() . '.' . $audioFile->getClientOriginalExtension());
            
            // Datei temporär speichern
            $audioFile->move(dirname($tempPath), basename($tempPath));

            // Transkriptionsoptionen
            $options = [
                'language' => $language,
                'task' => 'transcribe',
                'model' => 'base',
                'device' => 'cpu'
            ];

            // Transkription durchführen
            $result = $this->processAudioFile($tempPath, $options);

            // Temporäre Datei löschen
            unlink($tempPath);

            return $result;
        } catch (Exception $e) {
            Log::error('Transcription error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verarbeitet die Audiodatei mit Whisper
     */
    protected function processAudioFile($audioPath, $options)
    {
        try {
            $client = new \GuzzleHttp\Client();
            
            // Bereite Multipart-Request vor
            $response = $client->post($this->apiUrl . '/transcribe', [
                'timeout' => 60, // Timeout in Sekunden
                'multipart' => [
                    [
                        'name' => 'audio',
                        'contents' => fopen($audioPath, 'r'),
                        'filename' => basename($audioPath)
                    ],
                    [
                        'name' => 'model',
                        'contents' => $options['model'] ?? 'base'
                    ],
                    [
                        'name' => 'language',
                        'contents' => $options['language'] ?? 'auto'
                    ]
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new Exception("API-Fehler: " . $response->getBody());
            }

            $result = json_decode($response->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Ungültige API-Antwort");
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Audio processing error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Prüft den Status einer Transkription
     */
    public function getTranscriptionStatus($jobId)
    {
        try {
            // Status-Implementation hier...
            return [
                'status' => 'completed',
                'progress' => 100
            ];
        } catch (Exception $e) {
            Log::error('Status check error: ' . $e->getMessage());
            throw $e;
        }
    }
}
