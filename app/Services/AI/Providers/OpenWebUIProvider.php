<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\LanguageModel;
use App\Models\ProviderSetting;

class OpenWebUIProvider extends OpenAIProvider
{
    /**
     * Constructor for OpenWebUIProvider
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerId = 'openWebUi'; // Explizites Setzen der providerId
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
     * Format the raw payload for OpenAI API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];
        
        // Handle special cases for specific models
        $messages = $this->handleModelSpecificFormatting($modelId, $messages);
        
        // Format messages for OpenAI
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content']['text']
            ];
        }
        
        // Build payload with common parameters
        $payload = [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->supportsStreaming($modelId),
        ];
        
        // Add optional parameters if present in the raw payload
        if (isset($rawPayload['temperature'])) {
            $payload['temperature'] = $rawPayload['temperature'];
        }
        
        if (isset($rawPayload['top_p'])) {
            $payload['top_p'] = $rawPayload['top_p'];
        }
        
        if (isset($rawPayload['frequency_penalty'])) {
            $payload['frequency_penalty'] = $rawPayload['frequency_penalty'];
        }
        
        if (isset($rawPayload['presence_penalty'])) {
            $payload['presence_penalty'] = $rawPayload['presence_penalty'];
        }
        
        return $payload;
    }
    
    /**
     * Format the complete response from OpenWebUI
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);

        if (containsKey($jsonContent, 'content')){
            $content = getValueForKey($jsonContent, 'content');
        }
               
        return [
            'content' => [
                'text' => $content,
            ],
            'usage' => $this->extractUsage($jsonContent)
        ];

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
        if (!empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($jsonChunk);
        }
        
        // Extract content if available
        //if (isset($jsonChunk['choices'][0]['delta']['content'])) {
        //    $content = $jsonChunk['choices'][0]['delta']['content'];
        //}
        if ($this->containsKey($jsonChunk, 'content')){
            $content = $this->getValueForKey($jsonChunk, 'content');
        }
        
        return [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage
        ];
    }
    
    protected function containsKey($obj, $targetKey)
    {
        if (!is_array($obj)) {
            return false;
        }
        if (array_key_exists($targetKey, $obj)) {
            return true;
        }
        foreach ($obj as $value) {
            if ($this->containsKey($value, $targetKey)) {
                return true;
            }
        }
        return false;
    }

    protected function getValueForKey($obj, $targetKey)
    {
        if (!is_array($obj)) {
            return null;
        }
        if (array_key_exists($targetKey, $obj)) {
            return $obj[$targetKey];
        }
        foreach ($obj as $value) {
            $result = $this->getValueForKey($value, $targetKey);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
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
            'prompt_token/s' =>  $data['usage']['prompt_token/s'],
            'response_token/s' =>  $data['usage']['response_token/s'],
        ];    
    }
    
    /**
     * Make a non-streaming request to the OpenWebUI API
     *
     * @param array $payload The formatted payload
     * @return mixed The response
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Use the OpenAI implementation, but with OpenWebUI API URL
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
     * Make a streaming request to the OpenWebUI API
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
     * Handle special formatting requirements for specific models
     *
     * @param string $modelId
     * @param array $messages
     * @return array
     */
    protected function handleModelSpecificFormatting(string $modelId, array $messages): array
    {
        // Special case for o1-mini: convert system to user
        if ($modelId === 'o1-mini' && isset($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['role'] = 'user';
        }
        
        return $messages;
    }

    /**
     * Ping the API to check model status
     *
     * @return array
     * @throws \Exception
     */
    public function getModelsStatus(): array
    {
        $response = $this->pingProvider();
        if (!$response) {
            return [];
        }
        
        $referenceList = json_decode($response, true);
        if (!is_array($referenceList)) {
            return [];
        }
        
        // Get models from the database instead of from the configuration
        $providerId = $this->getProviderId();
        $provider = ProviderSetting::with('apiFormat')
            ->where(function($query) use ($providerId) {
                $query->where('provider_name', $providerId)
                      ->orWhereHas('apiFormat', function($subQuery) use ($providerId) {
                          $subQuery->where('unique_name', $providerId);
                      });
            })
            ->where('is_active', true)
            ->first();
            
        if (!$provider) {
            return [];
        }
        
        $dbModels = LanguageModel::where('provider_id', $provider->id)
            ->where('is_active', true)
            ->get();
        
        $models = [];
        foreach ($dbModels as $model) {
            $models[] = [
                'id' => $model->model_id,
                'label' => $model->label,
                'streamable' => $model->streamable,
                'api_format' => $provider->apiFormat?->unique_name ?? $provider->provider_name,
                'provider_name' => $provider->provider_name
            ];
        }
    
        // Determine model status from the reference list
        foreach ($models as &$model) {
            $found = false;
            
            // Search for the model in the reference list
            foreach ($referenceList as $reference) {
                if (isset($reference['id']) && $reference['id'] === $model['id']) {
                    $model['status'] = 'ready'; // OpenWebUI defaults to 'ready'
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $model['status'] = 'unknown';
            }
        }
    
        return $models;
    }
    
    /**
     * Ping OpenWebUI API to check status
     *
     * @return string|null
     */
    protected function pingProvider(): ?string
    {
        // Get the models endpoint URL from database configuration
        $url = $this->getModelsEndpointUrl();
        $apiKey = $this->config['api_key'];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(5)
                ->get($url);

            return $response;
        } catch (\Exception $e) {
            Log::error("Error pinging OpenWebUI provider: " . $e->getMessage());
            return null;
        }
    }
}