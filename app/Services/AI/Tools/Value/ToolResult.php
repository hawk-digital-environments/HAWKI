<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Value;

readonly class ToolResult
{
    public function __construct(
        public string $toolCallId,
        public string $toolName,
        public mixed $result,
        public bool $success = true,
        public ?string $error = null
    )
    {
    }

    /**
     * Convert to message format for sending back to the model
     * According to OpenAI spec, tool results should use role="tool"
     */
    public function toMessageFormat(): array
    {
        return [
            'role' => 'tool',
            'tool_call_id' => $this->toolCallId,
            'content' => is_string($this->result) ? $this->result : json_encode($this->result),
        ];
    }

    public function toArray(): array
    {
        return [
            'tool_call_id' => $this->toolCallId,
            'tool_name' => $this->toolName,
            'result' => $this->result,
            'success' => $this->success,
            'error' => $this->error,
        ];
    }
}
