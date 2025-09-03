<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Google\Request;


use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class GoogleNonStreamingRequest extends AbstractRequest
{
    use GoogleRequestTrait;
    
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
            payload: $this->preparePayload($this->payload),
            dataToResponse: fn(array $data) => new AiResponse(
                content: [
                    'text' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
                    'groundingMetadata' => $data['candidates'][0]['groundingMetadata'] ?? ''
                ],
                usage: $this->extractUsage($model, $data)
            ),
            getHttpHeaders: fn() => [
                'Content-Type: application/json'
            ],
            apiUrl: $this->buildApiUrl($model)
        );
    }
}
