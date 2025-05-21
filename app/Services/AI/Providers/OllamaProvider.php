<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\LanguageModel;
use App\Models\ProviderSetting;

class OllamaProvider extends BaseAIModelProvider
{
    /**
     * Constructor for OllamaProvider
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerId = 'ollama'; // Explicitly set the providerId
    }
    
    /**
     * Format the raw payload for Ollama API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];
        
        // Format messages for Ollama
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content']['text']
            ];
        }
        
        return [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->supportsStreaming($modelId),
        ];
    }
    
    /**
     * Format the complete response from Ollama
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);
        
        // Extract content based on Ollama's response format
        $content = $jsonContent['message']['content'] ?? '';
        
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
        
        // Extract content based on Ollama's streaming format
        if (isset($jsonChunk['message']['content'])) {
            $content = $jsonChunk['message']['content'];
        }
        
        // Check if this is the final chunk
        if (isset($jsonChunk['done']) && $jsonChunk['done'] === true) {
            $isDone = true;
            
            // Extract usage if available in the final chunk
            if (isset($jsonChunk['eval_count']) && isset($jsonChunk['prompt_eval_count'])) {
                $usage = $this->extractUsage($jsonChunk);
            }
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
     * Extract usage information from Ollama response
     *
     * @param array $data
     * @return array|null
     */
    protected function extractUsage(array $data): ?array
    {
        if (!isset($data['eval_count']) || !isset($data['prompt_eval_count'])) {
            return null;
        }
        
        return [
            'prompt_tokens' => $data['prompt_eval_count'],
            'completion_tokens' => $data['prompt_eval_count'] - $data['eval_count'],
        ];
    }
    
    /**
     * Make a non-streaming request to the Ollama API
     *
     * @param array $payload
     * @return mixed
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Ensure stream is set to false
        $payload['stream'] = false;
        
        // Initialize cURL with the base_url from database config
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['base_url']);
        
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
     * Make a streaming request to the Ollama API
     *
     * @param array $payload
     * @param callable $streamCallback
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Implementation of streaming request for Ollama
        // Similar to OpenAI implementation but adapted for Ollama's API
        
        // Ensure stream is set to true
        $payload['stream'] = true;
        
        set_time_limit(120);
        
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        
        // Initialize cURL with the base_url from database config
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['base_url']);
        
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
     * @return array
     */
    public function getModelsStatus(): array
    {
        $response = $this->pingProvider();
        
        // If no response or faulty response, return empty array
        if (!$response) {
            return [];
        }
        
        // Parse reference list from API response
        $referenceList = json_decode($response, true);
        if (!is_array($referenceList)) {
            return [];
        }
        
        // Get models from the database instead of from the configuration
        $providerId = $this->getProviderId();
        $provider = ProviderSetting::where('provider_name', $providerId)
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
            $modelData = [
                'id' => $model->model_id,
                'label' => $model->label,
                'streamable' => $model->streamable,
                'provider' => $providerId
            ];
            
            // Determine model status from the API response
            foreach ($referenceList as $reference) {
                if (isset($reference['name']) && $reference['name'] === $model->model_id) {
                    $modelData['status'] = 'ready';
                    break;
                }
            }
            
            // If no status found, mark as 'unknown'
            if (!isset($modelData['status'])) {
                $modelData['status'] = 'unknown';
            }
            
            $models[] = $modelData;
        }
        
        return $models;
    }
    
    /**
     * Ping the Ollama API to check available models
     *
     * @return string|null
     */
    protected function pingProvider(): ?string
    {
        Log::info('pingProvider: Ollama');
        $url = $this->config['ping_url'];
        
        try {
            // Ollama API might not require an API key
            $response = Http::timeout(5)->get($url);
            return $response;
        } catch (\Exception $e) {
            Log::error("Error pinging Ollama provider: " . $e->getMessage());
            return null;
        }
    }
}