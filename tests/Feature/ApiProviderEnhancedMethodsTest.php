<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ApiFormat;
use App\Models\ApiFormatEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiProviderEnhancedMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedApiFormatsAndProviders();
    }

    private function seedApiFormatsAndProviders(): void
    {
        // Create Ollama API Format
        $ollamaFormat = ApiFormat::create([
            'unique_name' => 'ollama-api',
            'display_name' => 'Ollama API',
            'metadata' => [
                'supports_streaming' => true,
                'supports_function_calling' => false,
            ],
        ]);

        // Create Ollama Endpoints
        ApiFormatEndpoint::create([
            'api_format_id' => $ollamaFormat->id,
            'name' => 'models.list',
            'path' => '/api/tags',
            'method' => 'GET',
            'is_active' => true,
        ]);

        ApiFormatEndpoint::create([
            'api_format_id' => $ollamaFormat->id,
            'name' => 'chat.create',
            'path' => '/api/chat',
            'method' => 'POST',
            'is_active' => true,
        ]);

        ApiFormatEndpoint::create([
            'api_format_id' => $ollamaFormat->id,
            'name' => 'embeddings.create',
            'path' => '/api/embeddings',
            'method' => 'POST',
            'is_active' => true,
        ]);

        // Create Ollama Provider
        ApiProvider::create([
            'provider_name' => 'Ollama',
            'api_format_id' => $ollamaFormat->id,
            'base_url' => 'http://localhost:11434',
            'is_active' => true,
            'display_order' => 10,
        ]);

        // Create OpenAI API Format
        $openaiFormat = ApiFormat::create([
            'unique_name' => 'openai-api',
            'display_name' => 'OpenAI API',
            'metadata' => [
                'supports_streaming' => true,
                'supports_function_calling' => true,
            ],
        ]);

        // Create OpenAI Endpoints
        ApiFormatEndpoint::create([
            'api_format_id' => $openaiFormat->id,
            'name' => 'models.list',
            'path' => '/v1/models',
            'method' => 'GET',
            'is_active' => true,
        ]);

        ApiFormatEndpoint::create([
            'api_format_id' => $openaiFormat->id,
            'name' => 'chat.create',
            'path' => '/v1/chat/completions',
            'method' => 'POST',
            'is_active' => true,
        ]);

        // Create OpenAI Provider
        ApiProvider::create([
            'provider_name' => 'OpenAI',
            'api_format_id' => $openaiFormat->id,
            'base_url' => 'https://api.openai.com',
            'api_key' => 'test-api-key',
            'is_active' => true,
            'display_order' => 1,
        ]);
    }

    public function test_generic_get_url_for_endpoint_method(): void
    {
        $ollama = ApiProvider::where('provider_name', 'Ollama')->first();

        $modelsUrl = $ollama->getUrlForEndpoint('models.list');
        $chatUrl = $ollama->getUrlForEndpoint('chat.create');
        $nonExistentUrl = $ollama->getUrlForEndpoint('non.existent');

        $this->assertEquals('http://localhost:11434/api/tags', $modelsUrl);
        $this->assertEquals('http://localhost:11434/api/chat', $chatUrl);
        $this->assertNull($nonExistentUrl);
    }

    public function test_convenience_methods_for_common_endpoints(): void
    {
        $ollama = ApiProvider::where('provider_name', 'Ollama')->first();
        $openai = ApiProvider::where('provider_name', 'OpenAI')->first();

        // Test Ollama URLs
        $this->assertEquals('http://localhost:11434/api/tags', $ollama->getModelsUrl());
        $this->assertEquals('http://localhost:11434/api/chat', $ollama->getChatUrl());

        // Test OpenAI URLs
        $this->assertEquals('https://api.openai.com/v1/models', $openai->getModelsUrl());
        $this->assertEquals('https://api.openai.com/v1/chat/completions', $openai->getChatUrl());
    }

    public function test_get_all_endpoint_urls(): void
    {
        $ollama = ApiProvider::where('provider_name', 'Ollama')->first();
        $allUrls = $ollama->getAllEndpointUrls();

        $this->assertIsArray($allUrls);
        $this->assertArrayHasKey('models.list', $allUrls);
        $this->assertArrayHasKey('chat.create', $allUrls);
        $this->assertArrayHasKey('embeddings.create', $allUrls);

        $this->assertEquals('http://localhost:11434/api/tags', $allUrls['models.list']);
        $this->assertEquals('http://localhost:11434/api/chat', $allUrls['chat.create']);
        $this->assertEquals('http://localhost:11434/api/embeddings', $allUrls['embeddings.create']);
    }

    public function test_has_endpoint_method(): void
    {
        $ollama = ApiProvider::where('provider_name', 'Ollama')->first();

        $this->assertTrue($ollama->hasEndpoint('models.list'));
        $this->assertTrue($ollama->hasEndpoint('chat.create'));
        $this->assertTrue($ollama->hasEndpoint('embeddings.create'));
        $this->assertFalse($ollama->hasEndpoint('non.existent'));
    }

    public function test_health_status_method(): void
    {
        $ollama = ApiProvider::where('provider_name', 'Ollama')->first();
        $openai = ApiProvider::where('provider_name', 'OpenAI')->first();

        // Test Ollama health (no API key)
        $ollamaHealth = $ollama->getHealthStatus();
        $this->assertEquals('Ollama', $ollamaHealth['provider_name']);
        $this->assertTrue($ollamaHealth['is_active']);
        $this->assertTrue($ollamaHealth['has_base_url']);
        $this->assertFalse($ollamaHealth['has_api_key']);
        $this->assertIsArray($ollamaHealth['available_endpoints']);
        $this->assertEquals(3, $ollamaHealth['total_endpoints']);

        // Test OpenAI health (with API key)
        $openaiHealth = $openai->getHealthStatus();
        $this->assertEquals('OpenAI', $openaiHealth['provider_name']);
        $this->assertTrue($openaiHealth['is_active']);
        $this->assertTrue($openaiHealth['has_base_url']);
        $this->assertTrue($openaiHealth['has_api_key']);
        $this->assertIsArray($openaiHealth['available_endpoints']);
        $this->assertEquals(2, $openaiHealth['total_endpoints']);
    }

    public function test_find_by_name_static_method(): void
    {
        // Create inactive provider
        $inactiveProvider = ApiProvider::create([
            'provider_name' => 'Inactive Provider',
            'api_format_id' => ApiFormat::first()->id,
            'base_url' => 'http://example.com',
            'is_active' => false,
            'display_order' => 99,
        ]);

        // Test finding active provider
        $ollama = ApiProvider::findByName('Ollama');
        $this->assertNotNull($ollama);
        $this->assertEquals('Ollama', $ollama->provider_name);

        // Test finding inactive provider (should return null)
        $inactive = ApiProvider::findByName('Inactive Provider');
        $this->assertNull($inactive);

        // Test finding non-existent provider
        $nonExistent = ApiProvider::findByName('Non Existent');
        $this->assertNull($nonExistent);
    }

    public function test_find_by_endpoint_static_method(): void
    {
        $providersWithModels = ApiProvider::findByEndpoint('models.list');
        $providersWithChat = ApiProvider::findByEndpoint('chat.create');
        $providersWithNonExistent = ApiProvider::findByEndpoint('non.existent');

        // Both Ollama and OpenAI should have models.list
        $this->assertEquals(2, $providersWithModels->count());
        $providerNames = $providersWithModels->pluck('provider_name')->toArray();
        $this->assertContains('Ollama', $providerNames);
        $this->assertContains('OpenAI', $providerNames);

        // Both should also have chat.create
        $this->assertEquals(2, $providersWithChat->count());

        // None should have non-existent endpoint
        $this->assertEquals(0, $providersWithNonExistent->count());
    }

    public function test_get_all_with_endpoints_static_method(): void
    {
        // Create inactive provider
        ApiProvider::create([
            'provider_name' => 'Inactive Provider',
            'api_format_id' => ApiFormat::first()->id,
            'base_url' => 'http://example.com',
            'is_active' => false,
            'display_order' => 99,
        ]);

        $activeProviders = ApiProvider::getAllWithEndpoints();

        // Should only return active providers
        $this->assertEquals(2, $activeProviders->count());

        // Each provider should have endpoint_urls property
        foreach ($activeProviders as $provider) {
            $this->assertTrue(isset($provider->endpoint_urls));
            $this->assertIsArray($provider->endpoint_urls);
            $this->assertGreaterThan(0, count($provider->endpoint_urls));
        }

        // Check specific provider endpoints
        $ollama = $activeProviders->where('provider_name', 'Ollama')->first();
        $this->assertNotNull($ollama);
        $this->assertArrayHasKey('models.list', $ollama->endpoint_urls);
        $this->assertEquals('http://localhost:11434/api/tags', $ollama->endpoint_urls['models.list']);
    }

    public function test_is_base_url_accessible_method(): void
    {
        // Mock HTTP responses
        Http::fake([
            'localhost:11434' => Http::response('OK', 200),
            'api.openai.com' => Http::response('Unauthorized', 401), // Should still be considered accessible
            'nonexistent.com' => Http::response('Server Error', 500), // Should be considered inaccessible
        ]);

        $ollama = ApiProvider::where('provider_name', 'Ollama')->first();
        $openai = ApiProvider::where('provider_name', 'OpenAI')->first();

        // Create provider with server error URL
        $failingProvider = ApiProvider::create([
            'provider_name' => 'Failing Provider',
            'api_format_id' => ApiFormat::first()->id,
            'base_url' => 'http://nonexistent.com',
            'is_active' => true,
            'display_order' => 50,
        ]);

        $this->assertTrue($ollama->isBaseUrlAccessible());
        $this->assertTrue($openai->isBaseUrlAccessible()); // 401 < 500, so accessible
        $this->assertFalse($failingProvider->isBaseUrlAccessible()); // 500 >= 500, so not accessible
    }

    public function test_url_caching_functionality(): void
    {
        $ollama = ApiProvider::where('provider_name', 'Ollama')->first();

        // First call should generate and cache URL
        $firstCallUrl = $ollama->getUrlForEndpoint('models.list');
        $this->assertEquals('http://localhost:11434/api/tags', $firstCallUrl);

        // Second call should use cached value (same result)
        $secondCallUrl = $ollama->getUrlForEndpoint('models.list');
        $this->assertEquals($firstCallUrl, $secondCallUrl);

        // Clear caches
        $ollama->clearUrlCaches();

        // Third call should regenerate URL (still same result but fresh)
        $thirdCallUrl = $ollama->getUrlForEndpoint('models.list');
        $this->assertEquals($firstCallUrl, $thirdCallUrl);
    }

    public function test_display_order_and_ordered_scope(): void
    {
        // Create additional provider with specific display order
        $customProvider = ApiProvider::create([
            'provider_name' => 'Custom Provider',
            'api_format_id' => ApiFormat::first()->id,
            'base_url' => 'http://custom.example.com',
            'is_active' => true,
            'display_order' => 5,
        ]);

        // Test ordered scope
        $orderedProviders = ApiProvider::ordered()->get();
        
        $this->assertGreaterThanOrEqual(3, $orderedProviders->count());
        
        // Check that providers are ordered by display_order first
        $displayOrders = $orderedProviders->pluck('display_order')->toArray();
        $sortedDisplayOrders = $displayOrders;
        sort($sortedDisplayOrders);
        
        $this->assertEquals($sortedDisplayOrders, $displayOrders, 'Providers should be ordered by display_order');
        
        // Test that Ollama (display_order 10) comes after OpenAI (display_order 1)
        $openaiPosition = $orderedProviders->search(function ($provider) {
            return $provider->provider_name === 'OpenAI';
        });
        
        $ollamaPosition = $orderedProviders->search(function ($provider) {
            return $provider->provider_name === 'Ollama';
        });
        
        $this->assertLessThan($ollamaPosition, $openaiPosition, 'OpenAI should come before Ollama in ordered list');
        
        // Test display_order filter functionality
        $primaryProviders = ApiProvider::where('display_order', '<=', 5)->ordered()->get();
        foreach ($primaryProviders as $provider) {
            $this->assertLessThanOrEqual(5, $provider->display_order);
        }
    }
}