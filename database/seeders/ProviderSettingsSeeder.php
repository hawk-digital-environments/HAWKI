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
        // Load the default configuration from the model_providers.php.example file
        $configPath = config_path('model_providers.php.example');
        
        if (!file_exists($configPath)) {
            $this->command->info('Keine Beispiel-Provider-Konfiguration gefunden unter: ' . $configPath);
            return;
        }
        
        // Directly load the example configuration file
        $config = require $configPath;

        if (!$config || !isset($config['providers']) || !is_array($config['providers'])) {
            $this->command->info('Keine gültige Provider-Konfiguration in der Beispieldatei gefunden.');
            return;
        }

        $providers = $config['providers'];
        
        foreach ($providers as $providerName => $providerConfig) {
            $this->command->info("Importiere Provider: {$providerName}");

            // Create default data for the provider
            $providerData = [
                'provider_name' => $providerName,
                'api_key' => $providerConfig['api_key'] ?? null,
                'base_url' => $providerConfig['api_url'] ?? $providerConfig['base_url'] ?? null,
                'ping_url' => $providerConfig['ping_url'] ?? null,
                'is_active' => $providerConfig['active'] ?? false,
                'api_format' => $providerConfig['api_format'] ?? $providerName,
            ];

            // Save additional settings in the additional_settings field
            $additionalSettings = [];
            foreach ($providerConfig as $key => $value) {
                if (!in_array($key, ['api_key', 'api_url', 'base_url', 'ping_url', 'active', 'is_active', 'api_format'])) {
                    $additionalSettings[$key] = $value;
                }
            }

            if (!empty($additionalSettings)) {
                $providerData['additional_settings'] = json_encode($additionalSettings); // Hier zu JSON konvertieren
            }

            // Create or update the provider in the database
            ProviderSetting::updateOrCreate(
                ['provider_name' => $providerName],
                $providerData
            );
        }

        $this->command->info('Provider-Daten wurden erfolgreich aus der Beispielkonfiguration importiert.');
    }
}
