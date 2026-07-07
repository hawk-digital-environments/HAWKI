<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


use App\Models\Ai\AiModel;
use Laravel\Ai\Responses\Data\Usage;

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

    public static function fromLaravelUsage(Usage $usage, AiModel $model): self
    {
        return new self(
            model: $model,
            promptTokens: $usage->promptTokens,
            completionTokens: $usage->completionTokens + $usage->reasoningTokens,
        );
    }
}
