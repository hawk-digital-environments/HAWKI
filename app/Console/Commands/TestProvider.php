<?php

namespace App\Console\Commands;

use App\Models\ProviderSetting;
use App\Services\Settings\ModelSettingsService;
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
                            {--timeout=10 : Request timeout in seconds}';

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
        $testPing = $this->option('ping') || (!$testModels && !$testEndpoints); // Default to ping
        $timeout = (int) $this->option('timeout');

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
            $this->testProvider($provider, $generateCurl, $testModels, $testPing, $testEndpoints, $timeout);
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
    private function testProvider(ProviderSetting $provider, bool $generateCurl, bool $testModels, bool $testPing, bool $testEndpoints, int $timeout)
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

        // Test ping endpoint
        if ($testPing) {
            $this->testPingEndpoint($provider, $generateCurl, $timeout);
        }

        // Test models endpoint
        if ($testModels) {
            $this->testModelsEndpoint($provider, $generateCurl, $timeout);
        }

        // Test all available endpoints
        if ($testEndpoints) {
            $this->testAllEndpoints($provider, $generateCurl, $timeout);
        }
    }

    /**
     * Test ping endpoint
     */
    private function testPingEndpoint(ProviderSetting $provider, bool $generateCurl, int $timeout)
    {
        $this->line("\n   📡 <fg=blue>Testing Base URL</>");
        
        $url = rtrim($provider->base_url, '/');
        $headers = $this->buildHeaders($provider);

        if ($generateCurl) {
            $curlCommand = $this->generateCurlCommand('GET', $url, $headers, null, $timeout);
            $this->line("   <fg=green>CURL Command:</>");
            $this->line("   <fg=white>{$curlCommand}</>");
        } else {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->get($url);

                $this->displayResponse('Ping', $response, $url, $headers, $timeout);
            } catch (\Exception $e) {
                $this->error("   ❌ Request failed: " . $e->getMessage());
                
                // Still show CURL for debugging
                $curlCommand = $this->generateCurlCommand('GET', $url, $headers, null, $timeout);
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

        if ($generateCurl) {
            $curlCommand = $this->generateCurlCommand('GET', $url, $headers, null, $timeout);
            $this->line("   <fg=green>CURL Command:</>");
            $this->line("   <fg=white>{$curlCommand}</>");
        } else {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->get($url);

                $this->displayResponse('Models', $response, $url, $headers, $timeout);
            } catch (\Exception $e) {
                $this->error("   ❌ Request failed: " . $e->getMessage());
                
                // Still show CURL for debugging
                $curlCommand = $this->generateCurlCommand('GET', $url, $headers, null, $timeout);
                $this->line("   <fg=yellow>Debug CURL:</>");
                $this->line("   <fg=white>{$curlCommand}</>");
            }
        }
    }

    /**
     * Build headers for request
     */
    private function buildHeaders(ProviderSetting $provider): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Add API key if configured
        if (!empty($provider->api_key)) {
            // Different providers use different header formats
            $apiFormat = $provider->apiFormat ? $provider->apiFormat->unique_name : '';
            
            switch (strtolower($apiFormat)) {
                case 'openai':
                case 'openai_compatible':
                    $headers['Authorization'] = 'Bearer ' . $provider->api_key;
                    break;
                default:
                    // Generic authorization header
                    $headers['Authorization'] = 'Bearer ' . $provider->api_key;
                    break;
            }
        }

        return $headers;
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
        $method = strtoupper($endpoint->method ?? 'GET');

        if ($generateCurl) {
            $curlCommand = $this->generateCurlCommand($method, $url, $headers, null, $timeout);
            $this->line("      <fg=green>CURL:</> <fg=white>{$curlCommand}</>");
        } else {
            try {
                $httpClient = Http::timeout($timeout)->withHeaders($headers);
                
                // Only test GET endpoints to avoid side effects
                if ($method === 'GET') {
                    $response = $httpClient->get($url);
                    $this->displayEndpointResponse($response, $url);
                } else {
                    $this->line("      <fg=yellow>⚠️  Skipping {$method} endpoint (only GET endpoints are tested)</>");
                    // Still show CURL for manual testing
                    $curlCommand = $this->generateCurlCommand($method, $url, $headers, null, $timeout);
                    $this->line("      <fg=gray>CURL:</> <fg=white>{$curlCommand}</>");
                }
            } catch (\Exception $e) {
                $this->line("      <fg=red>❌ Failed:</> " . $e->getMessage());
                
                // Show CURL for debugging
                $curlCommand = $this->generateCurlCommand($method, $url, $headers, null, $timeout);
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
    }
}
