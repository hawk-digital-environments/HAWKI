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

            // Complete text output
            case 'response.output_text.done':
                $content = $jsonChunk['text'] ?? '';
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
                break;

            // Response failed
            case 'response.failed':
                $error = $jsonChunk['error'] ?? $jsonChunk['response']['error'] ?? [];
                $errorMessage = $error['message'] ?? 'Response failed';
                return $this->createErrorResponse($errorMessage);

            // Metadata events (no action needed)
            case 'response.created':
            case 'response.in_progress':
            case 'response.output_item.added':
            case 'response.output_item.done':
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

        $response = new AiResponse(
            content: ['text' => $content],
            usage: $usage,
            isDone: $isDone
        );

        // Attach auxiliaries to response if present
        if (!empty($auxiliaries)) {
            $response->auxiliaries = $auxiliaries;
        }

        return $response;
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
}
