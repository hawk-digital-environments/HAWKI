<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Gwdg\Request;


use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\TokenUsage;

trait GwdgUsageTrait
{
    protected function extractUsage(AiModel $model, array $data): ?TokenUsage
    {
        if (empty($data['usage'])) {
            return null;
        }
        
        return new TokenUsage(
            model: $model,
            promptTokens: (int)($data['usage']['prompt_tokens'] ?? 0),
            completionTokens: (int)($data['usage']['completion_tokens'] ?? 0),
        );
    }
}
