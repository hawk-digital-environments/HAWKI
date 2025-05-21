<?php

namespace App\Services;

use App\Models\ProviderSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Exception;

class ProviderSettingsService
{
    public function getAllProviders(): Collection
    {
        return ProviderSetting::all();
    }

    public function getProviderSettings(string $providerName)
    {
        return ProviderSetting::where('provider_name', $providerName)->first();
    }

    /**
     * Update provider settings.
     *
     * @param int $providerId
     * @param array $settings
     * @return ProviderSetting
     */
    public function updateProviderSettings(int $providerId, array $settings)
    {
        $provider = ProviderSetting::find($providerId);
        
        if (!$provider) {
            return null;
        }
        
        $provider->update($settings);
        
        return $provider;
    }

    public function isProviderActive(string $providerName): bool
    {
        $provider = $this->getProviderSettings($providerName);
        return $provider ? $provider->is_active : false;
    }
    
    public function getApiKey(string $providerName): ?string
    {
        $provider = $this->getProviderSettings($providerName);
        return $provider ? $provider->api_key : null;
    }

    /**
     * Delete a provider by ID.
     *
     * @param int $providerId
     * @return bool
     * @throws Exception
     */
    public function deleteProvider(int $providerId): bool
    {
        $provider = ProviderSetting::find($providerId);
        
        if (!$provider) {
            return false;
        }
        
        if ($provider->provider_name === 'default') {
            throw new Exception("The default provider cannot be deleted.");
        }
        
        return $provider->delete();
    }

    /**
     * Import all provider settings from the model_providers.php configuration file
     * into the database.
     *
     * @return array Statistics about imported providers
     */
    public function importFromConfig(): array
    {
        $configPath = config_path('model_providers.php');
        $stats = ['imported' => 0, 'updated' => 0, 'total' => 0];
        
        // Prüfen, ob die Konfigurationsdatei existiert
        if (!file_exists($configPath)) {
            return $stats;
        }
        
        $config = require $configPath;
        
        // Prüfen, ob die Konfiguration korrekt strukturiert ist
        if (!is_array($config) || !isset($config['providers']) || !is_array($config['providers'])) {
            return $stats;
        }
        
        // Wir benötigen nur die Provider aus dem 'providers' Key
        $providers = $config['providers'];
        $stats['total'] = count($providers);
        
        foreach ($providers as $providerName => $providerConfig) {
            // Sicherstellen, dass $providerConfig ein Array ist
            if (!is_array($providerConfig)) {
                continue;
            }
            
            // Erstelle Standarddaten für den Provider
            $providerData = [
                'provider_name' => $providerName,
                'api_format' => $providerConfig['api_format'] ?? $providerName, // Api-Format explizit setzen oder Provider-Namen nutzen
                'api_key' => $providerConfig['api_key'] ?? null,
                'base_url' => $providerConfig['api_url'] ?? $providerConfig['base_url'] ?? null,
                'ping_url' => $providerConfig['ping_url'] ?? null,
                'is_active' => $providerConfig['active'] ?? false,
            ];

            // Speichere zusätzliche Einstellungen im additional_settings Feld
            $additionalSettings = [];
            foreach ($providerConfig as $key => $value) {
                if (!in_array($key, ['api_key', 'api_url', 'base_url', 'ping_url', 'active', 'is_active', 'api_format'])) {
                    $additionalSettings[$key] = $value;
                }
            }

            // Wenn zusätzliche Einstellungen vorhanden sind, konvertieren wir sie zu JSON
            if (!empty($additionalSettings)) {
                $providerData['additional_settings'] = json_encode($additionalSettings);
            }

            // Aktualisiere oder erstelle den Provider - existierende werden überschrieben
            $existingProvider = ProviderSetting::updateOrCreate(
                ['provider_name' => $providerName],
                $providerData
            );
            
            if ($existingProvider->wasRecentlyCreated) {
                $stats['imported']++;
            } else {
                $stats['updated']++;
            }
        }

        return $stats;
    }
}
