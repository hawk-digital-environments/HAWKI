<?php

declare(strict_types=1);

namespace App\Orchid\Traits;

use App\Models\ProviderSetting;
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
            
            // Simple connection test - this could be enhanced with actual API testing
            $response = @get_headers($testUrl);
            
            $connectionEndTime = microtime(true);
            $responseTime = round(($connectionEndTime - $connectionStartTime) * 1000, 2);
            $totalTime = round(($connectionEndTime - $startTime) * 1000, 2);
            
            $logData['timing'] = [
                'response_time_ms' => $responseTime,
                'total_time_ms' => $totalTime,
            ];
            
            if ($response !== false) {
                $logData['result'] = 'success';
                $logData['response'] = [
                    'headers_count' => count($response),
                    'first_header' => $response[0] ?? null,
                ];
                
                Log::info("Provider connection test successful - {$provider->provider_name}", $logData);
                
                Toast::success("Connection test successful for provider '{$provider->provider_name}' at {$testUrl} (Response time: {$responseTime}ms).");
            } else {
                $logData['result'] = 'failed_no_response';
                $logData['errors'][] = 'No response received from endpoint';
                $logData['last_error'] = error_get_last();
                
                Log::warning("Provider connection test failed - {$provider->provider_name} no response", $logData);
                
                Toast::error("Connection test failed for provider '{$provider->provider_name}' at {$testUrl}.");
            }
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $logData['result'] = 'failed_exception';
            $logData['timing']['total_time_ms'] = round(($endTime - $startTime) * 1000, 2);
            $logData['errors'][] = $e->getMessage();
            $logData['exception'] = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            
            Log::error("Provider connection test exception - {$provider->provider_name}", $logData);
            
            Toast::error("Connection test error for provider '{$provider->provider_name}': " . $e->getMessage());
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
