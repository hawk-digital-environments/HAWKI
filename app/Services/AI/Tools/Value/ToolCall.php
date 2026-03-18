<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Value;

readonly class ToolCall implements \JsonSerializable
{
    public function __construct(
        public string $id,           // e.g., "chatcmpl-tool-616df8d2..."
        public string $type,         // "function"
        public string $name,         // "test_tool"
        public array $arguments,     // Parsed JSON arguments
        public ?int $index = null    // For streaming assembly
    )
    {
    }

    /**
     * Returns a plain array representation of the tool call, suitable for sending to the model or logging
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'function' => [
                'name' => $this->name,
                'arguments' => json_encode($this->arguments),
            ],
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
