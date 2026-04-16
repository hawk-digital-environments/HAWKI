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
     * Supports extended token types from OpenAI Responses API:
     * - prompt_tokens_details: cached_tokens, audio_tokens
     * - completion_tokens_details: reasoning_tokens, audio_tokens
     *
     * @param AiModel $model
     * @param array $data
     * @return TokenUsage|null
     */
    protected function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (empty($data['usage'])) {
            return null;
        }
        
        $usage = $data['usage'];
        
        // Extract prompt_tokens_details (cache + audio input)
        $cacheReadInputTokens = $usage['prompt_tokens_details']['cached_tokens'] ?? 0;
        $audioInputTokens = $usage['prompt_tokens_details']['audio_tokens'] ?? 0;
        
        // Extract completion_tokens_details (reasoning + audio output)
        $reasoningTokens = $usage['completion_tokens_details']['reasoning_tokens'] ?? 0;
        $audioOutputTokens = $usage['completion_tokens_details']['audio_tokens'] ?? 0;
        
        // Cache creation tokens (not in prompt_tokens_details, separate field)
        $cacheCreationInputTokens = $usage['prompt_tokens_details']['cache_creation_input_tokens'] ?? 0;
        
        return new TokenUsage(
            model: $model,
            promptTokens: (int)$usage['prompt_tokens'],
            completionTokens: (int)$usage['completion_tokens'],
            totalTokens: (int)($usage['total_tokens'] ?? null),
            cacheReadInputTokens: (int)$cacheReadInputTokens,
            cacheCreationInputTokens: (int)$cacheCreationInputTokens,
            reasoningTokens: (int)$reasoningTokens,
            audioInputTokens: (int)$audioInputTokens,
            audioOutputTokens: (int)$audioOutputTokens,
        );
    }
    
}
