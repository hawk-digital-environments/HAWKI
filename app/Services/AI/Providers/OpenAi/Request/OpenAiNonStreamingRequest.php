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
            dataToResponse: fn(array $data) => new AiResponse(
                content: [
                    'text' => $data['choices'][0]['message']['content'] ?? ''
                ],
                usage: $this->extractUsage($model, $data)
            )
        );
    }
}
