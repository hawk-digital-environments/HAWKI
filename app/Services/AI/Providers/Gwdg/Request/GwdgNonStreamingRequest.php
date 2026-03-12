<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Gwdg\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Tools\Value\ToolCall;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class GwdgNonStreamingRequest extends AbstractRequest
{
    use GwdgUsageTrait;

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
            dataToResponse: fn(array $data) => $this->dataToResponse($model, $data)
        );
    }

    private function dataToResponse(AiModel $model, array $data): AiResponse
    {
        $message = $data['choices'][0]['message'] ?? [];
        $content = $message['content'] ?? '';
        $finishReason = $data['choices'][0]['finish_reason'] ?? null;
        $toolCalls = null;

        // Parse tool calls if present
        if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
            $toolCalls = $this->parseToolCalls($message['tool_calls']);
        }

        return new AiResponse(
            content: [
                'text' => $content
            ],
            usage: $this->extractUsage($model, $data),
            isDone: true,
            error: null,
            toolCalls: $toolCalls,
            finishReason: $finishReason
        );
    }

    /**
     * Parse tool calls from non-streaming response
     */
    private function parseToolCalls(array $toolCallsData): array
    {
        $toolCalls = [];

        foreach ($toolCallsData as $index => $toolCallData) {
            try {
                $arguments = json_decode(
                    $toolCallData['function']['arguments'] ?? '{}',
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $toolCalls[] = new ToolCall(
                    id: $toolCallData['id'] ?? 'tool-' . $index,
                    type: $toolCallData['type'] ?? 'function',
                    name: $toolCallData['function']['name'] ?? '',
                    arguments: $arguments,
                    index: $index
                );

                \Log::info('Non-streaming tool call parsed', [
                    'name' => $toolCallData['function']['name'] ?? '',
                    'arguments' => $arguments,
                ]);
            } catch (\JsonException $e) {
                \Log::error('Failed to parse non-streaming tool call', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $toolCalls;
    }
}
