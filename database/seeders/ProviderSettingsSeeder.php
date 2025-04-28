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
        // Lade die Konfiguration aus der model_providers.php
        $config = Config::get('model_providers');

        if (!$config || !isset($config['providers']) || !is_array($config['providers'])) {
            $this->command->info('Keine gültige Provider-Konfiguration gefunden.');
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
            ];

            // Speichere zusätzliche Einstellungen im additional_settings Feld
            $additionalSettings = [];
            foreach ($providerConfig as $key => $value) {
                if (!in_array($key, ['api_key', 'api_url', 'base_url', 'ping_url', 'active', 'is_active'])) {
                    $additionalSettings[$key] = $value;
                }
            }

            if (!empty($additionalSettings)) {
                $providerData['additional_settings'] = $additionalSettings;
            }

            // Erstelle oder aktualisiere den Provider in der Datenbank
            ProviderSetting::updateOrCreate(
                ['provider_name' => $providerName],
                $providerData
            );
        }

        $this->command->info('Provider-Daten wurden erfolgreich importiert.');
    }
}
