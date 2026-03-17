<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


readonly class AiResponse implements \JsonSerializable
{
    /**
     * Response types:
     * - 'content': Regular text content from the model
     * - 'tool_call': Model is requesting to call a tool
     * - 'tool_result': Result from tool execution
     * - 'status': Status message (e.g., "Executing tool...", "Max rounds reached")
     */
    public function __construct(
        public array       $content,
        public ?TokenUsage $usage = null,
        public bool        $isDone = true,
        public ?string     $error = null,
        public ?string     $finishReason = null,  // 'stop', 'tool_calls', 'length', etc.
        public string      $type = 'message',     // 'content', 'tool_call', 'tool_result', 'status'
        public ?array      $status = null  // Optional message for status type
    )
    {
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'usage' => $this->usage,
            'isDone' => $this->isDone,
            'finishReason' => $this->finishReason,
            'type' => $this->type,
            'status' => $this->status,
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
