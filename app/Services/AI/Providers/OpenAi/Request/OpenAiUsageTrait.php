<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi\Request;


use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;

trait OpenAiUsageTrait
{
    /**
     * Extract usage information from OpenAI response
     *
     * @param AiModel $model
     * @param array $data
     * @return array|null
     */
    protected function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (empty($data['usage'])) {
            return null;
        }
        
        return new TokenUsage(
            model: $model,
            promptTokens: (int)$data['usage']['prompt_tokens'],
            completionTokens: (int)$data['usage']['completion_tokens'],
        );
    }
    
}
