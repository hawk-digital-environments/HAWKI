<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class OpenAiStreamingRequest extends AbstractRequest
{
    use OpenAiUsageTrait;

    public function __construct(
        private array    $payload,
        private \Closure $onData
    )
    {
    }

    public function execute(AiModel $model): void
    {
        $this->payload['stream'] = true;
//        $this->payload['stream_options'] = [
//            'include_usage' => true,
//        ];
        \Log::debug($this->payload);

        $this->executeStreamingRequest(
            model: $model,
            payload: $this->payload,
            onData: $this->onData,
            chunkToResponse: [$this, 'chunkToResponse']
        );
    }

    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {
        \Log::debug($chunk);
        // Parse the event JSON
        $jsonChunk = json_decode($chunk, true);
        if (!$jsonChunk) {
            return $this->createErrorResponse('Invalid JSON chunk received.');
        }

        if (isset($jsonChunk['error'])) {
            return $this->createErrorResponse($jsonChunk['error']['message'] ?? 'Unknown error');
        }
        $type = $jsonChunk['type'] ?? '';

        $content = '';
        $isDone = false;
        $usage = null;
        $toolCalls = null;
        $finishReason = null;

        switch ($type) {
            // The main streaming text deltas
            case 'response.output_text.delta':
                $content = $jsonChunk['delta'] ?? '';
                break;

            // When the whole response is done
            case 'response.completed':
                $isDone = true;
                $response = $jsonChunk['response'] ?? [];

                // Extract usage
                if (!empty($response['usage'])) {
                    $usage = $this->extractUsage($model, $response);
                }

                // Parse tool calls from output array
                if (!empty($response['output'])) {
                    $toolCalls = $this->parseToolCalls($response['output']);
                    if (!empty($toolCalls)) {
                        $finishReason = 'tool_calls';
                    }
                }
                break;

            // Optional: handle created/in_progress etc. for logging/debugging
            case 'response.created':
            case 'response.in_progress':
            case 'response.output_item.added':
            case 'response.output_item.done':
            case 'response.content_part.added':
            case 'response.function_call_arguments.delta':
            case 'response.function_call_arguments.done':
                // No text content, just metadata events
                break;

            default:
                // Unknown or unsupported type — ignore or log it
                break;
        }

        return new AiResponse(
            content: [
                'text' => $content,
            ],
            usage: $usage,
            isDone: $isDone,
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
