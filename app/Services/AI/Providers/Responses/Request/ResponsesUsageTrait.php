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

        // Extract reasoning tokens if available
        $reasoningTokens = 0;
        if (isset($usage['output_tokens_details']['reasoning_tokens'])) {
            $reasoningTokens = (int)$usage['output_tokens_details']['reasoning_tokens'];
        }

        // Create TokenUsage with Responses API token naming
        // Note: TokenUsage constructor expects 'promptTokens' and 'completionTokens'
        // but we're feeding it Responses API values (input_tokens, output_tokens)
        $tokenUsage = new TokenUsage(
            model: $model,
            promptTokens: $inputTokens,
            completionTokens: $outputTokens,
        );

        // Store reasoning tokens as additional metadata if present
        if ($reasoningTokens > 0) {
            // Note: TokenUsage might not have a reasoningTokens property
            // This is for future extension or can be stored in metadata
            // For now, reasoning tokens are included in output_tokens
        }

        return $tokenUsage;
    }
}
