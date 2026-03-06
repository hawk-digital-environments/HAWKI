<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic\Request;

use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;

trait AnthropicUsageTrait
{
    private function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (!isset($data['usage'])) {
            return null;
        }
        
        $usage = $data['usage'];
        
        // Extract base tokens
        $inputTokens = (int)($usage['input_tokens'] ?? 0);
        $outputTokens = (int)($usage['output_tokens'] ?? 0);
        
        // Extract cache tokens (Prompt Caching)
        $cacheReadTokens = (int)($usage['cache_read_input_tokens'] ?? 0);
        $cacheCreationTokens = (int)($usage['cache_creation_input_tokens'] ?? 0);
        
        // Log usage data if trigger is enabled
        if (config('logging.triggers.usage')) {
            $logData = [
                'model' => $model->getId(),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $inputTokens + $outputTokens,
            ];
            
            if ($cacheReadTokens > 0) {
                $logData['cache_read_input_tokens'] = $cacheReadTokens;
            }
            if ($cacheCreationTokens > 0) {
                $logData['cache_creation_input_tokens'] = $cacheCreationTokens;
            }
            
            \Log::info('Token Usage - Anthropic', $logData);
        }
        
        return new TokenUsage(
            model: $model,
            promptTokens: $inputTokens,
            completionTokens: $outputTokens,
            totalTokens: null,
            cacheReadInputTokens: $cacheReadTokens,
            cacheCreationInputTokens: $cacheCreationTokens,
            reasoningTokens: 0, // Anthropic doesn't separate reasoning tokens
            audioInputTokens: 0,
            audioOutputTokens: 0,
            serverToolUse: null, // Will be added in StreamingRequest if tools are used
        );
    }
}
