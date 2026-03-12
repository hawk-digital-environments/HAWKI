<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Value;

readonly class ToolDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,  // JSON Schema format
        public bool $strict = false
    )
    {
    }

    /**
     * Convert to OpenAI Response API format (flat structure)
     * Response API expects: {type: 'function', name: '...', description: '...', parameters: {...}}
     */
    public function toOpenAiResponseFormat(): array
    {
        return [
            'type' => 'function',
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
        ];
    }

    /**
     * Convert to OpenAI Chat Completions API format (nested structure)
     * Chat API expects function definition to be wrapped by converter
     * Returns just: {name: '...', description: '...', parameters: {...}, strict: bool}
     */
    public function toOpenAiChatFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'strict' => $this->strict,
        ];
    }

    /**
     * Convert to Google tool format
     */
    public function toGoogleFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
        ];
    }

    /**
     * Convert to Anthropic tool format
     */
    public function toAnthropicFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->parameters,
        ];
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'strict' => $this->strict,
        ];
    }
}
