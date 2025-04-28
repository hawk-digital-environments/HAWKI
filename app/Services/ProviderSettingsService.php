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
        $config = Config::get('model_providers', []);
        $stats = ['imported' => 0, 'skipped' => 0, 'total' => 0];
        
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
                'api_format' => $providerName, // Der Provider-Name wird standardmäßig als API-Schema verwendet
                'api_key' => $providerConfig['api_key'] ?? null,
                'base_url' => $providerConfig['api_url'] ?? $providerConfig['base_url'] ?? null,
                'ping_url' => $providerConfig['ping_url'] ?? null,
                'is_active' => $providerConfig['active'] ?? false,
            ];

            // Überschreibe das Standard-Schema, wenn es explizit angegeben ist
            if (isset($providerConfig['api_format'])) {
                $providerData['api_format'] = $providerConfig['api_format'];
            }

            // Speichere zusätzliche Einstellungen im additional_settings Feld
            $additionalSettings = [];
            foreach ($providerConfig as $key => $value) {
                if (!in_array($key, ['api_key', 'api_url', 'base_url', 'ping_url', 'active', 'is_active', 'api_format'])) {
                    $additionalSettings[$key] = $value;
                }
            }

            if (!empty($additionalSettings)) {
                $providerData['additional_settings'] = $additionalSettings;
            }

            // Prüfe, ob der Provider bereits existiert
            $existingProvider = ProviderSetting::where('provider_name', $providerName)->first();
            
            if (!$existingProvider) {
                // Erstelle neuen Provider
                ProviderSetting::create($providerData);
                $stats['imported']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }
}
