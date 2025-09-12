<?php

namespace App\Services\AI\Providers;

use App\Models\LanguageModel;
use App\Models\ProviderSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HAWKIProvider extends BaseAIModelProvider
{
    /**
     * Constructor for HAWKIProvider
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerId = 'hawki'; // Explicitly set the providerId
    }

    /**
     * Get the chat endpoint URL from the provider's API format configuration
     */
    protected function getChatEndpointUrl(): string
    {
        $provider = $this->getProviderFromDatabase();

        return $provider ? $provider->chat_url : $this->config['base_url'] ?? '';
    }

    /**
     * Get the models endpoint URL from the provider's API format configuration
     */
    protected function getModelsEndpointUrl(): string
    {
        $provider = $this->getProviderFromDatabase();

        return $provider ? $provider->ping_url : $this->config['ping_url'] ?? '';
    }

    /**
     * Get the provider settings from database
     */
    protected function getProviderFromDatabase(): ?ProviderSetting
    {
        return ProviderSetting::where('provider_name', $this->providerId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Format the raw payload for Ollama API
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
                'content' => $message['content']['text'],
            ];
        }
        Log::info('stream support: '.$this->supportsStreaming($modelId));

        return [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->supportsStreaming($modelId),
        ];
    }

    /**
     * Format the complete response from Ollama
     *
     * @param  mixed  $response
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
            'usage' => $this->extractUsage($jsonContent),
        ];
    }

    /**
     * Format a single chunk from a streaming response
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
            'usage' => $usage,
        ];
    }

    /**
     * Extract usage information from Ollama response
     */
    protected function extractUsage(array $data): ?array
    {
        if (! isset($data['eval_count']) || ! isset($data['prompt_eval_count'])) {
            return null;
        }

        // Calculate completion tokens, ensuring it's never negative
        $completionTokens = max(0, $data['eval_count'] - $data['prompt_eval_count']);

        return [
            'prompt_tokens' => $data['prompt_eval_count'],
            'completion_tokens' => $completionTokens,
        ];
    }

    /**
     * Make a non-streaming request to the HAWKI API
     *
     * @return mixed
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Ensure stream is set to false
        $payload['stream'] = false;

        // Get the chat endpoint URL from database configuration
        $chatUrl = $this->getChatEndpointUrl();

        // Initialize cURL with the database-configured URL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $chatUrl);

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders());

        // Execute the request
        $response = curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error: '.curl_error($ch);
            curl_close($ch);

            return response()->json(['error' => $error], 500);
        }

        curl_close($ch);

        return response($response)->header('Content-Type', 'application/json');
    }

    /**
     * Make a streaming request to the HAWKI API
     *
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Implementation of streaming request for HAWKI
        // Similar to Ollama implementation but adapted for HAWKI's API

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

        // Initialize cURL with the database-configured URL
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
            $streamCallback('Error: '.curl_error($ch));
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
     */
    public function getModelsStatus(): array
    {
        $response = $this->pingProvider();

        // If no response or faulty response, return empty array
        if (! $response) {
            return [];
        }

        // Parse reference list from API response
        $referenceList = json_decode($response, true);
        if (! is_array($referenceList)) {
            return [];
        }

        // Get models from the database instead of from the configuration
        $providerId = $this->getProviderId();
        $provider = ProviderSetting::where('provider_name', $providerId)
            ->where('is_active', true)
            ->first();

        if (! $provider) {
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
                'provider' => $providerId,
            ];

            // Determine model status from the API response
            foreach ($referenceList as $reference) {
                if (isset($reference['name']) && $reference['name'] === $model->model_id) {
                    $modelData['status'] = 'ready';
                    break;
                }
            }

            // If no status found, mark as 'unknown'
            if (! isset($modelData['status'])) {
                $modelData['status'] = 'unknown';
            }

            $models[] = $modelData;
        }

        return $models;
    }

    /**
     * Ping the HAWKI API to check available models
     */
    protected function pingProvider(): ?string
    {
        Log::info('pingProvider: HAWKI');

        // Get the models endpoint URL from database configuration
        $url = $this->getModelsEndpointUrl();

        try {
            // HAWKI API might not require an API key
            $response = Http::timeout(5)->get($url);

            return $response;
        } catch (\Exception $e) {
            Log::error('Error pinging HAWKI provider: '.$e->getMessage());

            return null;
        }
    }
}
