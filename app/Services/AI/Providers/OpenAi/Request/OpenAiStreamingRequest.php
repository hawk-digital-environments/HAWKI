<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiErrorResponse;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\ToolCallAiResponse;

class OpenAiStreamingRequest extends AbstractRequest
{
    use OpenAiUsageTrait;
    use OpenAiToolCallingTrait;

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
            chunkToResponse: [$this, 'chunkToResponse'],
            timeout: 360
        );
    }

    protected function chunkToResponse(AiModel $model, string $chunk): AiResponse
    {

        // Parse the event JSON
        $jsonChunk = json_decode($chunk, true);
        if (!$jsonChunk) {
            return new AiErrorResponse('Invalid JSON chunk received');
        }

        if (isset($jsonChunk['error'])) {
            return new AiErrorResponse($jsonChunk['error']['message'] ?? 'Unknown error');
        }
        $type = $jsonChunk['type'] ?? '';

        $content = '';
        $isDone = false;
        $usage = null;
        $toolCalls = null;
        $finishReason = null;
        $status = null;
        $statusKey = null;
        $responseType = 'message';

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
                    if ($toolCalls->hasItems()) {
                        $finishReason = 'tool_calls';
                    }
                }
                break;

            // Optional: handle created/in_progress etc. for logging/debugging
            case 'response.created':
            case 'response.in_progress':
                $responseType = 'status';
                $status = 'in_progress';
                $statusKey = 'reasoning';
                break;
            case 'response.output_item.added':
            case 'response.output_item.done':
                $responseType = 'status';
                $status = $jsonChunk['item.type'] ?? '';
                $statusKey = 'reasoning';
            case 'response.content_part.added':
            case 'response.function_call_arguments.delta':
            case 'response.function_call_arguments.done':
                // No text content, just metadata events
                break;

            default:
                // Unknown or unsupported type — ignore or log it
                break;
        }

        $response = new AiResponse(
            content: [
                'text' => $content,
            ],
            usage: $usage,
            isDone: $isDone,
            finishReason: $finishReason,
            type: $responseType,
            status: [
                        'key' => $statusKey,
                        'value' => $status,
                    ],
        );

        if ($toolCalls?->hasItems()) {
            return ToolCallAiResponse::fromResponseAndToolCalls(
                response: $response,
                toolCalls: $toolCalls
            );
        }

        return $response;
    }
}
