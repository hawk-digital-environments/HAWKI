<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Responses\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class ResponsesStreamingRequest extends AbstractRequest
{
    use ResponsesUsageTrait;

    private array $reasoningItems = [];
    private array $citations = [];

    public function __construct(
        private array    $payload,
        private \Closure $onData
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
            chunkToResponse: [$this, 'chunkToResponse']
        );
    }

    /**
     * Convert streaming chunk to AiResponse
     * Handles all Responses API event types
     */
    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        $jsonChunk = json_decode($chunk, true, 512, JSON_THROW_ON_ERROR);

        if (!$jsonChunk) {
            return $this->createErrorResponse('Invalid JSON chunk received.');
        }

        // Handle errors
        if (isset($jsonChunk['error'])) {
            return $this->createErrorResponse($jsonChunk['error']['message'] ?? 'Unknown error');
        }

        $type = $jsonChunk['type'] ?? '';
        $content = '';
        $isDone = false;
        $usage = null;
        $auxiliaries = [];

        switch ($type) {
            // Main streaming text chunks
            case 'response.output_text.delta':
                $content = $jsonChunk['delta'] ?? '';
                break;

            // Complete text output - DON'T send content again (causes duplicates)
            // The text was already sent via delta events
            case 'response.output_text.done':
                // Just a completion signal, no content to send
                break;

            // Reasoning chunks (streaming)
            case 'response.reasoning.delta':
                $this->handleReasoningDelta($jsonChunk);
                break;

            // Complete reasoning output
            case 'response.reasoning.done':
                $this->handleReasoningDone($jsonChunk);
                break;

            // MCP tool call initiated
            case 'response.mcp_call_tool':
                // Tool calls are handled internally by OpenAI
                // We just log for debugging if needed
                break;

            // Web search call initiated
            case 'response.web_search_call':
                // Extract web search metadata
                $this->handleWebSearchCall($jsonChunk);
                break;

            // Response completed with final data
            case 'response.completed':
                $isDone = true;
                
                // Extract usage from final response
                if (!empty($jsonChunk['response']['usage'])) {
                    $usage = $this->extractUsage($model, $jsonChunk['response']);
                }

                // Include reasoning items as auxiliaries
                if (!empty($this->reasoningItems)) {
                    $auxiliaries[] = [
                        'type' => 'responsesReasoning',
                        'content' => json_encode([
                            'reasoning' => $this->reasoningItems
                        ])
                    ];
                }

                // Include citations as auxiliaries
                if (!empty($this->citations)) {
                    $auxiliaries[] = [
                        'type' => 'responsesCitations',
                        'content' => json_encode([
                            'citations' => $this->citations
                        ])
                    ];
                }
                break;

            // Response failed
            case 'response.failed':
                $error = $jsonChunk['error'] ?? $jsonChunk['response']['error'] ?? [];
                $errorMessage = $error['message'] ?? 'Response failed';
                return $this->createErrorResponse($errorMessage);

            // Output item done - may contain citations/annotations
            case 'response.output_item.done':
                $this->handleOutputItemDone($jsonChunk);
                break;

            // Metadata events (no action needed)
            case 'response.created':
            case 'response.in_progress':
            case 'response.output_item.added':
            case 'response.content_part.added':
            case 'response.content_part.done':
            case 'response.mcp_list_tools.in_progress':
            case 'response.mcp_list_tools.completed':
                // Ignore metadata events
                break;

            default:
                // Unknown event type - log for debugging
                // Note: Don't use Log::debug in production
                break;
        }

        // Skip empty responses for metadata events (prevents duplicate messages)
        // Only send responses that have content OR are done (final message with usage)
        if (empty($content) && !$isDone) {
            return new AiResponse(
                content: ['text' => ''],
                isDone: false
            );
        }

        return new AiResponse(
            content: ['text' => $content],
            usage: $usage,
            isDone: $isDone,
            auxiliaries: $auxiliaries
        );
    }

    /**
     * Handle streaming reasoning delta
     */
    private function handleReasoningDelta(array $chunk): void
    {
        // Store reasoning chunk for later assembly
        $itemId = $chunk['item_id'] ?? null;
        $delta = $chunk['delta'] ?? '';

        if ($itemId) {
            if (!isset($this->reasoningItems[$itemId])) {
                $this->reasoningItems[$itemId] = [
                    'id' => $itemId,
                    'type' => 'reasoning',
                    'content' => ''
                ];
            }
            $this->reasoningItems[$itemId]['content'] .= $delta;
        }
    }

    /**
     * Handle complete reasoning output
     */
    private function handleReasoningDone(array $chunk): void
    {
        $itemId = $chunk['item_id'] ?? null;
        $content = $chunk['content'] ?? $chunk['text'] ?? '';

        if ($itemId) {
            $this->reasoningItems[$itemId] = [
                'id' => $itemId,
                'type' => 'reasoning',
                'content' => $content
            ];
        }
    }

    /**
     * Handle web search call event
     * Extracts search metadata for debugging/analytics
     */
    private function handleWebSearchCall(array $chunk): void
    {
        // Extract web search call metadata
        $searchId = $chunk['id'] ?? null;
        $status = $chunk['status'] ?? 'unknown';
        
        // Extract action details if available
        $action = $chunk['action'] ?? [];
        $actionType = $action['type'] ?? null; // 'search', 'open_page', 'find_in_page'
        
        // Store metadata for potential future use
        // For now, we just acknowledge the search call
        // In future: could track search queries, domains, sources for analytics
        
        // Optional: Extract additional details
        // $query = $action['query'] ?? null;
        // $domains = $action['domains'] ?? [];
        // $sources = $action['sources'] ?? [];
    }

    /**
     * Handle output item done event
     * Extracts URL citations from message annotations
     */
    private function handleOutputItemDone(array $chunk): void
    {
        // Check if this is a message output item with content
        $item = $chunk['item'] ?? [];
        if (($item['type'] ?? '') !== 'message') {
            return;
        }

        // Extract content array
        $content = $item['content'] ?? [];
        if (empty($content)) {
            return;
        }

        // Parse each content part for annotations
        foreach ($content as $contentPart) {
            if (($contentPart['type'] ?? '') === 'output_text') {
                $annotations = $contentPart['annotations'] ?? [];
                
                foreach ($annotations as $annotation) {
                    if (($annotation['type'] ?? '') === 'url_citation') {
                        // Store citation for later use
                        $this->citations[] = [
                            'type' => 'url_citation',
                            'url' => $annotation['url'] ?? '',
                            'title' => $annotation['title'] ?? '',
                            'start_index' => $annotation['start_index'] ?? 0,
                            'end_index' => $annotation['end_index'] ?? 0,
                        ];
                    }
                }
            }
        }
    }
}
