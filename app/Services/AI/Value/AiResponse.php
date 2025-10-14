<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


readonly class AiResponse implements \JsonSerializable
{
    public function __construct(
        public array       $content,
        public ?TokenUsage $usage = null,
        public bool        $isDone = true,
        public ?string     $error = null,
    )
    {
    }
    
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'usage' => $this->usage,
            'isDone' => $this->isDone,
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
