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
        
        return new TokenUsage(
            model: $model,
            promptTokens: $usage['input_tokens'] ?? 0,
            completionTokens: $usage['output_tokens'] ?? 0
        );
    }
}
