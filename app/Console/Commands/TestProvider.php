<?php

namespace App\Console\Commands;

use App\Models\ProviderSetting;
use App\Services\Settings\ModelSettingsService;
use App\Services\AI\AIProviderFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestProvider extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'provider:test 
                            {provider? : Provider ID or name to test}
                            {--all : Test all active providers}
                            {--curl : Generate CURL commands instead of making requests}
                            {--models : Test models endpoint}
                            {--ping : Test ping endpoint (default)}
                            {--endpoints : Test all available endpoints}
                            {--responses : Test responses endpoint (OpenAI Responses API)}
                            {--web-search : Test web search functionality with responses}
                            {--model=gpt-4 : Model to use for responses/web-search tests}
                            {--message=Hello : Message to send for responses/web-search tests}
                            {--timeout=30 : Request timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test provider connections and generate CURL commands for manual testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $providerInput = $this->argument('provider');
        $testAll = $this->option('all');
        $generateCurl = $this->option('curl');
        $testModels = $this->option('models');
        $testEndpoints = $this->option('endpoints');
        $testResponses = $this->option('responses');
        $testWebSearch = $this->option('web-search');
        $testPing = $this->option('ping') || (!$testModels && !$testEndpoints && !$testResponses && !$testWebSearch); // Default to ping
        $timeout = (int) $this->option('timeout');
        $model = $this->option('model');
        $message = $this->option('message');

        if (!$providerInput && !$testAll) {
            $this->error('Please specify a provider ID/name or use --all to test all providers');
            $this->showAvailableProviders();
            return 1;
        }

        $providers = $this->getProviders($providerInput, $testAll);

        if ($providers->isEmpty()) {
            $this->error('No providers found');
            return 1;
        }

        $this->info("Testing " . $providers->count() . " provider(s)...\n");

        foreach ($providers as $provider) {
            $this->testProvider($provider, $generateCurl, $testModels, $testPing, $testEndpoints, $testResponses, $testWebSearch, $timeout, $model, $message);
            $this->newLine();
        }

        return 0;
    }

    /**
     * Get providers to test
     */
    private function getProviders($providerInput, bool $testAll)
    {
        if ($testAll) {
            return ProviderSetting::with(['apiFormat', 'apiFormat.activeEndpoints'])->where('is_active', true)->get();
        }

        // Try to find by ID first, then by name
        $provider = ProviderSetting::with(['apiFormat', 'apiFormat.activeEndpoints'])
            ->where('id', $providerInput)
            ->orWhere('provider_name', 'like', "%{$providerInput}%")
            ->first();

        return $provider ? collect([$provider]) : collect();
    }

    /**
     * Test a specific provider
     */
    private function testProvider(ProviderSetting $provider, bool $generateCurl, bool $testModels, bool $testPing, bool $testEndpoints, bool $testResponses, bool $testWebSearch, int $timeout, string $model, string $message)
    {
        $this->line("🔍 <fg=cyan>Testing Provider:</> <fg=yellow>{$provider->provider_name}</> (ID: {$provider->id})");
        $this->line("   <fg=gray>API Format:</> " . ($provider->apiFormat ? $provider->apiFormat->display_name : 'None'));
        $this->line("   <fg=gray>Base URL:</> {$provider->base_url}");
        $this->line("   <fg=gray>Active:</> " . ($provider->is_active ? '✅ Yes' : '❌ No'));

        if (!$provider->is_active) {
            $this->warn("   ⚠️  Provider is inactive - skipping tests");
            return;
        }

        if (!$provider->base_url) {
            $this->error("   ❌ No base URL configured");
            return;
        }

        // Test ping endpoint (now uses models endpoint for better connection testing)
        if ($testPing) {
            $this->testConnectionEndpoint($provider, $generateCurl, $timeout);
        }

        // Test models endpoint
        if ($testModels) {
            $this->testModelsEndpoint($provider, $generateCurl, $timeout);
        }

        // Test responses endpoint
        if ($testResponses) {
            $this->testResponsesEndpoint($provider, $generateCurl, $timeout, $model, $message, false);
        }

        // Test web search functionality
        if ($testWebSearch) {
            $this->testResponsesEndpoint($provider, $generateCurl, $timeout, $model, $message, true);
        }

        // Test all available endpoints
        if ($testEndpoints) {
            $this->testAllEndpoints($provider, $generateCurl, $timeout);
        }
    }

    /**
     * Test connection endpoint (uses models endpoint for reliable connection testing)
     */
    private function testConnectionEndpoint(ProviderSetting $provider, bool $generateCurl, int $timeout)
    {
        $this->line("\n   📡 <fg=blue>Testing Connection</>");
        
        if (!$provider->apiFormat) {
            $this->error("   ❌ No API format configured");
            return;
        }

        $modelsEndpoint = $provider->apiFormat->getModelsEndpoint();
        if (!$modelsEndpoint || !$modelsEndpoint->is_active) {
            $this->warn("   ⚠️  No active models endpoint configured - testing base URL instead");
            // Fallback to base URL test
            $url = rtrim($provider->base_url, '/');
            $headers = $this->buildHeaders($provider);
            $finalUrl = $this->buildUrl($provider, $url);
        } else {
            // Use models endpoint for connection test
            $baseUrl = rtrim($provider->base_url, '/');
            $url = $baseUrl . '/' . ltrim($modelsEndpoint->path, '/');
            
            // Fix double v1 issue - if base_url ends with /v1 and endpoint starts with /v1, remove one
            if (str_ends_with($provider->base_url, '/v1') && str_starts_with($modelsEndpoint->path, '/v1')) {
                $url = rtrim($provider->base_url, '/') . substr($modelsEndpoint->path, 3); // Remove /v1 from endpoint
            }
            
            $headers = $this->buildHeaders($provider);
            $finalUrl = $this->buildUrl($provider, $url);
        }

        if ($generateCurl) {
            $curlCommand = $this->generateCurlCommand('GET', $finalUrl, $headers, null, $timeout);
            $this->line("   <fg=green>CURL Command:</>");
            $this->line("   <fg=white>{$curlCommand}</>");
        } else {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->get($finalUrl);

                $this->displayResponse('Connection', $response, $finalUrl, $headers, $timeout);
            } catch (\Exception $e) {
                $this->error("   ❌ Request failed: " . $e->getMessage());
                
                // Still show CURL for debugging
                $curlCommand = $this->generateCurlCommand('GET', $finalUrl, $headers, null, $timeout);
                $this->line("   <fg=yellow>Debug CURL:</>");
                $this->line("   <fg=white>{$curlCommand}</>");
            }
        }
    }

    /**
     * Test models endpoint
     */
    private function testModelsEndpoint(ProviderSetting $provider, bool $generateCurl, int $timeout)
    {
        $this->line("\n   🤖 <fg=blue>Testing Models Endpoint</>");

        if (!$provider->apiFormat) {
            $this->error("   ❌ No API format configured");
            return;
        }

        $modelsEndpoint = $provider->apiFormat->getModelsEndpoint();
        if (!$modelsEndpoint || !$modelsEndpoint->is_active) {
            $this->error("   ❌ No active models endpoint configured");
            return;
        }

        $baseUrl = rtrim($provider->base_url, '/');
        $url = $baseUrl . '/' . ltrim($modelsEndpoint->path, '/');
        
        // Fix double v1 issue - if base_url ends with /v1 and endpoint starts with /v1, remove one
        if (str_ends_with($provider->base_url, '/v1') && str_starts_with($modelsEndpoint->path, '/v1')) {
            $url = rtrim($provider->base_url, '/') . substr($modelsEndpoint->path, 3); // Remove /v1 from endpoint
        }
        
        $headers = $this->buildHeaders($provider);
        $finalUrl = $this->buildUrl($provider, $url);

        if ($generateCurl) {
            $curlCommand = $this->generateCurlCommand('GET', $finalUrl, $headers, null, $timeout);
            $this->line("   <fg=green>CURL Command:</>");
            $this->line("   <fg=white>{$curlCommand}</>");
        } else {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->get($finalUrl);

                $this->displayResponse('Models', $response, $finalUrl, $headers, $timeout);
            } catch (\Exception $e) {
                $this->error("   ❌ Request failed: " . $e->getMessage());
                
                // Still show CURL for debugging
                $curlCommand = $this->generateCurlCommand('GET', $finalUrl, $headers, null, $timeout);
                $this->line("   <fg=yellow>Debug CURL:</>");
                $this->line("   <fg=white>{$curlCommand}</>");
            }
        }
    }

    /**
     * Test responses endpoint (OpenAI Responses API with optional web search)
     */
    private function testResponsesEndpoint(ProviderSetting $provider, bool $generateCurl, int $timeout, string $model, string $message, bool $includeWebSearch)
    {
        $endpointType = $includeWebSearch ? 'Responses with Web Search' : 'Responses';
        $this->line("\n   🤖 <fg=blue>Testing {$endpointType} Endpoint</>");

        // Check if this is an OpenAI Responses API provider
        if (!$provider->apiFormat || $provider->apiFormat->unique_name !== 'openai-responses-api') {
            $this->warn("   ⚠️  This test is only available for OpenAI Responses API providers");
            return;
        }

        try {
            // Create provider instance to test web search support
            $providerFactory = app(AIProviderFactory::class);
            $aiProvider = $providerFactory->getProviderInterface($provider->apiFormat->unique_name);
            
            // Configure the provider
            $additionalSettings = is_array($provider->additional_settings) 
                ? $provider->additional_settings 
                : json_decode($provider->additional_settings, true) ?? [];
                
            $config = [
                'api_key' => $provider->api_key,
                'base_url' => $provider->base_url,
                'provider_name' => $provider->provider_name,
                'additional_settings' => $additionalSettings
            ];
            
            $aiProvider = new \App\Services\AI\Providers\OpenAIResponsesProvider($config);
            
            // Check model compatibility and web search support
            $this->line("   <fg=gray>Model:</> {$model}");
            $this->line("   <fg=gray>Message:</> {$message}");
            
            $isCompatible = $aiProvider->isModelCompatible($model);
            $this->line("   <fg=gray>Model Compatible:</> " . ($isCompatible ? '✅ Yes' : '❌ No'));
            
            if (!$isCompatible) {
                $this->error("   ❌ Model '{$model}' is not compatible with OpenAI Responses API");
                $this->line("   💡 Try using a gpt-4 or later model (e.g., gpt-4, gpt-5-mini)");
                return;
            }
            
            if ($includeWebSearch) {
                $supportsSearch = $aiProvider->modelSupportsSearch($model);
                $this->line("   <fg=gray>Web Search Support:</> " . ($supportsSearch ? '✅ Yes' : '❌ No'));
                
                if (!$supportsSearch) {
                    $this->warn("   ⚠️  Model '{$model}' does not support web search");
                    $this->line("   💡 Try using gpt-4o, gpt-5-mini, or similar models");
                }
            }
            
            // Prepare the payload
            $rawPayload = [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $message
                    ]
                ],
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 500
            ];
            
            $payload = $aiProvider->formatPayload($rawPayload);
            
            // Show payload information
            $this->line("   <fg=gray>Tools in payload:</> " . (isset($payload['tools']) ? count($payload['tools']) : 0));
            if (isset($payload['tools'])) {
                foreach ($payload['tools'] as $i => $tool) {
                    $this->line("     - Tool " . ($i + 1) . ": " . ($tool['type'] ?? 'unknown'));
                }
            }
            
            // Build URL and headers
            $baseUrl = rtrim($provider->base_url, '/');
            $url = $baseUrl . '/responses';
            $headers = $this->buildHeaders($provider);
            $finalUrl = $this->buildUrl($provider, $url);
            
            if ($generateCurl) {
                $curlCommand = $this->generateCurlCommand('POST', $finalUrl, $headers, $payload, $timeout);
                $this->line("   <fg=green>CURL Command:</>");
                $this->line("   <fg=white>{$curlCommand}</>");
            } else {
                try {
                    $this->line("   <fg=yellow>Making API request...</> (this may take up to {$timeout}s)");
                    
                    $response = Http::timeout($timeout)
                        ->withHeaders($headers)
                        ->post($finalUrl, $payload);

                    $this->displayResponsesResponse($response, $aiProvider, $includeWebSearch);
                    
                    // Show CURL for debugging
                    $curlCommand = $this->generateCurlCommand('POST', $finalUrl, $headers, $payload, $timeout);
                    $this->line("   <fg=cyan>CURL Command:</>");
                    $this->line("   <fg=white>{$curlCommand}</>");
                    
                } catch (\Exception $e) {
                    $this->error("   ❌ Request failed: " . $e->getMessage());
                    
                    // Still show CURL for debugging
                    $curlCommand = $this->generateCurlCommand('POST', $finalUrl, $headers, $payload, $timeout);
                    $this->line("   <fg=yellow>Debug CURL:</>");
                    $this->line("   <fg=white>{$curlCommand}</>");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("   ❌ Failed to create provider instance: " . $e->getMessage());
        }
    }

    /**
     * Display responses endpoint response with formatting
     */
    private function displayResponsesResponse($response, $aiProvider, bool $includeWebSearch)
    {
        $statusCode = $response->status();
        $isSuccess = $statusCode >= 200 && $statusCode < 300;
        
        $statusIcon = $isSuccess ? '✅' : '❌';
        $statusColor = $isSuccess ? 'green' : 'red';
        
        $this->line("   {$statusIcon} <fg={$statusColor}>Status:</> {$statusCode} " . $response->reason());
        
        if ($isSuccess) {
            try {
                $rawContent = $response->body();
                $this->line("   📊 <fg=green>Response Length:</> " . strlen($rawContent) . " characters");
                
                // Try to format the response using the provider
                $formattedResponse = $aiProvider->formatResponse($response);
                
                if (isset($formattedResponse['content']['text'])) {
                    $text = $formattedResponse['content']['text'];
                    $this->line("   💬 <fg=green>Response Text:</> " . (strlen($text) > 200 ? substr($text, 0, 200) . '...' : $text));
                }
                
                if ($includeWebSearch && isset($formattedResponse['content']['groundingMetadata'])) {
                    $metadata = $formattedResponse['content']['groundingMetadata'];
                    if (isset($metadata['groundingSupports']) && is_array($metadata['groundingSupports'])) {
                        $supportCount = count($metadata['groundingSupports']);
                        $this->line("   🔍 <fg=green>Web Search Sources:</> {$supportCount} sources found");
                        
                        foreach (array_slice($metadata['groundingSupports'], 0, 3) as $i => $support) {
                            if (isset($support['url'], $support['title'])) {
                                $this->line("     " . ($i + 1) . ". <fg=cyan>{$support['title']}</> - {$support['url']}");
                            }
                        }
                        
                        if ($supportCount > 3) {
                            $this->line("     ... and " . ($supportCount - 3) . " more sources");
                        }
                    }
                }
                
                if (isset($formattedResponse['usage'])) {
                    $usage = $formattedResponse['usage'];
                    $this->line("   📈 <fg=green>Token Usage:</> " . json_encode($usage));
                }
                
                // Show raw response structure
                $this->line("   📋 <fg=gray>Raw Response Preview:</>");
                $jsonResponse = json_decode($rawContent, true);
                if ($jsonResponse && isset($jsonResponse['output']) && is_array($jsonResponse['output'])) {
                    $outputCount = count($jsonResponse['output']);
                    $this->line("     - Output items: {$outputCount}");
                    
                    foreach (array_slice($jsonResponse['output'], 0, 3) as $i => $item) {
                        if (isset($item['type'])) {
                            $this->line("       " . ($i + 1) . ". Type: {$item['type']}");
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->warn("   ⚠️  Could not format response: " . $e->getMessage());
                $this->line("   📄 <fg=gray>Raw Response:</> " . substr($response->body(), 0, 500) . '...');
            }
        } else {
            $errorBody = $response->body();
            if (strlen($errorBody) > 500) {
                $errorBody = substr($errorBody, 0, 500) . '...';
            }
            $this->line("   <fg=red>Error:</> {$errorBody}");
        }
    }

    /**
     * Build headers for request using provider-specific authentication
     */
    private function buildHeaders(ProviderSetting $provider): array
    {
        try {
            // Use AI Provider Factory for provider-specific headers
            $providerFactory = app(AIProviderFactory::class);
            $aiProvider = $providerFactory->getProviderInterface($provider->apiFormat->unique_name);
            
            return $aiProvider->getConnectionTestHeaders();
        } catch (\Exception $e) {
            $this->warn("Could not create provider instance, falling back to generic headers: " . $e->getMessage());
            
            // Fallback to generic headers
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            // Add API key if configured (generic method)
            if (!empty($provider->api_key)) {
                $headers['Authorization'] = 'Bearer ' . $provider->api_key;
            }

            return $headers;
        }
    }

    /**
     * Build URL for request with provider-specific authentication
     */
    private function buildUrl(ProviderSetting $provider, string $baseUrl): string
    {
        try {
            // Use AI Provider Factory for provider-specific URL building
            $providerFactory = app(AIProviderFactory::class);
            $aiProvider = $providerFactory->getProviderInterface($provider->apiFormat->unique_name);
            
            return $aiProvider->buildConnectionTestUrl($baseUrl);
        } catch (\Exception $e) {
            // Fallback to base URL
            return $baseUrl;
        }
    }

    /**
     * Generate CURL command
     */
    private function generateCurlCommand(string $method, string $url, array $headers, ?array $data, int $timeout): string
    {
        $curl = "curl -X {$method}";
        $curl .= " --max-time {$timeout}";
        $curl .= " --connect-timeout 5";
        
        foreach ($headers as $key => $value) {
            $curl .= " -H '{$key}: {$value}'";
        }

        if ($data && $method !== 'GET') {
            $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES);
            $curl .= " -d '{$jsonData}'";
        }

        $curl .= " '{$url}'";
        
        return $curl;
    }

    /**
     * Display response information
     */
    private function displayResponse(string $endpoint, $response, string $url, array $headers, int $timeout)
    {
        $statusCode = $response->status();
        $isSuccess = $statusCode >= 200 && $statusCode < 300;
        
        $statusIcon = $isSuccess ? '✅' : '❌';
        $statusColor = $isSuccess ? 'green' : 'red';
        
        $this->line("   {$statusIcon} <fg={$statusColor}>Status:</> {$statusCode} " . $response->reason());
        
        if ($isSuccess) {
            $responseData = $response->json();
            if ($endpoint === 'Models' && is_array($responseData)) {
                $modelCount = 0;
                
                // Count models in different response formats
                if (isset($responseData['models']) && is_array($responseData['models'])) {
                    $modelCount = count($responseData['models']);
                } elseif (isset($responseData['data']) && is_array($responseData['data'])) {
                    $modelCount = count($responseData['data']);
                } elseif (is_array($responseData)) {
                    $modelCount = count($responseData);
                }
                
                $this->line("   📊 <fg=green>Models found:</> {$modelCount}");
            }
        } else {
            $errorBody = $response->body();
            if (strlen($errorBody) > 200) {
                $errorBody = substr($errorBody, 0, 200) . '...';
            }
            $this->line("   <fg=red>Error:</> {$errorBody}");
        }

        // Always show CURL for successful requests too
        $curlCommand = $this->generateCurlCommand('GET', $url, $headers, null, $timeout);
        $this->line("   <fg=cyan>CURL Command:</>");
        $this->line("   <fg=white>{$curlCommand}</>");
    }

    /**
     * Test all available endpoints for a provider
     */
    private function testAllEndpoints(ProviderSetting $provider, bool $generateCurl, int $timeout)
    {
        $this->line("\n   🚀 <fg=blue>Testing All Available Endpoints</>");

        if (!$provider->apiFormat) {
            $this->error("   ❌ No API format configured");
            return;
        }

        $endpoints = $provider->apiFormat->activeEndpoints()->get();
        
        if ($endpoints->isEmpty()) {
            $this->warn("   ⚠️  No endpoints configured for this API format");
            return;
        }

        $this->line("   <fg=gray>Found {$endpoints->count()} endpoint(s) to test</>");

        foreach ($endpoints as $endpoint) {
            $this->testSingleEndpoint($provider, $endpoint, $generateCurl, $timeout);
        }
    }

    /**
     * Test a single endpoint
     */
    private function testSingleEndpoint(ProviderSetting $provider, $endpoint, bool $generateCurl, int $timeout)
    {
        $endpointName = $this->getEndpointDescription($endpoint->name);
        $this->line("\n      📍 <fg=magenta>Testing:</> {$endpointName} ({$endpoint->method} {$endpoint->path})");

        $baseUrl = rtrim($provider->base_url, '/');
        $url = $baseUrl . '/' . ltrim($endpoint->path, '/');
        
        // Fix double v1 issue
        if (str_ends_with($provider->base_url, '/v1') && str_starts_with($endpoint->path, '/v1')) {
            $url = rtrim($provider->base_url, '/') . substr($endpoint->path, 3);
        }
        
        $headers = $this->buildHeaders($provider);
        $finalUrl = $this->buildUrl($provider, $url);
        $method = strtoupper($endpoint->method ?? 'GET');

        if ($generateCurl) {
            $curlCommand = $this->generateCurlCommand($method, $finalUrl, $headers, null, $timeout);
            $this->line("      <fg=green>CURL:</> <fg=white>{$curlCommand}</>");
        } else {
            try {
                $httpClient = Http::timeout($timeout)->withHeaders($headers);
                
                // Only test GET endpoints to avoid side effects
                if ($method === 'GET') {
                    $response = $httpClient->get($finalUrl);
                    $this->displayEndpointResponse($response, $finalUrl);
                } else {
                    $this->line("      <fg=yellow>⚠️  Skipping {$method} endpoint (only GET endpoints are tested)</>");
                    // Still show CURL for manual testing
                    $curlCommand = $this->generateCurlCommand($method, $finalUrl, $headers, null, $timeout);
                    $this->line("      <fg=gray>CURL:</> <fg=white>{$curlCommand}</>");
                }
            } catch (\Exception $e) {
                $this->line("      <fg=red>❌ Failed:</> " . $e->getMessage());
                
                // Show CURL for debugging
                $curlCommand = $this->generateCurlCommand($method, $finalUrl, $headers, null, $timeout);
                $this->line("      <fg=gray>Debug CURL:</> <fg=white>{$curlCommand}</>");
            }
        }
    }

    /**
     * Display endpoint response (simplified version)
     */
    private function displayEndpointResponse($response, string $url)
    {
        $statusCode = $response->status();
        $isSuccess = $statusCode >= 200 && $statusCode < 300;
        
        $statusIcon = $isSuccess ? '✅' : '❌';
        $statusColor = $isSuccess ? 'green' : 'red';
        
        $this->line("      {$statusIcon} <fg={$statusColor}>Status:</> {$statusCode}");
        
        if ($isSuccess) {
            $responseData = $response->json();
            if (is_array($responseData)) {
                // Show basic response info
                if (isset($responseData['data']) && is_array($responseData['data'])) {
                    $this->line("      <fg=gray>Response:</> " . count($responseData['data']) . " items in data array");
                } elseif (count($responseData) > 0) {
                    $keys = array_keys($responseData);
                    $this->line("      <fg=gray>Response:</> " . implode(', ', array_slice($keys, 0, 3)) . (count($keys) > 3 ? '...' : ''));
                }
            }
        }
    }

    /**
     * Get human-readable description for endpoint name
     */
    private function getEndpointDescription(string $endpointName): string
    {
        return match($endpointName) {
            'models.list' => 'List Models',
            'chat.create' => 'Chat Completions',
            'chat.stream' => 'Chat Streaming',
            'completions.create' => 'Text Completions',
            'embeddings.create' => 'Create Embeddings',
            'generate.create' => 'Generate Text',
            'count_tokens' => 'Count Tokens',
            default => ucwords(str_replace(['.', '_'], ' ', $endpointName))
        };
    }

    /**
     * Show available providers
     */
    private function showAvailableProviders()
    {
        $providers = ProviderSetting::with(['apiFormat', 'apiFormat.activeEndpoints'])->select('id', 'provider_name', 'is_active', 'api_format_id')->get();
        
        if ($providers->isEmpty()) {
            $this->warn('No providers configured in the system');
            return;
        }

        $this->newLine();
        $this->line('<fg=cyan>Available Providers:</>');
        
        $tableData = [];
        foreach ($providers as $provider) {
            $tableData[] = [
                'ID' => $provider->id,
                'Name' => $provider->provider_name,
                'Status' => $provider->is_active ? '✅ Active' : '❌ Inactive',
                'Base URL' => $provider->base_url ?: 'Not configured',
            ];
        }
        
        $this->table(['ID', 'Name', 'Status', 'Base URL'], $tableData);
        
        $this->newLine();
        $this->line('<fg=yellow>Usage examples:</>');
        $this->line('  php artisan provider:test 1');
        $this->line('  php artisan provider:test ollama');
        $this->line('  php artisan provider:test --all --curl');
        $this->line('  php artisan provider:test 1 --models --curl');
        $this->line('  php artisan provider:test 1 --endpoints');
        $this->line('  php artisan provider:test --all --endpoints --curl');
        $this->line('');
        $this->line('<fg=cyan>OpenAI Responses API specific tests:</>');
        $this->line('  php artisan provider:test "OpenAI Responses API" --responses');
        $this->line('  php artisan provider:test "OpenAI Responses API" --web-search');
        $this->line('  php artisan provider:test "OpenAI Responses API" --web-search --model=gpt-5-mini --message="Search for latest AI news"');
        $this->line('  php artisan provider:test "OpenAI Responses API" --responses --curl --model=gpt-4');
    }
}
