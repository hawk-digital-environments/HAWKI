<?php

namespace Database\Seeders;

use App\Models\ApiFormat;
use App\Models\ApiFormatEndpoint;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'base_url' => 'https://api.openai.com/v1',
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
                ]
            ],
            [
                'unique_name' => 'ollama-api',
                'display_name' => 'Ollama API',
                'base_url' => 'http://localhost:11434',
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
                ]
            ],
            [
                'unique_name' => 'google-generative-language-api',
                'display_name' => 'Google Generative Language API',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
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
                ]
            ],
            [
                'unique_name' => 'google-vertex-ai-api',
                'display_name' => 'Google Vertex AI API',
                'base_url' => 'https://{region}-aiplatform.googleapis.com/v1',
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
            [
                'unique_name' => 'anthropic-api',
                'display_name' => 'Anthropic Claude API',
                'base_url' => 'https://api.anthropic.com/v1',
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
                ]
            ],
            [
                'unique_name' => 'huggingface-api',
                'display_name' => 'Hugging Face API',
                'base_url' => 'https://api-inference.huggingface.co',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => false,
                    'supports_function_calling' => false,
                    'compatible_providers' => ['huggingface'],
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
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => false,
                    'compatible_providers' => ['cohere'],
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/chat', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/embed', 'method' => 'POST'],
                ]
            ],
            [
                'unique_name' => 'gwdg-api',
                'display_name' => 'GWDG AI Service',
                'base_url' => 'https://chat-ai.academiccloud.de/v1',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'compatible_providers' => ['gwdg'],
                    'description' => 'GWDG (Gesellschaft für wissenschaftliche Datenverarbeitung mbH Göttingen) AI service based on OpenAI API'
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/chat/completions', 'method' => 'POST'],
                    ['name' => 'completions.create', 'path' => '/completions', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/embeddings', 'method' => 'POST'],
                ]
            ],
            [
                'unique_name' => 'openwebui-api',
                'display_name' => 'Open WebUI API',
                'base_url' => 'http://localhost:3000',
                'metadata' => [
                    'auth_type' => 'bearer',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'compatible_providers' => ['openwebui'],
                    'description' => 'Open WebUI - Self-hosted web interface for various AI models'
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/api/v1/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/api/v1/chat/completions', 'method' => 'POST'],
                    ['name' => 'completions.create', 'path' => '/api/v1/completions', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/api/v1/embeddings', 'method' => 'POST'],
                ]
            ],
        ];

        foreach ($apiFormats as $formatData) {
            $endpoints = $formatData['endpoints'];
            unset($formatData['endpoints']);
            
            $apiFormat = ApiFormat::updateOrCreate(
                ['unique_name' => $formatData['unique_name']],
                $formatData
            );
            
            // Create endpoints for this API format
            foreach ($endpoints as $endpointData) {
                ApiFormatEndpoint::updateOrCreate(
                    [
                        'api_format_id' => $apiFormat->id,
                        'name' => $endpointData['name']
                    ],
                    array_merge($endpointData, ['api_format_id' => $apiFormat->id])
                );
            }
        }
    }
}
