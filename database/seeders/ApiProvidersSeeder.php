<?php

namespace Database\Seeders;

use App\Models\ApiFormat;
use App\Models\ApiProvider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ApiProvidersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * IMPORTANT: This seeder only runs when the api_providers table is empty (initial setup).
     * For updates/deployments, new providers should be added via migrations, not seeders.
     * This ensures existing provider configurations are never touched during updates.
     */
    public function run(): void
    {
        // Check if table already has data - skip seeding if not empty
        if (ApiProvider::count() > 0) {
            $this->command->info('API Providers table is not empty. Skipping seeder (use migrations for new providers).');
            return;
        }
        
        $this->command->info('API Providers table is empty. Running initial seed...');
        
        // Map Provider Names to their default configurations
        $providerConfigs = [
            'OpenAI' => [
                'api_format_unique_name' => 'openai-responses-api',
                'base_url' => 'https://api.openai.com/v1',
                'is_active' => true,
                'display_order' => 1,
            ],
            'GWDG' => [
                'api_format_unique_name' => 'gwdg-api',
                'base_url' => 'https://chat-ai.academiccloud.de/v1',
                'is_active' => true,
                'display_order' => 2,
            ],
            'Google' => [
                'api_format_unique_name' => 'google-generative-language-api',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'is_active' => true,
                'display_order' => 3,
            ],
            'Anthropic' => [
                'api_format_unique_name' => 'anthropic-api',
                'base_url' => 'https://api.anthropic.com/v1',
                'is_active' => false, // Requires specific API key
                'display_order' => 4,
            ],
            'Ollama' => [
                'api_format_unique_name' => 'ollama-api',
                'base_url' => 'http://localhost:11434',
                'is_active' => false, // Local service, inactive by default
                'display_order' => 10,
            ],
            'Open WebUI' => [
                'api_format_unique_name' => 'openwebui-api',
                'base_url' => 'http://localhost:3000',
                'is_active' => false, // Local service, inactive by default
                'display_order' => 11,
            ],
        ];

        foreach ($providerConfigs as $providerName => $config) {
            // Find the API format by unique name
            $apiFormat = ApiFormat::where('unique_name', $config['api_format_unique_name'])->first();
            
            if (!$apiFormat) {
                $this->command->warn("API Format '{$config['api_format_unique_name']}' not found for provider '{$providerName}'. Skipping.");
                continue;
            }

            // Create new provider with all default values
            // Since we only run when table is empty, all providers are new
            $provider = ApiProvider::create([
                'provider_name' => $providerName,
                'api_format_id' => $apiFormat->id,
                'base_url' => $config['base_url'],
                'is_active' => $config['is_active'],
                'display_order' => $config['display_order'],
                'additional_settings' => [
                    'description' => "Default {$providerName} provider configuration",
                    'created_by_seeder' => true,
                    'seeded_at' => now()->toISOString(),
                ],
            ]);
            $this->command->info("Created provider: {$providerName} (ID: {$provider->id}) with API format: {$apiFormat->display_name}");
        }

        $this->command->info('API Providers seeding completed.');
    }
}
