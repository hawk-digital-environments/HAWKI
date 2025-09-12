<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\LanguageModel;
use App\Models\ProviderSetting;

class GWDGProvider extends OpenAIProvider
{
    /**
     * Constructor for GWDGProvider
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        // Call parent constructor first
        parent::__construct($config);
        // Override the providerId after parent sets it
        $this->providerId = 'gwdg'; // Explizites Setzen der providerId
    }
    
    /**
     * Get the provider settings from database
     *
     * @return ProviderSetting|null
     */
    protected function getProviderFromDatabase(): ?ProviderSetting
    {
        return ProviderSetting::where('provider_name', $this->providerId)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get the chat endpoint URL from the provider's API format configuration
     *
     * @return string
     */
    protected function getChatEndpointUrl(): string
    {
        $provider = $this->getProviderFromDatabase();
        return $provider ? $provider->chat_url : $this->config['base_url'] ?? '';
    }
    
    /**
     * Get the models endpoint URL from the provider's API format configuration  
     *
     * @return string
     */
    protected function getModelsEndpointUrl(): string
    {
        $provider = $this->getProviderFromDatabase();
        return $provider ? $provider->ping_url : $this->config['ping_url'] ?? '';
    }
    
    /**
     * Format the raw payload for GWDG API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        // Get standard OpenAI formatting
        $payload = parent::formatPayload($rawPayload);
        
        // For GWDG, we might want to add any GWDG-specific parameters
        // Currently using the same format as OpenAI, but this can be customized
        
        return $payload;
    }

/**
     * Format a single chunk from a streaming response
     *
     * @param string $chunk
     * @return array
     */
     public function formatStreamChunk(string $chunk): array
    {
        $jsonChunk = json_decode($chunk, true);
        $content = '';
        $isDone = false;
        $usage = null;
        
        // Check for the finish_reason flag
        if (isset($jsonChunk['choices'][0]['finish_reason']) && $jsonChunk['choices'][0]['finish_reason'] === 'stop') {
            $isDone = true;
        }
        
        // Extract usage data if available
        // Mistral Fix: Additional check for empty choices array
        if (!empty($jsonChunk['usage']) && empty($jsonChunk['choices'])) {
            $usage = $this->extractUsage($jsonChunk);
        }
        
        // Extract content if available
        if (isset($jsonChunk['choices'][0]['delta']['content'])) {
            $content = $jsonChunk['choices'][0]['delta']['content'];
        }
        
        return [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage
        ];
    }
    /**
     * Extract usage information from OpenAI response
     *
     * @param array $data
     * @return array|null
     */
     protected function extractUsage(array $data): ?array
    {
        if (empty($data['usage'])) {
            return null;
        }
        //Log::info($data['usage']);
        return [
            'prompt_tokens' => $data['usage']['prompt_tokens'],
            'completion_tokens' => $data['usage']['completion_tokens'],
        ];    
    }
    /**
     * Handle special formatting requirements for specific GWDG models
     *
     * @param string $modelId
     * @param array $messages
     * @return array
     */
    protected function handleModelSpecificFormatting(string $modelId, array $messages): array
    {
        // Special case for mixtral: convert system to user
        if ($modelId === 'mixtral-8x7b-instruct' && isset($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['role'] = 'user';
        }
        
        return $messages;
    }
    
    /**
     * Make a non-streaming request to the GWDG API
     *
     * @param array $payload The formatted payload
     * @return mixed The response
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Use the OpenAI implementation, but with GWDG API URL
        $payload['stream'] = false;
        
        // Get the chat endpoint URL from database configuration
        $chatUrl = $this->getChatEndpointUrl();
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $chatUrl);
        
        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders());
        
        // Execute the request
        $response = curl_exec($ch);
        
        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error: ' . curl_error($ch);
            curl_close($ch);
            return response()->json(['error' => $error], 500);
        }
        
        curl_close($ch);
        
        return response($response)->header('Content-Type', 'application/json');
    }
    
    /**
     * Make a streaming request to the GWDG API
     *
     * @param array $payload The formatted payload
     * @param callable $streamCallback Callback for streaming responses
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Ensure stream is set to true
        $payload['stream'] = true;
        
        set_time_limit(120);
        
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        
        // Get the chat endpoint URL from database configuration
        $chatUrl = $this->getChatEndpointUrl();
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $chatUrl);
        
        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders(true));
        
        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, $streamCallback);
        
        // Execute the cURL session
        curl_exec($ch);
        
        // Handle errors
        if (curl_errno($ch)) {
            $streamCallback('Error: ' . curl_error($ch));
            if (ob_get_length()) {
                ob_flush();
            }
            flush();
        }
        
        curl_close($ch);
        
        // Flush any remaining data
        if (ob_get_length()) {
            ob_flush();
        }
        flush();
    }






    /**
     * Ping the API to check model status
     *
     * @param string $modelId
     * @return string
     * @throws \Exception
     */
    public function getModelsStatus(): array
    {
        $referenceList = $this->checkAllModelsStatus();
        $models = $this->config['models'];
    
        // Index the referenceList by IDs for O(1) access
        $referenceMap = [];
        foreach ($referenceList as $reference) {
            $referenceMap[$reference['id']] = $reference['status'];
        }
    
        // Update each model with the status from the reference map if it exists
        foreach ($models as &$model) {
            if (isset($referenceMap[$model['id']])) {
                $model['status'] = $referenceMap[$model['id']];
            } else {
                $model['status'] = 'unknown'; // or any default value if not found
            }
        }
        Log::info($models);
        return $models;
    }

    // /**
    // * Ping the API to check status of all models
    // */
    public function checkAllModelsStatus(): array
    {
        $response = $this->pingProvider();
        $referenceList = json_decode($response, true)['data'];
        return $referenceList;
    }


    /**
     * Get status of all models
     *
     * @return string
     */
    protected function pingProvider(): string
    {        
        // Get the models endpoint URL from database configuration
        $url = $this->getModelsEndpointUrl();
        $apiKey = $this->config['api_key'];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(5) // Set a short timeout
                ->get($url);

            return $response->body();
        } catch (\Exception $e) {
            return '{"data":[]}'; // Return empty data structure as string
        }
    }
}