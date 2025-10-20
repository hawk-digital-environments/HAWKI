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
        public array       $auxiliaries = [],
    )
    {
    }
    
    public function toArray(): array
    {
        $result = [
            'content' => $this->content,
            'usage' => $this->usage,
            'isDone' => $this->isDone,
        ];
        
        // Include auxiliaries if present
        if (!empty($this->auxiliaries)) {
            $result['auxiliaries'] = $this->auxiliaries;
        }
        
        return $result;
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
}
