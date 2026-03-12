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

    public function jsonSerialize(): array
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'arguments' => $this->arguments,
            'index' => $this->index,
        ];
    }
}
