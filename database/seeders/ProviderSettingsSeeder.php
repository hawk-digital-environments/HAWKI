<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProviderSetting;
use Illuminate\Support\Facades\Config;

class ProviderSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lade die Standard-Konfiguration aus der model_providers.php.example
        $configPath = config_path('model_providers.php.example');
        
        if (!file_exists($configPath)) {
            $this->command->info('Keine Beispiel-Provider-Konfiguration gefunden unter: ' . $configPath);
            return;
        }
        
        // Direkt die Beispiel-Konfigurationsdatei laden
        $config = require $configPath;

        if (!$config || !isset($config['providers']) || !is_array($config['providers'])) {
            $this->command->info('Keine gültige Provider-Konfiguration in der Beispieldatei gefunden.');
            return;
        }

        $providers = $config['providers'];
        
        foreach ($providers as $providerName => $providerConfig) {
            $this->command->info("Importiere Provider: {$providerName}");

            // Erstelle Standarddaten für den Provider
            $providerData = [
                'provider_name' => $providerName,
                'api_key' => $providerConfig['api_key'] ?? null,
                'base_url' => $providerConfig['api_url'] ?? $providerConfig['base_url'] ?? null,
                'ping_url' => $providerConfig['ping_url'] ?? null,
                'is_active' => $providerConfig['active'] ?? false,
                'api_format' => $providerConfig['api_format'] ?? $providerName,
            ];

            // Speichere zusätzliche Einstellungen im additional_settings Feld
            $additionalSettings = [];
            foreach ($providerConfig as $key => $value) {
                if (!in_array($key, ['api_key', 'api_url', 'base_url', 'ping_url', 'active', 'is_active', 'api_format'])) {
                    $additionalSettings[$key] = $value;
                }
            }

            if (!empty($additionalSettings)) {
                $providerData['additional_settings'] = json_encode($additionalSettings); // Hier zu JSON konvertieren
            }

            // Erstelle oder aktualisiere den Provider in der Datenbank
            ProviderSetting::updateOrCreate(
                ['provider_name' => $providerName],
                $providerData
            );
        }

        $this->command->info('Provider-Daten wurden erfolgreich aus der Beispielkonfiguration importiert.');
    }
}
