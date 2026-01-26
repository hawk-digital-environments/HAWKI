<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class OpenAiNonStreamingRequest extends AbstractRequest
{
    use OpenAiUsageTrait;
    
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
            dataToResponse: fn(array $data) => $this->parseResponse($model, $data)
        );
    }

    private function parseResponse(AiModel $model, array $data): AiResponse
    {
        // Response API format
        $content = '';
        $toolCalls = null;
        $finishReason = null;

        // Extract text content from output array
        if (!empty($data['output'])) {
            foreach ($data['output'] as $item) {
                if (($item['type'] ?? '') === 'output_text') {
                    $content .= $item['text'] ?? '';
                }
            }

            // Parse tool calls
            $toolCalls = $this->parseToolCalls($data['output']);
            if (!empty($toolCalls)) {
                $finishReason = 'tool_calls';
            }
        }

        return new AiResponse(
            content: ['text' => $content],
            usage: $this->extractUsage($model, $data),
            isDone: true,
            toolCalls: $toolCalls,
            finishReason: $finishReason
        );
    }

    /**
     * Parse tool calls from Response API output array
     */
    private function parseToolCalls(array $output): array
    {
        $toolCalls = [];

        foreach ($output as $item) {
            if (($item['type'] ?? '') === 'function_call' && ($item['status'] ?? '') === 'completed') {
                $arguments = json_decode($item['arguments'] ?? '{}', true);

                $toolCalls[] = new \App\Services\AI\Tools\Value\ToolCall(
                    id: $item['call_id'] ?? $item['id'] ?? 'unknown',
                    type: 'function',
                    name: $item['name'] ?? 'unknown',
                    arguments: $arguments,
                    index: null
                );
            }
        }

        return $toolCalls;
    }
}
