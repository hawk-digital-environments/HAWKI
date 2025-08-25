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
                'provider_name' => 'OpenAI',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('openai'),
                'is_active' => false,
                'additional_settings' => json_encode([
                    'description' => 'OpenAI GPT Models',
                    'models' => ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo']
                ])
            ],
            [
                'provider_name' => 'Google',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('google'),
                'is_active' => false,
                'additional_settings' => json_encode([
                    'description' => 'Google Gemini Models',
                    'models' => ['gemini-pro', 'gemini-pro-vision']
                ])
            ],
            [
                'provider_name' => 'Ollama',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('ollama'),
                'is_active' => false,
                'additional_settings' => json_encode([
                    'description' => 'Local Ollama Installation',
                    'models' => ['llama2', 'mistral', 'codellama']
                ])
            ],
            [
                'provider_name' => 'GWDG',
                'api_key' => null,
                'api_format_id' => $this->getApiFormatId('openai'),
                'is_active' => false,
                'additional_settings' => json_encode([
                    'description' => 'GWDG AI Service',
                    'models' => ['gpt-3.5-turbo', 'gpt-4']
                ])
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
        $apiFormat = ApiFormat::where('name', $formatName)->first();
        return $apiFormat ? $apiFormat->id : null;
    }
}
