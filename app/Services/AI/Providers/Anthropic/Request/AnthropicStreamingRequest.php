<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class AnthropicStreamingRequest extends AbstractRequest
{
    use AnthropicUsageTrait;
    
    public function __construct(
        private array    $payload,
        private          $onData
    )
    {
    }
    
    public function execute(AiModel $model): void
    {
        $this->payload['stream'] = true;
        
        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: fn(AiModel $model, string $chunk) => $this->parseStreamChunk($model, $chunk),
            getHttpHeaders: fn(AiModel $model) => $this->getAnthropicHeaders($model)
        );
    }
    
    /**
     * Parse Anthropic streaming response chunk
     * Anthropic sends SSE format with event: and data: lines
     */
    private function parseStreamChunk(AiModel $model, string $chunk): AiResponse
    {
        $content = '';
        $isDone = false;
        $usage = null;

        // Log raw chunk for debugging (only if trigger enabled)
        if (config('logging.triggers.curl_return_object')) {
            \Log::info('Anthropic Stream Chunk (raw)', [
                'chunk_length' => strlen($chunk),
                'chunk_preview' => substr($chunk, 0, 200)
            ]);
        }

        // Try to parse as JSON (after StreamChunkHandler normalization)
        try {
            $data = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            \Log::warning('Anthropic stream chunk JSON parse error', [
                'error' => $e->getMessage(),
                'chunk' => substr($chunk, 0, 200)
            ]);
            // Return empty response - will be filtered out
            return new AiResponse(
                content: ['text' => ''],
                usage: null,
                isDone: false
            );
        }
        
        if (!$data || !isset($data['type'])) {
            // If no type, return empty response
            return new AiResponse(
                content: ['text' => ''],
                usage: null,
                isDone: false
            );
        }
        
        switch ($data['type']) {
            case 'content_block_delta':
                // This contains the actual text content
                if (isset($data['delta']['type']) && $data['delta']['type'] === 'text_delta') {
                    $content = $data['delta']['text'] ?? '';
                }
                break;

            case 'message_delta':
                // Message metadata update, may contain usage info
                if (isset($data['usage'])) {
                    $usage = $this->extractUsage($model, ['usage' => $data['usage']]);
                }
                break;

            case 'message_stop':
                // Message has completely finished
                $isDone = true;
                break;

            case 'message_start':
            case 'content_block_start':
            case 'content_block_stop':
            case 'ping':
                // Ignore these event types - return empty response
                break;

            default:
                // Unknown event type - log only in debug mode
                if (config('app.debug')) {
                    \Log::debug("Unknown Anthropic stream event type: {$data['type']}", ['data' => $data]);
                }
                break;
        }

        return new AiResponse(
            content: ['text' => $content],
            usage: $usage,
            isDone: $isDone
        );
    }
    
    /**
     * Anthropic-specific headers
     */
    private function getAnthropicHeaders(AiModel $model): array
    {
        $headers = [
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ];
        
        $apiKey = $model->getProvider()->getConfig()->getApiKey();
        if ($apiKey !== null) {
            $headers[] = 'x-api-key: ' . $apiKey;
        }
        
        return $headers;
    }
}
