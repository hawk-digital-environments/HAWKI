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
    ) {}

    public function execute(AiModel $model): AiResponse
    {
        // Make sure streaming is disabled
        $this->payload['stream'] = false;

        return $this->executeNonStreamingRequest(
            model: $model,
            payload: $this->payload,
            dataToResponse: function (array $data) use ($model) {
                // Handle errors
                if (isset($data['error'])) {
                    return $this->createErrorResponse($data['error']['message'] ?? 'Unknown error');
                }

                // Extract text safely from new API structure
                $text = $data['output'][0]['content'][0]['text'] ?? '';

                return new AiResponse(
                    content: ['text' => $text],
                    usage: $this->extractUsage($model, $data)
                );
            }
        );
    }
}
