<?php

namespace App\Console\Commands;

use App\Models\ProviderSetting;
use Illuminate\Console\Command;

class ProviderCurl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'provider:curl 
                            {provider? : Provider ID or name}
                            {--endpoint=/v1/models : API endpoint to test}
                            {--method=GET : HTTP method}
                            {--list : List all providers and their CURL commands}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate ready-to-use CURL commands for provider testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $providerInput = $this->argument('provider');
        $endpoint = $this->option('endpoint');
        $method = strtoupper($this->option('method'));
        $listAll = $this->option('list');

        if ($listAll || !$providerInput) {
            $this->showAllProviderCurls($endpoint, $method);
            return 0;
        }

        $provider = $this->findProvider($providerInput);
        if (!$provider) {
            $this->error("Provider not found: {$providerInput}");
            return 1;
        }

        $this->showProviderCurl($provider, $endpoint, $method);
        return 0;
    }

    /**
     * Find provider by ID or name
     */
    private function findProvider($input): ?ProviderSetting
    {
        return ProviderSetting::where('id', $input)
            ->orWhere('provider_name', 'like', "%{$input}%")
            ->first();
    }

    /**
     * Show CURL commands for all providers
     */
    private function showAllProviderCurls(string $endpoint, string $method)
    {
        $providers = ProviderSetting::with(['apiFormat', 'apiFormat.activeEndpoints'])
            ->where('is_active', true)
            ->whereHas('apiFormat', function($q) {
                $q->whereNotNull('base_url');
            })
            ->orderBy('provider_name')
            ->get();

        if ($providers->isEmpty()) {
            $this->warn('No active providers with ping URLs found');
            return;
        }

        $this->line("<fg=cyan>🔧 CURL Commands for All Active Providers</>");
        $this->line("<fg=gray>Endpoint:</> {$endpoint}");
        $this->line("<fg=gray>Method:</> {$method}");
        $this->newLine();

        foreach ($providers as $provider) {
            $this->showProviderCurl($provider, $endpoint, $method, false);
            $this->newLine();
        }

        $this->line("<fg=yellow>💡 Tips:</>");
        $this->line("  - Copy and paste any command directly into your terminal");
        $this->line("  - Add -v flag for verbose output: curl -v ...");
        $this->line("  - Add -s flag for silent mode: curl -s ...");
        $this->line("  - Add | jq for pretty JSON formatting: curl ... | jq");
    }

    /**
     * Show CURL command for specific provider
     */
    private function showProviderCurl(ProviderSetting $provider, string $endpoint, string $method, bool $showHeader = true)
    {
        if ($showHeader) {
            $this->line("<fg=cyan>🔧 CURL Command for:</> <fg=yellow>{$provider->provider_name}</>");
            $this->line("<fg=gray>Provider ID:</> {$provider->id}");
            $this->line("<fg=gray>Base URL:</> {$provider->base_url}");
            $this->newLine();
        }

        if (!$provider->base_url) {
            $this->error("❌ No base URL configured for {$provider->provider_name}");
            return;
        }

        $url = rtrim($provider->base_url, '/') . '/' . ltrim($endpoint, '/');
        
        // Fix double v1 issue - if base_url ends with /v1 and endpoint starts with /v1, remove one
        if (str_ends_with($provider->base_url, '/v1') && str_starts_with($endpoint, '/v1')) {
            $url = rtrim($provider->base_url, '/') . substr($endpoint, 3); // Remove /v1 from endpoint
        }
        $curlCommand = $this->generateCurlCommand($method, $url, $provider);
        
        if (!$showHeader) {
            $apiKeyStatus = !empty($provider->api_key) ? '' : ' # No API key configured';
            $this->line("<fg=yellow># {$provider->provider_name}{$apiKeyStatus}</>");
        }
        
        $this->line("<fg=green>{$curlCommand}</>");

        if ($showHeader) {
            if (empty($provider->api_key)) {
                $this->line("<fg=gray>💡 Note: No API key configured for this provider</>");
            }
            $this->newLine();
            $this->line("<fg=yellow>💡 Usage variations:</>");
            
            // Show available endpoints for this provider
            $availableEndpoints = $this->getAvailableEndpoints($provider);
            foreach ($availableEndpoints as $desc => $ep) {
                $epUrl = rtrim($provider->base_url, '/') . '/' . ltrim($ep, '/');
                
                // Fix double v1 issue for common endpoints too
                if (str_ends_with($provider->base_url, '/v1') && str_starts_with($ep, '/v1')) {
                    $epUrl = rtrim($provider->base_url, '/') . substr($ep, 3);
                }
                
                $epCurl = $this->generateCurlCommand('GET', $epUrl, $provider);
                $this->line("<fg=gray># {$desc}</>");
                $this->line("<fg=white>{$epCurl}</>");
                $this->newLine();
            }
        }
    }

    /**
     * Get available endpoints for provider from database
     */
    private function getAvailableEndpoints(ProviderSetting $provider): array
    {
        if (!$provider->apiFormat) {
            return [
                'Default Models' => '/v1/models',
                'Health Check' => '/health',
            ];
        }

        $endpoints = [];
        
        // Get active endpoints from database
        $dbEndpoints = $provider->apiFormat->activeEndpoints()->get();
        
        foreach ($dbEndpoints as $endpoint) {
            $description = $this->getEndpointDescription($endpoint->name);
            $endpoints[$description] = $endpoint->path;
        }

        // If no endpoints found, provide some defaults based on API format
        if (empty($endpoints)) {
            $apiFormat = strtolower($provider->apiFormat->unique_name);
            
            $endpoints = match($apiFormat) {
                'ollama-api' => [
                    'List Models' => '/api/tags',
                    'Health Check' => '/api/health',
                ],
                'anthropic-api' => [
                    'List Models' => '/models',
                    'Health Check' => '/health',
                ],
                default => [
                    'List Models' => '/models',
                    'Health Check' => '/health',
                ]
            };
        }

        return $endpoints;
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
     * Generate CURL command
     */
    private function generateCurlCommand(string $method, string $url, ProviderSetting $provider): string
    {
        $curl = "curl -X {$method}";
        $curl .= " --max-time 30";
        $curl .= " --connect-timeout 10";
        
        // Add common headers
        $curl .= " -H 'Content-Type: application/json'";
        $curl .= " -H 'Accept: application/json'";
        
        // Add API key if configured
        if (!empty($provider->api_key)) {
            $apiFormat = $provider->apiFormat ? $provider->apiFormat->unique_name : '';
            
            switch (strtolower($apiFormat)) {
                case 'openai':
                case 'openai_compatible':
                    $curl .= " -H 'Authorization: Bearer {$provider->api_key}'";
                    break;
                case 'anthropic':
                    $curl .= " -H 'x-api-key: {$provider->api_key}'";
                    $curl .= " -H 'anthropic-version: 2023-06-01'";
                    break;
                default:
                    $curl .= " -H 'Authorization: Bearer {$provider->api_key}'";
                    break;
            }
        }
        
        $curl .= " '{$url}'";
        
        return $curl;
    }
}
