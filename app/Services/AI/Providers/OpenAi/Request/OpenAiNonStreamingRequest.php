<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\ToolCallAiResponse;

class OpenAiNonStreamingRequest extends AbstractRequest
{
    use OpenAiUsageTrait;
    use OpenAiToolCallingTrait;

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

                if (($item['type'] ?? '') === 'message') {
                    $content .= $item['content'][0]['text'] ?? '';
                }
            }

            // Parse tool calls
            $toolCalls = $this->parseToolCalls($data['output']);
            if (!$toolCalls->isEmpty()) {
                $finishReason = 'tool_calls';
            }
        }

        $response = new AiResponse(
            content: ['text' => $content],
            usage: $this->extractUsage($model, $data),
            isDone: true,
            finishReason: $finishReason
        );

        if (!$toolCalls?->isEmpty()) {
            return ToolCallAiResponse::fromResponseAndToolCalls(
                $response,
                $toolCalls
            );
        }

        return $response;
    }


}
