<?php

namespace Tests\Feature;

use App\Models\ApiFormat;
use App\Models\ApiProvider;
use App\Orchid\Traits\AiConnectionTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleApiConnectionTest extends TestCase
{
    use RefreshDatabase;

    private $traitInstance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        
        // Create anonymous class that uses the trait
        $this->traitInstance = new class {
            use AiConnectionTrait;
        };
    }

    public function test_google_provider_without_api_key_fails(): void
    {
        // Get Google API format and create a test provider without API key
        $googleFormat = ApiFormat::where('unique_name', 'google-generative-language-api')->first();
        $this->assertNotNull($googleFormat, 'Google API format should exist in database');

        $provider = ApiProvider::create([
            'provider_name' => 'Google Without Key',
            'api_key' => null, // No API key
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'is_active' => true,
            'api_format_id' => $googleFormat->id,
            'additional_settings' => []
        ]);

        // Mock a 401 response (Unauthorized)
        Http::fake([
            '*' => Http::response(['error' => 'API key required'], 401)
        ]);

        // Test connection
        $result = $this->traitInstance->testConnection($provider);

        // Should fail with 401 status
        $this->assertFalse($result['success']);
        $this->assertEquals('HTTP 401', $result['error']);
        $this->assertEquals(401, $result['status_code']);
    }

    public function test_google_provider_with_invalid_api_key_fails(): void
    {
        // Get Google API format and create a test provider with invalid API key
        $googleFormat = ApiFormat::where('unique_name', 'google-generative-language-api')->first();
        
        $provider = ApiProvider::create([
            'provider_name' => 'Google Invalid Key',
            'api_key' => 'invalid-key',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'is_active' => true,
            'api_format_id' => $googleFormat->id,
            'additional_settings' => []
        ]);

        // Mock a 400 response (Bad Request)
        Http::fake([
            '*' => Http::response(['error' => 'Invalid API key'], 400)
        ]);

        // Test connection
        $result = $this->traitInstance->testConnection($provider);

        // Should fail with 400 status
        $this->assertFalse($result['success']);
        $this->assertEquals('HTTP 400', $result['error']);
        $this->assertEquals(400, $result['status_code']);

        // Verify the request was made with query parameter
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'key=invalid-key') && 
                   !$request->hasHeader('Authorization');
        });
    }

    public function test_google_provider_with_valid_api_key_succeeds(): void
    {
        // Get Google API format and create a test provider with valid API key
        $googleFormat = ApiFormat::where('unique_name', 'google-generative-language-api')->first();
        
        $provider = ApiProvider::create([
            'provider_name' => 'Google Valid Key',
            'api_key' => 'valid-google-api-key',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'is_active' => true,
            'api_format_id' => $googleFormat->id,
            'additional_settings' => []
        ]);

        // Mock a successful response
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-pro',
                        'displayName' => 'Gemini Pro',
                        'description' => 'The best model for scaling across a wide range of tasks'
                    ],
                    [
                        'name' => 'models/gemini-pro-vision',
                        'displayName' => 'Gemini Pro Vision',
                        'description' => 'The best image understanding model to handle a broad range of applications'
                    ]
                ]
            ], 200)
        ]);

        // Test connection
        $result = $this->traitInstance->testConnection($provider);

        // Should succeed
        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status_code']);

        // Verify the request was made with query parameter
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'key=valid-google-api-key') && 
                   !$request->hasHeader('Authorization');
        });
    }

    public function test_fetch_google_models_returns_raw_models_array(): void
    {
        // Get Google API format and create a test provider
        $googleFormat = ApiFormat::where('unique_name', 'google-generative-language-api')->first();
        
        $provider = ApiProvider::create([
            'provider_name' => 'Google Models Test',
            'api_key' => 'test-models-key',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'is_active' => true,
            'api_format_id' => $googleFormat->id,
            'additional_settings' => []
        ]);

        // Mock Google API response format
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-pro',
                        'displayName' => 'Gemini Pro',
                        'description' => 'The best model for scaling across a wide range of tasks',
                        'supportedGenerationMethods' => ['generateContent']
                    ],
                    [
                        'name' => 'models/gemini-pro-vision',
                        'displayName' => 'Gemini Pro Vision',
                        'description' => 'The best image understanding model to handle a broad range of applications',
                        'supportedGenerationMethods' => ['generateContent']
                    ]
                ]
            ], 200)
        ]);

        // Fetch models
        $models = $this->traitInstance->fetchModelsDirectly($provider);

        // Should return raw models array from Google API
        $this->assertIsArray($models);
        $this->assertCount(2, $models);
        
        // Check Google API structure (raw response)
        $this->assertArrayHasKey('name', $models[0]);
        $this->assertArrayHasKey('displayName', $models[0]);
        $this->assertArrayHasKey('description', $models[0]);
        
        // Verify specific model data from Google API response
        $this->assertEquals('models/gemini-pro', $models[0]['name']);
        $this->assertEquals('Gemini Pro', $models[0]['displayName']);
        
        // Verify the request was made with query parameter authentication
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'key=test-models-key') && 
                   !$request->hasHeader('Authorization');
        });
    }

    public function test_google_model_id_cleaning(): void
    {
        // Get Google API format and create a test provider
        $googleFormat = ApiFormat::where('unique_name', 'google-generative-language-api')->first();
        
        $provider = ApiProvider::create([
            'provider_name' => 'Google Model ID Test',
            'api_key' => 'test-key',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'is_active' => true,
            'api_format_id' => $googleFormat->id,
            'additional_settings' => []
        ]);

        // Mock Google API response with models/ prefixed IDs
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/embedding-gecko-001',
                        'displayName' => 'Embedding Gecko 001',
                        'description' => 'Text embedding model'
                    ],
                    [
                        'name' => 'models/gemini-pro',
                        'displayName' => 'Gemini Pro',
                        'description' => 'Multi-modal large language model'
                    ]
                ]
            ], 200)
        ]);

        // Fetch and save models to database
        $models = $this->traitInstance->fetchModelsDirectly($provider);
        $saveResult = $this->traitInstance->saveModelsToDatabase($provider, $models);

        // Verify save was successful
        $this->assertTrue($saveResult['success']);
        $this->assertEquals(2, $saveResult['total']);

        // Verify model IDs in database have 'models/' prefix removed
        $savedModels = \App\Models\AiModel::where('provider_id', $provider->id)->get();
        
        $this->assertCount(2, $savedModels);
        
        // Check that model IDs don't have 'models/' prefix
        $modelIds = $savedModels->pluck('model_id')->toArray();
        $this->assertContains('embedding-gecko-001', $modelIds);
        $this->assertContains('gemini-pro', $modelIds);
        
        // Verify they don't contain the 'models/' prefix
        $this->assertNotContains('models/embedding-gecko-001', $modelIds);
        $this->assertNotContains('models/gemini-pro', $modelIds);
        
        // Verify display names are preserved
        $geckoModel = $savedModels->where('model_id', 'embedding-gecko-001')->first();
        $geminiModel = $savedModels->where('model_id', 'gemini-pro')->first();
        
        $this->assertEquals('Embedding Gecko 001', $geckoModel->label);
        $this->assertEquals('Gemini Pro', $geminiModel->label);
    }
}