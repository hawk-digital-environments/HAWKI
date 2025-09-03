<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


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
            'model' => $this->model->getId(),
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
    
}
