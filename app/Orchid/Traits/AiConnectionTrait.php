<?php

declare(strict_types=1);

namespace App\Orchid\Traits;

use App\Models\ProviderSetting;
use App\Services\AI\AIProviderFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Orchid\Support\Facades\Toast;

trait AiConnectionTrait
{
    /**
     * Test connection to an AI provider.
     * 
     * This method provides a general framework for testing connections to various AI providers.
     * It can be extended to support different types of connection tests (provider endpoints, 
     * model availability, API authentication, etc.).
     */
    public function testConnection(ProviderSetting $provider)
    {
        $startTime = microtime(true);
        $logData = [
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->provider_name,
                'is_active' => $provider->is_active,
                'api_format_id' => $provider->api_format_id,
            ],
            'test_metadata' => [
                'tested_by' => auth()->id(),
                'started_at' => now()->toISOString(),
                'test_type' => 'provider_endpoint',
            ],
            'result' => null,
            'timing' => [],
            'errors' => [],
        ];

        if (!$provider->is_active) {
            $logData['result'] = 'skipped_inactive';
            $logData['errors'][] = 'Provider is inactive';
            
            Log::warning("Provider connection test skipped - {$provider->provider_name} is inactive", $logData);
            
            Toast::warning("Provider '{$provider->provider_name}' is currently inactive.");
            return;
        }
        
        // Load API format relationship
        $provider->load('apiFormat');
        
        if (!$provider->apiFormat) {
            $logData['result'] = 'failed_no_api_format';
            $logData['errors'][] = 'No API format configured';
            
            Log::warning("Provider connection test failed - {$provider->provider_name} has no API format", $logData);
            
            Toast::warning("No API format configured for provider '{$provider->provider_name}'.");
            return;
        }

        $logData['api_format'] = [
            'id' => $provider->apiFormat->id,
            'name' => $provider->apiFormat->display_name,
            'base_url' => $provider->apiFormat->base_url,
        ];
        
        // Get models endpoint from API format
        $modelsEndpoint = $provider->apiFormat->getModelsEndpoint();
        if (!$modelsEndpoint) {
            $logData['result'] = 'failed_no_endpoint';
            $logData['errors'][] = 'No models endpoint available';
            $logData['api_format']['available_endpoints'] = $provider->apiFormat->endpoints->pluck('name')->toArray();
            
            Log::warning("Provider connection test failed - {$provider->provider_name} has no models endpoint", $logData);
            
            Toast::warning("No models endpoint available for API format '{$provider->apiFormat->display_name}'.");
            return;
        }

        $logData['endpoint'] = [
            'id' => $modelsEndpoint->id,
            'name' => $modelsEndpoint->name,
            'path' => $modelsEndpoint->path,
            'method' => $modelsEndpoint->method,
            'is_active' => $modelsEndpoint->is_active,
        ];
        
        $testUrl = $modelsEndpoint->full_url;
        if (empty($testUrl)) {
            $logData['result'] = 'failed_url_construction';
            $logData['errors'][] = 'Cannot construct test URL';
            
            Log::error("Provider connection test failed - {$provider->provider_name} URL construction error", $logData);
            
            Toast::warning("Cannot construct test URL for provider '{$provider->provider_name}'.");
            return;
        }

        $logData['test_url'] = $testUrl;
        
        try {
            $connectionStartTime = microtime(true);
            
            // Use AI Provider Factory to get provider-specific configuration
            $providerFactory = app(AIProviderFactory::class);
            
            try {
                $aiProvider = $providerFactory->getProviderInterface($provider->apiFormat->unique_name);
            } catch (\Exception $e) {
                $logData['result'] = 'failed_provider_creation';
                $logData['errors'][] = 'Could not create AI provider: ' . $e->getMessage();
                
                Log::warning("Provider connection test failed - {$provider->provider_name} provider creation error", $logData);
                
                Toast::error("Could not create provider instance for '{$provider->provider_name}': " . $e->getMessage());
                return;
            }
            
            // Get provider-specific headers and URL
            $headers = $aiProvider->getConnectionTestHeaders();
            $requestUrl = $aiProvider->buildConnectionTestUrl($testUrl);
            
            $logData['request_headers'] = array_keys($headers);
            $logData['final_url'] = $requestUrl;
            
            // Make HTTP request with timeout
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withHeaders($headers)
                ->get($requestUrl);
            
            $connectionEndTime = microtime(true);
            $responseTime = round(($connectionEndTime - $connectionStartTime) * 1000, 2);
            $totalTime = round(($connectionEndTime - $startTime) * 1000, 2);
            
            $logData['timing'] = [
                'response_time_ms' => $responseTime,
                'total_time_ms' => $totalTime,
            ];
            
            $statusCode = $response->status();
            $isSuccess = $statusCode >= 200 && $statusCode < 400;
            
            $logData['response'] = [
                'status_code' => $statusCode,
                'status_text' => $response->reason(),
                'has_body' => !empty($response->body()),
            ];
            
            if ($isSuccess) {
                // Successful connection
                $logData['result'] = 'success';
                
                Log::info("Provider connection test successful - {$provider->provider_name}", $logData);
                
                Toast::success("Connection test successful for provider '{$provider->provider_name}' at {$testUrl} (HTTP {$statusCode}, Response time: {$responseTime}ms).");
            } else {
                // HTTP error
                $logData['result'] = 'failed_http_error';
                $errorBody = $response->body();
                if (strlen($errorBody) > 200) {
                    $errorBody = substr($errorBody, 0, 200) . '...';
                }
                $logData['errors'][] = "HTTP {$statusCode}: {$errorBody}";
                
                Log::warning("Provider connection test failed - {$provider->provider_name} HTTP error", $logData);
                
                Toast::error("Connection test failed for provider '{$provider->provider_name}' at {$testUrl} (HTTP {$statusCode}).");
            }
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $logData['result'] = 'failed_exception';
            $logData['timing']['total_time_ms'] = round(($endTime - $startTime) * 1000, 2);
            $logData['errors'][] = $e->getMessage();
            $logData['exception'] = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'type' => get_class($e),
            ];
            
            Log::error("Provider connection test exception - {$provider->provider_name}", $logData);
            
            // More user-friendly error messages for common HTTP client exceptions
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'Connection timed out')) {
                $errorMessage = 'Connection timeout - please check if the provider URL is accessible';
            } elseif (str_contains($errorMessage, 'Could not resolve host')) {
                $errorMessage = 'Could not resolve hostname - please check the provider URL';
            }
            
            Toast::error("Connection test error for provider '{$provider->provider_name}': " . $errorMessage);
        }
    }

    /**
     * Test a specific model's availability (future implementation).
     * 
     * This method can be implemented to test if a specific AI model is available
     * and responding correctly.
     */
    // public function testModelConnection($model) { ... }

    /**
     * Test AI provider authentication (future implementation).
     * 
     * This method can be implemented to verify API key validity and authentication
     * without making actual requests to AI endpoints.
     */
    // public function testProviderAuthentication($provider) { ... }

    /**
     * Test AI model response quality (future implementation).
     * 
     * This method can be implemented to send a test prompt and verify the quality
     * and format of the AI response.
     */
    // public function testModelResponse($model, $testPrompt = null) { ... }
}
