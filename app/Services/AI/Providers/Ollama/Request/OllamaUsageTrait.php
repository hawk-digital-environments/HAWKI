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
        
        $promptTokens = (int)$data['prompt_eval_count'];
        $completionTokens = (int)$data['eval_count']; // eval_count is already the response tokens count
        
        // Log usage data if trigger is enabled
        if (config('logging.triggers.usage')) {
            \Log::info('Token Usage - Ollama', [
                'model' => $model->getId(),
                'prompt_eval_count' => $promptTokens,
                'eval_count' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens
            ]);
        }
        
        return new TokenUsage(
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
        );
    }
}
