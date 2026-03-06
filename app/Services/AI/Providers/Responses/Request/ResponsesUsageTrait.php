<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Responses\Request;

use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;

trait ResponsesUsageTrait
{
    /**
     * Extract usage information from Responses API response
     * 
     * Responses API uses 'input_tokens' and 'output_tokens'
     * (unlike Chat Completions which uses 'prompt_tokens' and 'completion_tokens')
     * 
     * Supports extended token types:
     * - input_tokens_details: cached_tokens, text_tokens, audio_tokens, image_tokens
     * - output_tokens_details: reasoning_tokens, text_tokens, audio_tokens
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

        // Extract base token counts
        $inputTokens = (int)($usage['input_tokens'] ?? 0);
        $outputTokens = (int)($usage['output_tokens'] ?? 0);

        // Extract input_tokens_details (cache + audio)
        $inputDetails = $usage['input_tokens_details'] ?? [];
        $cacheReadInputTokens = (int)($inputDetails['cached_tokens'] ?? 0);
        $audioInputTokens = (int)($inputDetails['audio_tokens'] ?? 0);
        
        // Note: cache_creation_input_tokens might be a separate field
        $cacheCreationInputTokens = (int)($inputDetails['cache_creation_input_tokens'] ?? 0);

        // Extract output_tokens_details (reasoning + audio)
        $outputDetails = $usage['output_tokens_details'] ?? [];
        $reasoningTokens = (int)($outputDetails['reasoning_tokens'] ?? 0);
        $audioOutputTokens = (int)($outputDetails['audio_tokens'] ?? 0);

        return new TokenUsage(
            model: $model,
            promptTokens: $inputTokens,
            completionTokens: $outputTokens,
            totalTokens: (int)($usage['total_tokens'] ?? null),
            cacheReadInputTokens: $cacheReadInputTokens,
            cacheCreationInputTokens: $cacheCreationInputTokens,
            reasoningTokens: $reasoningTokens,
            audioInputTokens: $audioInputTokens,
            audioOutputTokens: $audioOutputTokens,
        );
    }
}
