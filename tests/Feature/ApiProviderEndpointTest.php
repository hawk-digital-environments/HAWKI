<?php

namespace Tests\Feature;

use App\Models\ApiFormat;
use App\Models\ApiFormatEndpoint;
use App\Models\ApiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiProviderEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the database with necessary test data
        $this->artisan('db:seed', ['--class' => 'ApiFormatsSeeder']);
        $this->artisan('db:seed', ['--class' => 'ApiProvidersSeeder']);
    }

    /**
     * Test the relationship chain: api_provider -> api_format -> api_format_endpoints
     */
    public function test_api_provider_uses_api_format_with_multiple_endpoints(): void
    {
        // Get OpenAI provider (created by seeder)
        $openaiProvider = ApiProvider::where('provider_name', 'OpenAI')->first();

        $this->assertNotNull($openaiProvider, 'OpenAI provider should exist');
        $this->assertNotNull($openaiProvider->api_format_id, 'Provider should have an API format');

        // Test the relationship chain
        $apiFormat = $openaiProvider->apiFormat;
        $this->assertNotNull($apiFormat, 'Provider should have an associated API format');
        $this->assertEquals('openai-api', $apiFormat->unique_name, 'Should use OpenAI API format');

        // Check that the API format has multiple endpoints
        $endpoints = $apiFormat->endpoints;
        $this->assertGreaterThan(0, $endpoints->count(), 'API format should have endpoints');

        // Verify specific endpoints exist
        $modelsEndpoint = $apiFormat->getModelsEndpoint();
        $chatEndpoint = $apiFormat->getChatEndpoint();

        $this->assertNotNull($modelsEndpoint, 'API format should have a models endpoint');
        $this->assertNotNull($chatEndpoint, 'API format should have a chat endpoint');
    }

    /**
     * Test getModels endpoint URL generation for OpenAI provider
     */
    public function test_get_models_endpoint_url_generation_for_openai(): void
    {
        // Get OpenAI provider
        $openaiProvider = ApiProvider::where('provider_name', 'OpenAI')->first();
        $this->assertNotNull($openaiProvider);

        // Get the models endpoint
        $modelsEndpoint = $openaiProvider->apiFormat->getModelsEndpoint();
        $this->assertNotNull($modelsEndpoint, 'OpenAI API format should have a models endpoint');

        // Test URL generation: provider base_url + endpoint path
        $expectedBaseUrl = 'https://api.openai.com/v1';
        $this->assertEquals($expectedBaseUrl, $openaiProvider->base_url, 'OpenAI provider should have correct base URL');

        // Generate full URL for models endpoint
        $fullModelsUrl = $modelsEndpoint->getFullUrlForProvider($openaiProvider);
        $expectedModelsUrl = $expectedBaseUrl.'/'.ltrim($modelsEndpoint->path, '/');

        $this->assertEquals($expectedModelsUrl, $fullModelsUrl, 'Full models URL should combine base URL and endpoint path');
        $this->assertStringContainsString('/models', $fullModelsUrl, 'Models URL should contain /models path');
    }

    /**
     * Test getModels functionality across different providers
     */
    public function test_get_models_endpoint_for_different_providers(): void
    {
        $providers = ['OpenAI', 'GWDG', 'Google'];

        foreach ($providers as $providerName) {
            $provider = ApiProvider::where('provider_name', $providerName)->first();

            if (! $provider) {
                $this->markTestSkipped("Provider {$providerName} not found in seeded data");

                continue;
            }

            // Each provider should have an API format
            $apiFormat = $provider->apiFormat;
            $this->assertNotNull($apiFormat, "Provider {$providerName} should have an API format");

            // Each API format should have a models endpoint
            $modelsEndpoint = $apiFormat->getModelsEndpoint();
            $this->assertNotNull($modelsEndpoint, "API format for {$providerName} should have a models endpoint");

            // Full URL should be generated correctly
            $fullUrl = $modelsEndpoint->getFullUrlForProvider($provider);
            $this->assertNotNull($fullUrl, "Should generate full URL for {$providerName}");
            $this->assertStringStartsWith('http', $fullUrl, "Full URL should be a valid HTTP URL for {$providerName}");

            // URL should combine base_url and endpoint path
            $this->assertStringContainsString($provider->base_url, $fullUrl, "Full URL should contain provider base URL for {$providerName}");
        }
    }

    /**
     * Test chat endpoint URL generation
     */
    public function test_chat_endpoint_url_generation(): void
    {
        $openaiProvider = ApiProvider::where('provider_name', 'OpenAI')->first();
        $this->assertNotNull($openaiProvider);

        // Get chat endpoint
        $chatEndpoint = $openaiProvider->apiFormat->getChatEndpoint();
        $this->assertNotNull($chatEndpoint, 'OpenAI API format should have a chat endpoint');

        // Test URL generation
        $chatUrl = $chatEndpoint->getFullUrlForProvider($openaiProvider);
        $this->assertNotNull($chatUrl, 'Should generate chat URL');
        $this->assertStringStartsWith($openaiProvider->base_url, $chatUrl, 'Chat URL should start with provider base URL');
        $this->assertStringContainsString('chat/completions', $chatUrl, 'Chat URL should contain chat/completions path');
    }

    /**
     * Test endpoint URL generation with edge cases
     */
    public function test_endpoint_url_generation_edge_cases(): void
    {
        // Create a test API format and provider
        $apiFormat = ApiFormat::create([
            'unique_name' => 'test-api',
            'display_name' => 'Test API',
            'provider_class' => 'GenericModelProvider',
        ]);

        $endpoint = ApiFormatEndpoint::create([
            'api_format_id' => $apiFormat->id,
            'name' => 'test.endpoint',
            'path' => 'test/path',
            'method' => 'GET',
            'is_active' => true,
        ]);

        // Test with provider that has base_url without trailing slash
        $provider1 = ApiProvider::create([
            'provider_name' => 'Test Provider 1',
            'api_format_id' => $apiFormat->id,
            'base_url' => 'https://api.test.com/v1',
            'is_active' => true,
        ]);

        $url1 = $endpoint->getFullUrlForProvider($provider1);
        $this->assertEquals('https://api.test.com/v1/test/path', $url1);

        // Test with provider that has base_url with trailing slash
        $provider2 = ApiProvider::create([
            'provider_name' => 'Test Provider 2',
            'api_format_id' => $apiFormat->id,
            'base_url' => 'https://api.test.com/v1/',
            'is_active' => true,
        ]);

        $url2 = $endpoint->getFullUrlForProvider($provider2);
        $this->assertEquals('https://api.test.com/v1/test/path', $url2);

        // Test edge case: no base_url
        $provider3 = ApiProvider::create([
            'provider_name' => 'Test Provider 3',
            'api_format_id' => $apiFormat->id,
            'base_url' => null,
            'is_active' => true,
        ]);

        $url3 = $endpoint->getFullUrlForProvider($provider3);
        $this->assertNull($url3, 'Should return null when provider has no base_url');
    }

    /**
     * Test that provider uses cached chat_url attribute
     */
    public function test_provider_chat_url_attribute(): void
    {
        $openaiProvider = ApiProvider::where('provider_name', 'OpenAI')->first();
        $this->assertNotNull($openaiProvider);

        // Test chat_url attribute (should use caching)
        $chatUrl = $openaiProvider->chat_url;
        $this->assertNotNull($chatUrl, 'Provider should have chat_url attribute');
        $this->assertStringStartsWith($openaiProvider->base_url, $chatUrl, 'Chat URL should start with base URL');

        // Test that multiple calls return the same result (cache hit)
        $chatUrl2 = $openaiProvider->chat_url;
        $this->assertEquals($chatUrl, $chatUrl2, 'Multiple calls should return same URL (cached)');
    }

    /**
     * Test API format endpoint retrieval methods
     */
    public function test_api_format_endpoint_retrieval_methods(): void
    {
        $openaiFormat = ApiFormat::where('unique_name', 'openai-api')->first();
        $this->assertNotNull($openaiFormat);

        // Test getEndpoint method
        $modelsEndpoint = $openaiFormat->getEndpoint('models.list');
        $this->assertNotNull($modelsEndpoint, 'Should find models.list endpoint');
        $this->assertEquals('models.list', $modelsEndpoint->name);

        // Test getModelsEndpoint convenience method
        $modelsEndpoint2 = $openaiFormat->getModelsEndpoint();
        $this->assertNotNull($modelsEndpoint2, 'getModelsEndpoint should return models endpoint');
        $this->assertEquals($modelsEndpoint->id, $modelsEndpoint2->id, 'Both methods should return same endpoint');

        // Test getChatEndpoint convenience method
        $chatEndpoint = $openaiFormat->getChatEndpoint();
        $this->assertNotNull($chatEndpoint, 'getChatEndpoint should return chat endpoint');
        $this->assertContains($chatEndpoint->name, ['chat.create', 'responses.create', 'completions.create'], 'Chat endpoint should have valid name');
    }

    /**
     * Test Ollama models.list endpoint specifically
     */
    public function test_ollama_models_list_endpoint(): void
    {
        // Get Ollama provider
        $ollamaProvider = ApiProvider::where('provider_name', 'Ollama')->first();
        $this->assertNotNull($ollamaProvider, 'Ollama provider should exist');

        // Test base configuration
        $this->assertEquals('http://localhost:11434', $ollamaProvider->base_url, 'Ollama should use localhost:11434');
        $this->assertNotNull($ollamaProvider->apiFormat, 'Ollama should have API format');
        $this->assertEquals('ollama-api', $ollamaProvider->apiFormat->unique_name, 'Should use Ollama API format');

        // Test models endpoint
        $modelsEndpoint = $ollamaProvider->apiFormat->getModelsEndpoint();
        $this->assertNotNull($modelsEndpoint, 'Ollama API format should have models endpoint');
        $this->assertEquals('models.list', $modelsEndpoint->name, 'Models endpoint should be named models.list');
        $this->assertEquals('/api/tags', $modelsEndpoint->path, 'Ollama models endpoint should use /api/tags path');
        $this->assertEquals('GET', $modelsEndpoint->method, 'Models endpoint should use GET method');

        // Test full URL generation
        $fullModelsUrl = $modelsEndpoint->getFullUrlForProvider($ollamaProvider);
        $expectedUrl = 'http://localhost:11434/api/tags';
        $this->assertEquals($expectedUrl, $fullModelsUrl, 'Full models URL should combine base URL and /api/tags path');

        // Test URL structure: System fragt nach (api_format) EndPoint für Ollama -> sollte base_url + path ausgeben
        $this->assertStringStartsWith($ollamaProvider->base_url, $fullModelsUrl, 'URL should start with provider base_url');
        $this->assertStringEndsWith('/api/tags', $fullModelsUrl, 'URL should end with endpoint path');

        // Verify this is the correct Ollama models endpoint (not OpenAI's /models)
        $this->assertStringContainsString('/api/tags', $fullModelsUrl, 'Ollama should use /api/tags, not /models');
        $this->assertStringNotContainsString('/models', $fullModelsUrl, 'Ollama should not use OpenAI /models endpoint');
    }
}
