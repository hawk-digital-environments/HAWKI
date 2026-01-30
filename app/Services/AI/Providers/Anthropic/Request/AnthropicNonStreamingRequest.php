<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class AnthropicNonStreamingRequest extends AbstractRequest
{
    use AnthropicUsageTrait;
    
    public function __construct(
        private array $payload
    )
    {
    }
    
    public function execute(AiModel $model): AiResponse
    {
        $this->payload['stream'] = false;
        
        return $this->executeNonStreamingRequest(
            model: $model,
            payload: $this->payload,
            dataToResponse: fn(array $data) => $this->buildResponse($model, $data),
            getHttpHeaders: fn(AiModel $model) => $this->getAnthropicHeaders($model)
        );
    }
    
    /**
     * Build AiResponse from Anthropic data
     */
    private function buildResponse(AiModel $model, array $data): AiResponse
    {
        $textContent = $this->extractTextContent($data);
        $citations = $this->extractCitationsFromContent($data);
        
        // Count server tool use by tool name
        $serverToolUses = $this->collectServerToolUse($data);
        
        $responseContent = ['text' => $textContent];
        
        // Add citations as auxiliaries if available
        if (!empty($citations)) {
            $responseContent['auxiliaries'] = [
                [
                    'type' => 'anthropicCitations',
                    'content' => json_encode([
                        'citations' => $citations
                    ])
                ]
            ];
        }
        
        // Extract usage and add server tool use if present
        $usage = $this->extractUsage($model, $data);
        if ($usage && !empty($serverToolUses)) {
            $usage = new \App\Services\AI\Value\TokenUsage(
                model: $usage->model,
                promptTokens: $usage->promptTokens,
                completionTokens: $usage->completionTokens,
                totalTokens: $usage->totalTokens,
                cacheReadInputTokens: $usage->cacheReadInputTokens,
                cacheCreationInputTokens: $usage->cacheCreationInputTokens,
                reasoningTokens: $usage->reasoningTokens,
                audioInputTokens: $usage->audioInputTokens,
                audioOutputTokens: $usage->audioOutputTokens,
                serverToolUse: $serverToolUses, // Array with tool names as keys
            );
        }
        
        return new AiResponse(
            content: $responseContent,
            usage: $usage
        );
    }
    
    /**
     * Collect server tool use invocations with tool names
     */
    private function collectServerToolUse(array $data): array
    {
        $toolUses = [];
        
        if (!isset($data['content']) || !is_array($data['content'])) {
            return $toolUses;
        }
        
        foreach ($data['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'server_tool_use') {
                $toolName = $block['name'] ?? 'unknown';
                
                if (!isset($toolUses[$toolName])) {
                    $toolUses[$toolName] = 0;
                }
                $toolUses[$toolName]++;
            }
        }
        
        return $toolUses;
    }
    
    /**
     * Extract text content from Anthropic response, combining all text blocks
     * (handles regular text, web_search results, etc.)
     */
    private function extractTextContent(array $data): string
    {
        $text = '';
        
        if (!isset($data['content']) || !is_array($data['content'])) {
            return $text;
        }
        
        foreach ($data['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        
        return $text;
    }
    
    /**
     * Extract citations from Anthropic response content blocks
     */
    private function extractCitationsFromContent(array $data): array
    {
        $citations = [];
        
        if (!isset($data['content']) || !is_array($data['content'])) {
            return $citations;
        }
        
        foreach ($data['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'text' && isset($block['citations'])) {
                foreach ($block['citations'] as $citation) {
                    if (($citation['type'] ?? '') === 'web_search_result_location') {
                        $citations[] = [
                            'type' => 'web_search_result',
                            'url' => $citation['url'] ?? '',
                            'title' => $citation['title'] ?? '',
                            'cited_text' => $citation['cited_text'] ?? '',
                        ];
                    }
                }
            }
        }
        
        return $citations;
    }
    
    /**
     * Anthropic-specific headers
     */
    private function getAnthropicHeaders(AiModel $model): array
    {
        $headers = [
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'  // Required by Anthropic
        ];
        
        $apiKey = $model->getProvider()->getConfig()->getApiKey();
        if ($apiKey !== null) {
            $headers[] = 'x-api-key: ' . $apiKey;  // Anthropic uses x-api-key, not Bearer
        }
        
        return $headers;
    }
}
