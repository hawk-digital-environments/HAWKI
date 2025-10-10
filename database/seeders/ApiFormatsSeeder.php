<?php

namespace Database\Seeders;

use App\Models\ApiFormat;
use App\Models\ApiFormatEndpoint;
use Illuminate\Database\Seeder;

class ApiFormatsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apiFormats = [
            [
                'unique_name' => 'openai-api',
                'display_name' => 'OpenAI API',
                'client_adapter' => 'openai',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'compatible_providers' => ['openai', 'openwebui', 'gwdg', 'mistral', 'groq', 'together', 'perplexity'],
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/chat/completions', 'method' => 'POST'],
                    ['name' => 'completions.create', 'path' => '/completions', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/embeddings', 'method' => 'POST'],
                ],
            ],
            [
                'unique_name' => 'openai-responses-api',
                'display_name' => 'OpenAI Responses API',
                'client_adapter' => 'openai',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => false,
                    'supports_reasoning' => true,
                    'description' => 'OpenAI Responses API for reasoning models (o1, o3 series) with enhanced reasoning capabilities',
                    'compatible_providers' => ['openai'],
                    'reasoning_models' => ['o1', 'o1-mini', 'o1-pro', 'o3', 'o3-mini', 'o3-pro', 'o3-deep-research'],
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'responses.create', 'path' => '/responses', 'method' => 'POST'],
                ],
            ],
            [
                'unique_name' => 'ollama-api',
                'display_name' => 'Ollama API',
                'client_adapter' => 'ollama',
                'metadata' => [
                    'auth_type' => 'none',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => false,
                    'compatible_providers' => ['ollama'],
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/api/tags', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/api/chat', 'method' => 'POST'],
                    ['name' => 'generate.create', 'path' => '/api/generate', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/api/embeddings', 'method' => 'POST'],
                ],
            ],
            [
                'unique_name' => 'google-generative-language-api',
                'display_name' => 'Google Generative Language API',
                'client_adapter' => 'google',
                'metadata' => [
                    'auth_type' => 'api_key',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'supports_grounding' => true,
                    'description' => 'Direct access to Gemini models via Google AI Studio',
                    'compatible_providers' => ['google', 'gemini', 'google-ai-studio'],
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/models/{model}:generateContent', 'method' => 'POST'],
                    ['name' => 'chat.stream', 'path' => '/models/{model}:streamGenerateContent', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/models/{model}:embedContent', 'method' => 'POST'],
                    ['name' => 'count_tokens', 'path' => '/models/{model}:countTokens', 'method' => 'POST'],
                ],
            ],
            // TODO: Temporarily disabled - will be re-enabled later
            /*
            [
                'unique_name' => 'google-vertex-ai-api',
                'display_name' => 'Google Vertex AI API',
                'base_url' => 'https://{region}-aiplatform.googleapis.com/v1',
                'provider_class' => 'App\\Services\\AI\\Providers\\GoogleProvider',
                'metadata' => [
                    'auth_type' => 'oauth2',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'supports_grounding' => true,
                    'supports_enterprise_features' => true,
                    'description' => 'Enterprise-grade Gemini models via Google Cloud Vertex AI',
                    'compatible_providers' => ['google', 'vertex-ai', 'google-cloud'],
                    'region_dependent' => true,
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/projects/{project}/locations/{region}/publishers/google/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/projects/{project}/locations/{region}/publishers/google/models/{model}:generateContent', 'method' => 'POST'],
                    ['name' => 'chat.stream', 'path' => '/projects/{project}/locations/{region}/publishers/google/models/{model}:streamGenerateContent', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/projects/{project}/locations/{region}/publishers/google/models/{model}:predict', 'method' => 'POST'],
                    ['name' => 'count_tokens', 'path' => '/projects/{project}/locations/{region}/publishers/google/models/{model}:countTokens', 'method' => 'POST'],
                ]
            ],
            */
            [
                'unique_name' => 'anthropic-api',
                'display_name' => 'Anthropic Claude API',
                'client_adapter' => 'anthropic',
                'metadata' => [
                    'auth_type' => 'api_key',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'compatible_providers' => ['anthropic', 'claude'],
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/messages', 'method' => 'POST'],
                ],
            ],
            // TODO: Temporarily disabled - will be re-enabled later
            /*
            [
                'unique_name' => 'huggingface-api',
                'display_name' => 'Hugging Face API',
                'base_url' => 'https://api-inference.huggingface.co',
                'provider_class' => 'App\\Services\\AI\\Providers\\OpenAIProvider',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => false,
                    'supports_function_calling' => false,
                    'compatible_providers' => ['huggingface'],
                    'description' => 'Uses OpenAI-compatible provider for Hugging Face API',
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/models/{model}', 'method' => 'POST'],
                ]
            ],
            [
                'unique_name' => 'cohere-api',
                'display_name' => 'Cohere API',
                'base_url' => 'https://api.cohere.ai/v1',
                'provider_class' => 'App\\Services\\AI\\Providers\\OpenAIProvider',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => false,
                    'compatible_providers' => ['cohere'],
                    'description' => 'Uses OpenAI-compatible provider for Cohere API',
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/chat', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/embed', 'method' => 'POST'],
                ]
            ],
            */
            [
                'unique_name' => 'gwdg-api',
                'display_name' => 'GWDG AI Service',
                'client_adapter' => 'gwdg',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'compatible_providers' => ['gwdg'],
                    'description' => 'GWDG (Gesellschaft für wissenschaftliche Datenverarbeitung mbH Göttingen) AI service based on OpenAI API',
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/chat/completions', 'method' => 'POST'],
                    ['name' => 'completions.create', 'path' => '/completions', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/embeddings', 'method' => 'POST'],
                ],
            ],
            [
                'unique_name' => 'openwebui-api',
                'display_name' => 'Open WebUI API',
                'client_adapter' => 'openwebui',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'compatible_providers' => ['openwebui'],
                    'description' => 'Open WebUI - Self-hosted web interface for various AI models',
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/api/v1/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/api/v1/chat/completions', 'method' => 'POST'],
                    ['name' => 'completions.create', 'path' => '/api/v1/completions', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/api/v1/embeddings', 'method' => 'POST'],
                ],
            ],
        ];

        foreach ($apiFormats as $formatData) {
            $endpoints = $formatData['endpoints'];
            unset($formatData['endpoints']);

            $apiFormat = ApiFormat::firstOrCreate(
                ['unique_name' => $formatData['unique_name']],
                $formatData
            );

            // Create endpoints for this API format (only if they don't exist)
            foreach ($endpoints as $endpointData) {
                ApiFormatEndpoint::firstOrCreate(
                    [
                        'api_format_id' => $apiFormat->id,
                        'name' => $endpointData['name'],
                    ],
                    array_merge($endpointData, ['api_format_id' => $apiFormat->id])
                );
            }
        }

        // Clean up provider_class from metadata (migration step for existing data)
        $this->cleanupProviderClassFromMetadata();
    }

    /**
     * Clean up provider_class from metadata for existing records (legacy migration)
     */
    private function cleanupProviderClassFromMetadata(): void
    {
        $this->command->info('Cleaning up legacy provider_class from metadata for existing records...');

        ApiFormat::whereNotNull('metadata')->each(function ($apiFormat) {
            $metadata = $apiFormat->metadata;
            $hasChanges = false;

            if (is_array($metadata) && isset($metadata['provider_class'])) {
                // Remove legacy provider_class from metadata (no longer needed)
                unset($metadata['provider_class']);
                $hasChanges = true;
            }

            if ($hasChanges) {
                $apiFormat->update(['metadata' => $metadata]);
                $this->command->info("Cleaned legacy provider_class from metadata: {$apiFormat->unique_name}");
            }
        });

        $this->command->info('Legacy provider class cleanup completed.');
    }
}
