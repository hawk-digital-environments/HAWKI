<?php

namespace Tests\Feature;

use App\Models\ApiFormat;
use App\Models\ApiProvider;
use App\Orchid\Traits\AiConnectionTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiConnectionTraitTest extends TestCase
{
    use RefreshDatabase;

    // Create a helper class to test the trait
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

    public function test_google_api_uses_query_parameter_authentication(): void
    {
        // Mock HTTP response
        Http::fake([
            '*' => Http::response(['models' => []], 200)
        ]);

        // Get Google API format and create a test provider
        $googleFormat = ApiFormat::where('unique_name', 'google-generative-language-api')->first();
        $this->assertNotNull($googleFormat, 'Google API format should exist in database');

        $provider = ApiProvider::create([
            'provider_name' => 'Test Google',
            'api_key' => 'test-api-key',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'is_active' => true,
            'api_format_id' => $googleFormat->id,
            'additional_settings' => []
        ]);

        // Test connection using the trait method
        $result = $this->traitInstance->testConnection($provider);

        // Verify the request was made with query parameter authentication
        Http::assertSent(function ($request) {
            // Check that the request URL contains the API key as query parameter
            $url = $request->url();
            return str_contains($url, 'key=test-api-key') && 
                   !$request->hasHeader('Authorization');
        });

        $this->assertTrue($result['success']);
    }

    public function test_openai_api_uses_bearer_token_authentication(): void
    {
        // Mock HTTP response
        Http::fake([
            '*' => Http::response(['data' => []], 200)
        ]);

        // Get OpenAI API format and create a test provider
        $openaiFormat = ApiFormat::where('unique_name', 'openai-api')->first();
        $this->assertNotNull($openaiFormat, 'OpenAI API format should exist in database');

        $provider = ApiProvider::create([
            'provider_name' => 'Test OpenAI',
            'api_key' => 'test-api-key',
            'base_url' => 'https://api.openai.com/v1',
            'is_active' => true,
            'api_format_id' => $openaiFormat->id,
            'additional_settings' => []
        ]);

        // Test connection using the trait method
        $result = $this->traitInstance->testConnection($provider);

        // Verify the request was made with Bearer token authentication
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                   !str_contains($request->url(), 'key=');
        });

        $this->assertTrue($result['success']);
    }

    public function test_fetch_models_directly_uses_correct_authentication(): void
    {
        // Mock HTTP response for Google API
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'models' => [
                    ['name' => 'models/gemini-pro', 'displayName' => 'Gemini Pro']
                ]
            ], 200)
        ]);

        // Get Google API format and create a test provider
        $googleFormat = ApiFormat::where('unique_name', 'google-generative-language-api')->first();
        $provider = ApiProvider::create([
            'provider_name' => 'Test Google Models',
            'api_key' => 'test-models-key',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'is_active' => true,
            'api_format_id' => $googleFormat->id,
            'additional_settings' => []
        ]);

        // Fetch models
        $models = $this->traitInstance->fetchModelsDirectly($provider);

        // Verify the request was made with query parameter authentication
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'key=test-models-key') && 
                   !$request->hasHeader('Authorization');
        });

        $this->assertIsArray($models);
    }

    public function test_inactive_provider_returns_error(): void
    {
        $provider = ApiProvider::create([
            'provider_name' => 'Inactive Provider',
            'api_key' => 'test-key',
            'base_url' => 'https://example.com',
            'is_active' => false,
            'api_format_id' => 1,
            'additional_settings' => []
        ]);

        $result = $this->traitInstance->testConnection($provider);

        $this->assertFalse($result['success']);
        $this->assertEquals('Provider is inactive', $result['error']);
    }

    public function test_provider_without_base_url_returns_error(): void
    {
        $provider = ApiProvider::create([
            'provider_name' => 'No URL Provider',
            'api_key' => 'test-key',
            'base_url' => null,
            'is_active' => true,
            'api_format_id' => 1,
            'additional_settings' => []
        ]);

        $result = $this->traitInstance->testConnection($provider);

        $this->assertFalse($result['success']);
        $this->assertEquals('No base URL configured', $result['error']);
    }
}