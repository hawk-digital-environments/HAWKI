<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Providers\AbstractRequest;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;

class AnthropicNonStreamingRequest extends AbstractRequest
{
    use AnthropicUsageTrait;
    
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
                    'text' => $data['content'][0]['text'] ?? ''
                ],
                usage: $this->extractUsage($model, $data)
            ),
            getHttpHeaders: fn(AiModel $model) => $this->getAnthropicHeaders($model)
        );
    }
    
    /**
     * Anthropic-specific headers
     */
    private function getAnthropicHeaders(AiModel $model): array
    {
        $headers = [
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'  // Required by Anthropic
        ];
        
        $apiKey = $model->getProvider()->getConfig()->getApiKey();
        if ($apiKey !== null) {
            $headers[] = 'x-api-key: ' . $apiKey;  // Anthropic uses x-api-key, not Bearer
        }
        
        return $headers;
    }
}
