<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Ollama\Request;


use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;

trait OllamaUsageTrait
{
    
    /**
     * Extract token usage from response data if available.
     *
     * @param AiModel $model
     * @param array $data
     * @return TokenUsage|null
     */
    protected function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (!isset($data['eval_count'], $data['prompt_eval_count'])) {
            return null;
        }
        
        return new TokenUsage(
            model: $model,
            promptTokens: (int)$data['prompt_eval_count'],
            completionTokens: (int)$data['prompt_eval_count'] - $data['eval_count'],
        );
    }
}
