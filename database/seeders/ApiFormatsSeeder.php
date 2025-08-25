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
                'unique_name' => 'google-api',
                'display_name' => 'Google Gemini API',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'metadata' => [
                    'auth_type' => 'api_key',
                    'content_type' => 'application/json',
                    'supports_streaming' => true,
                    'supports_function_calling' => true,
                    'compatible_providers' => ['google', 'gemini'],
                ],
                'endpoints' => [
                    ['name' => 'models.list', 'path' => '/models', 'method' => 'GET'],
                    ['name' => 'chat.create', 'path' => '/models/{model}:generateContent', 'method' => 'POST'],
                    ['name' => 'embeddings.create', 'path' => '/models/{model}:embedContent', 'method' => 'POST'],
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
