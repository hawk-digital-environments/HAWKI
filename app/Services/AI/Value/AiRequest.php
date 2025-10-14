<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


readonly class AiRequest
{
    public function __construct(
        public ?AiModel $model = null,
        public ?array   $payload = null
    )
    {
    }
    
    public function withModel(AiModel $model): self
    {
        return new self(
            model: $model,
            payload: $this->payload
        );
    }
}
