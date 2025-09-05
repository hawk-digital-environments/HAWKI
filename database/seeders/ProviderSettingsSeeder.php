<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProviderSetting;
use App\Models\ApiFormat;

class ProviderSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default provider configurations using the new database-driven API format system
        $defaultProviders = [
            [
                'provider_name' => 'GWDG',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('gwdg-api'),
                'is_active' => false,
                'additional_settings' => null
            ],
            [
                'provider_name' => 'OpenAI',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('openai-api'),
                'is_active' => false,
                'additional_settings' => null
            ],
            [
                'provider_name' => 'Google',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('google-generative-language-api'),
                'is_active' => false,
                'additional_settings' => null
            ],
            [
                'provider_name' => 'Anthropic',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('anthropic-api'),
                'is_active' => false,
                'additional_settings' => null
            ],
            [
                'provider_name' => 'Ollama',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('ollama-api'),
                'is_active' => false,
                'additional_settings' => null
            ],
            
        ];
        
        foreach ($defaultProviders as $providerData) {
            $this->command->info("Erstelle Standard-Provider: {$providerData['provider_name']}");

            // Create or update the provider in the database
            ProviderSetting::updateOrCreate(
                ['provider_name' => $providerData['provider_name']],
                $providerData
            );
        }

        $this->command->info('Standard-Provider wurden erfolgreich erstellt.');
    }

    /**
     * Get API format ID by name
     */
    private function getApiFormatId(string $formatName): ?int
    {
        $apiFormat = ApiFormat::where('unique_name', $formatName)->first();
        return $apiFormat ? $apiFormat->id : null;
    }
}
