<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


use App\Models\Ai\AiModel;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Immutable value object that records the token counts produced by a single AI response.
 *
 * Carries the model that was used together with the number of prompt tokens consumed
 * and completion tokens generated. Reasoning tokens (returned separately by some
 * providers) are folded into `$completionTokens` when constructing from a Laravel AI
 * {@see Usage} object via {@see fromLaravelUsage()}.
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
     * Reasoning tokens are added to `$completionTokens` because HAWKI tracks only
     * two token buckets (prompt / completion); the split between generated and
     * reasoning output is not relevant for billing or quota purposes here.
     */
    public static function fromLaravelUsage(Usage $usage, AiModel $model): self
    {
        return new self(
            model: $model,
            promptTokens: $usage->promptTokens,
            completionTokens: $usage->completionTokens + $usage->reasoningTokens,
        );
    }
}
