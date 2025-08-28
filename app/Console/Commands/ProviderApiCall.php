<?php

namespace App\Console\Commands;

use App\Models\ProviderSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ProviderApiCall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'provider:call 
                            {provider : Provider ID or name}
                            {endpoint : API endpoint path (e.g., /v1/models, /api/tags)}
                            {--method=GET : HTTP method (GET, POST, PUT, DELETE)}
                            {--data= : JSON data for POST/PUT requests}
                            {--curl : Generate CURL command only, do not make request}
                            {--timeout=30 : Request timeout in seconds}
                            {--raw : Show raw response without formatting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make custom API calls to providers and generate CURL commands';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $providerInput = $this->argument('provider');
        $endpoint = $this->argument('endpoint');
        $method = strtoupper($this->option('method'));
        $dataOption = $this->option('data');
        $generateCurl = $this->option('curl');
        $timeout = (int) $this->option('timeout');
        $showRaw = $this->option('raw');

        // Validate method
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])) {
            $this->error("Invalid HTTP method: {$method}");
            return 1;
        }

        // Find provider
        $provider = $this->findProvider($providerInput);
        if (!$provider) {
            $this->error("Provider not found: {$providerInput}");
            $this->showAvailableProviders();
            return 1;
        }

        // Validate endpoint format
        $endpoint = $this->normalizeEndpoint($endpoint);

        // Parse data if provided
        $data = null;
        if ($dataOption) {
            $data = json_decode($dataOption, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Invalid JSON data: " . json_last_error_msg());
                return 1;
            }
        }

        $this->makeApiCall($provider, $endpoint, $method, $data, $generateCurl, $timeout, $showRaw);

        return 0;
    }

    /**
     * Find provider by ID or name
     */
    private function findProvider($input): ?ProviderSetting
    {
        return ProviderSetting::with('apiFormat')
            ->where('id', $input)
            ->orWhere('provider_name', 'like', "%{$input}%")
            ->first();
    }

    /**
     * Normalize endpoint path
     */
    private function normalizeEndpoint(string $endpoint): string
    {
        // Ensure endpoint starts with /
        if (!str_starts_with($endpoint, '/')) {
            $endpoint = '/' . $endpoint;
        }
        
        return $endpoint;
    }

    /**
     * Make API call to provider
     */
    private function makeApiCall(
        ProviderSetting $provider, 
        string $endpoint, 
        string $method, 
        ?array $data, 
        bool $generateCurl, 
        int $timeout,
        bool $showRaw
    ) {
        $this->line("🔍 <fg=cyan>API Call to:</> <fg=yellow>{$provider->provider_name}</>");
        $this->line("   <fg=gray>Provider ID:</> {$provider->id}");
        $this->line("   <fg=gray>API Format:</> " . ($provider->apiFormat ? $provider->apiFormat->display_name : 'None'));
        $this->line("   <fg=gray>Base URL:</> {$provider->base_url}");
        $this->line("   <fg=gray>Endpoint:</> {$endpoint}");
        $this->line("   <fg=gray>Method:</> {$method}");
        
        if ($data) {
            $this->line("   <fg=gray>Data:</> " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (!$provider->base_url) {
            $this->error("❌ No base URL configured for this provider");
            return;
        }

        $url = rtrim($provider->base_url, '/') . $endpoint;
        
        // Fix double v1 issue - if base_url ends with /v1 and endpoint starts with /v1, remove one
        if (str_ends_with($provider->base_url, '/v1') && str_starts_with($endpoint, '/v1')) {
            $url = rtrim($provider->base_url, '/') . substr($endpoint, 3); // Remove /v1 from endpoint
        }
        $headers = $this->buildHeaders($provider);

        $curlCommand = $this->generateCurlCommand($method, $url, $headers, $data, $timeout);
        
        $this->newLine();
        $this->line("<fg=green>🔧 CURL Command:</>");
        $this->line("<fg=white>{$curlCommand}</>");

        if (!$generateCurl) {
            $this->newLine();
            $this->line("<fg=blue>📡 Making API Request...</>");
            
            try {
                $httpClient = Http::timeout($timeout)->withHeaders($headers);
                
                $response = match($method) {
                    'GET' => $httpClient->get($url),
                    'POST' => $httpClient->post($url, $data ?? []),
                    'PUT' => $httpClient->put($url, $data ?? []),
                    'DELETE' => $httpClient->delete($url, $data ?? []),
                    'PATCH' => $httpClient->patch($url, $data ?? []),
                    default => throw new \InvalidArgumentException("Unsupported method: {$method}")
                };

                $this->displayResponse($response, $showRaw);

            } catch (\Exception $e) {
                $this->error("❌ Request failed: " . $e->getMessage());
                $this->newLine();
                $this->line("<fg=yellow>💡 Use the CURL command above to test manually in your terminal</>");
            }
        } else {
            $this->newLine();
            $this->line("<fg=yellow>💡 Copy the CURL command above to test manually in your terminal</>");
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
            'User-Agent' => 'HAWKI-API-Test/1.0',
        ];

        // Add API key if configured
        if (!empty($provider->api_key)) {
            $apiFormat = $provider->apiFormat ? $provider->apiFormat->unique_name : '';
            
            switch (strtolower($apiFormat)) {
                case 'openai':
                case 'openai_compatible':
                    $headers['Authorization'] = 'Bearer ' . $provider->api_key;
                    break;
                case 'anthropic':
                    $headers['x-api-key'] = $provider->api_key;
                    $headers['anthropic-version'] = '2023-06-01';
                    break;
                default:
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
        $curl .= " --connect-timeout 10";
        $curl .= " -w '\\n\\nResponse Time: %{time_total}s\\nHTTP Code: %{http_code}\\n'";
        
        foreach ($headers as $key => $value) {
            // Escape single quotes in header values
            $escapedValue = str_replace("'", "'\"'\"'", $value);
            $curl .= " -H '{$key}: {$escapedValue}'";
        }

        if ($data && !in_array($method, ['GET', 'DELETE'])) {
            $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES);
            // Escape single quotes in JSON data
            $escapedData = str_replace("'", "'\"'\"'", $jsonData);
            $curl .= " -d '{$escapedData}'";
        }

        $curl .= " '{$url}'";
        
        return $curl;
    }

    /**
     * Display response information
     */
    private function displayResponse($response, bool $showRaw)
    {
        $statusCode = $response->status();
        $isSuccess = $statusCode >= 200 && $statusCode < 300;
        
        $statusIcon = $isSuccess ? '✅' : '❌';
        $statusColor = $isSuccess ? 'green' : 'red';
        
        $this->line("   {$statusIcon} <fg={$statusColor}>HTTP Status:</> {$statusCode} " . $response->reason());
        
        // Show response headers if interesting
        $headers = $response->headers();
        if (isset($headers['content-type'][0])) {
            $this->line("   📄 <fg=gray>Content-Type:</> {$headers['content-type'][0]}");
        }
        
        // Show response body
        $body = $response->body();
        
        if (empty($body)) {
            $this->line("   📭 <fg=gray>Response:</> Empty body");
            return;
        }

        $this->newLine();
        $this->line("📋 <fg=cyan>Response Body:</>");
        
        if ($showRaw) {
            $this->line($body);
        } else {
            // Try to pretty-print JSON
            $jsonData = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $prettyJson = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $this->line($prettyJson);
                
                // Show some stats for models endpoints
                if (is_array($jsonData)) {
                    $this->newLine();
                    $this->showResponseStats($jsonData);
                }
            } else {
                // Not JSON, show raw
                $this->line($body);
            }
        }
    }

    /**
     * Show response statistics
     */
    private function showResponseStats(array $data)
    {
        $this->line("📊 <fg=cyan>Response Statistics:</>");
        
        // Count models if this looks like a models response
        if (isset($data['models']) && is_array($data['models'])) {
            $this->line("   🤖 <fg=green>Models:</> " . count($data['models']));
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $this->line("   🤖 <fg=green>Models:</> " . count($data['data']));
        } elseif (is_array($data) && !empty($data)) {
            $firstItem = reset($data);
            if (is_array($firstItem) && (isset($firstItem['id']) || isset($firstItem['name']))) {
                $this->line("   🤖 <fg=green>Models:</> " . count($data));
            }
        }
        
        // Show top-level keys
        if (is_array($data)) {
            $keys = array_keys($data);
            $this->line("   🔑 <fg=gray>Top-level keys:</> " . implode(', ', array_slice($keys, 0, 10)));
            if (count($keys) > 10) {
                $this->line("      <fg=gray>... and " . (count($keys) - 10) . " more</>");
            }
        }
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
        
        // Show examples with actual endpoints from the database
        $sampleProvider = $providers->first();
        if ($sampleProvider && $sampleProvider->apiFormat) {
            $endpoints = $sampleProvider->apiFormat->activeEndpoints()->limit(2)->get();
            
            $this->line("  php artisan provider:call {$sampleProvider->id} " . ($endpoints->first()->path ?? '/v1/models'));
            $this->line("  php artisan provider:call {$sampleProvider->provider_name} " . ($endpoints->last()->path ?? '/api/tags') . " --curl");
            
            // Show POST example if chat endpoint exists
            $chatEndpoint = $sampleProvider->apiFormat->getChatEndpoint();
            if ($chatEndpoint) {
                $this->line("  php artisan provider:call {$sampleProvider->id} {$chatEndpoint->path} --method=POST --data='{\"messages\":[{\"role\":\"user\",\"content\":\"Hello!\"}]}'");
            }
        } else {
            $this->line('  php artisan provider:call 1 /v1/models');
            $this->line('  php artisan provider:call ollama /api/tags --curl');
            $this->line('  php artisan provider:call openai /v1/chat/completions --method=POST --data=\'{"model":"gpt-3.5-turbo","messages":[{"role":"user","content":"Hello!"}]}\'');
        }
        
        $this->line('  php artisan provider:call 1 /v1/models --raw');
    }
}
