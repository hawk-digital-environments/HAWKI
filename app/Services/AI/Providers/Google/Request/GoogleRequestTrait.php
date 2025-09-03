<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Google\Request;


use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;

trait GoogleRequestTrait
{
    /**
     * Extract usage information from Google response
     *
     * @param AiModel $model
     * @param array $data
     * @return TokenUsage|null
     */
    protected function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (empty($data['usageMetadata'])) {
            return null;
        }
        // fix duplicate usage log entries
        if (!empty($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === "STOP") {
            return new TokenUsage(
                model: $model,
                promptTokens: (int)($data['usageMetadata']['promptTokenCount'] ?? 0),
                completionTokens: (int)($data['usageMetadata']['candidatesTokenCount'] ?? 0),
            );
        }
        return null;
    }
    
    protected function buildApiUrl(AiModel $model): string
    {
        $config = $model->getProvider()->getConfig();
        $apiUrl = $config->getApiUrl();
        $apiKey = $config->getApiKey();
        return $apiUrl . $model->getId() . ':generateContent?key=' . $apiKey;
    }
    
    protected function preparePayload(array $payload): array
    {
        
        // Extract just the necessary parts for Google's API
        $requestPayload = [
            'system_instruction' => $payload['system_instruction'],
            'contents' => $payload['contents']
        ];
        
        // Add aditional config parameters if present
        if (isset($payload['safetySettings'])) {
            $requestPayload['safetySettings'] = $payload['safetySettings'];
        }
        if (isset($payload['generationConfig'])) {
            $requestPayload['generationConfig'] = $payload['generationConfig'];
        }
        if (isset($payload['tools'])) {
            $requestPayload['tools'] = $payload['tools'];
        }
        
        return $requestPayload;
    }
}
