<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class AnthropicStreamingRequest extends AbstractRequest
{
    use AnthropicUsageTrait;
    
    private array $citations = [];
    
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
                    
                    // Check for citations in this text block
                    if (isset($data['delta']['citations'])) {
                        $this->extractCitations($data['delta']['citations']);
                    }
                }
                break;

            case 'content_block_start':
                // Check if this is a web search tool invocation
                if (isset($data['content_block']['type'])) {
                    if ($data['content_block']['type'] === 'server_tool_use') {
                        // Web search tool is being invoked
                        if (config('app.debug')) {
                            \Log::debug("Anthropic web search invocation", [
                                'tool_use_id' => $data['content_block']['id'] ?? null,
                                'tool_name' => $data['content_block']['name'] ?? null
                            ]);
                        }
                    } elseif ($data['content_block']['type'] === 'web_search_tool_result') {
                        // Extract search sources from web_search_tool_result
                        $this->extractWebSearchSources($data['content_block']);
                    } elseif ($data['content_block']['type'] === 'text') {
                        // Check for citations in text blocks (text-based citations)
                        if (isset($data['content_block']['citations']) && !empty($data['content_block']['citations'])) {
                            $this->extractCitations($data['content_block']['citations']);
                        }
                    }
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

        // Build response content
        $responseContent = ['text' => $content];
        
        // Add citations as auxiliaries as soon as they are available
        if (!empty($this->citations)) {
            $responseContent['auxiliaries'] = [
                [
                    'type' => 'anthropicCitations',
                    'content' => json_encode([
                        'citations' => $this->citations
                    ])
                ]
            ];
        }

        return new AiResponse(
            content: $responseContent,
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
    
    /**
     * Extract web search sources from web_search_tool_result
     */
    private function extractWebSearchSources(array $contentBlock): void
    {
        if (!isset($contentBlock['content']) || !is_array($contentBlock['content'])) {
            return;
        }
        
        foreach ($contentBlock['content'] as $result) {
            if (($result['type'] ?? '') === 'web_search_result') {
                $source = [
                    'type' => 'web_search_source',
                    'url' => $result['url'] ?? '',
                    'title' => $result['title'] ?? '',
                    'page_age' => $result['page_age'] ?? '',
                ];
                
                $this->citations[] = $source;
            }
        }
    }
    
    /**
     * Extract citations from Anthropic text block citations (for inline citations)
     */
    private function extractCitations(array $citations): void
    {
        foreach ($citations as $citation) {
            if (($citation['type'] ?? '') === 'web_search_result_location') {
                $extractedCitation = [
                    'type' => 'web_search_citation',
                    'url' => $citation['url'] ?? '',
                    'title' => $citation['title'] ?? '',
                    'cited_text' => $citation['cited_text'] ?? '',
                ];
                
                $this->citations[] = $extractedCitation;
            }
        }
    }
}
