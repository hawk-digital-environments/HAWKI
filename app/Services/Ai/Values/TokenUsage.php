<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


use App\Models\Ai\AiModel;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Immutable value object that records the token counts produced by a single AI response.
 *
 * Carries the model that was used together with the number of prompt tokens consumed
 * and completion tokens generated. When constructed from a Laravel AI {@see Usage} object
 * via {@see fromLaravelUsage()}, cached input tokens are part of `$promptTokens` and
 * reasoning tokens are part of `$completionTokens`.
 *
 * Used by {@see UsageAnalyzerService} to persist usage records and by agent
 * implementations (e.g. {@see \App\Services\Ai\Agents\Adapters\AbstractLaravelAgent})
 * to expose usage data after a response is received.
 *
 * @api
 */
readonly class TokenUsage implements \JsonSerializable
{
    public function __construct(
        public AiModel $model,
        public int     $promptTokens,
        public int     $completionTokens,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model->model_id,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens
        ];
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Creates a TokenUsage from a Laravel AI {@see Usage} response object.
     *
     * Since Laravel AI 1.0 the reported counts are inclusive: `inputTokens` contains
     * cached and cache-written tokens and `outputTokens` contains reasoning tokens.
     * HAWKI tracks only two token buckets (prompt / completion), so the totals map
     * directly without adding the breakdown on top.
     */
    public static function fromLaravelUsage(Usage $usage, AiModel $model): self
    {
        return new self(
            model: $model,
            promptTokens: $usage->inputTokens,
            completionTokens: $usage->outputTokens,
        );
    }
}
