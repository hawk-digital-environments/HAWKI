<?php
declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Services\AI\Tools\Interfaces\ToolInterface;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;

/**
 * Abstract base class for tools to reduce boilerplate
 *
 * Tool availability is now determined by model configuration (model_lists/*.php),
 * not by the tool itself. Tools should focus solely on their execution logic.
 */
abstract class AbstractTool implements ToolInterface
{
    /**
     * Get the tool's unique identifier
     */
    abstract public function getName(): string;

    /**
     * Get the tool's definition for model payload
     */
    abstract public function getDefinition(): ToolDefinition;

    /**
     * Execute the tool logic
     */
    abstract public function execute(array $arguments, string $toolCallId): ToolResult;

    /**
     * Helper method to create a successful result
     */
    protected function success(mixed $result, string $toolCallId): ToolResult
    {
        return new ToolResult(
            toolCallId: $toolCallId,
            toolName: $this->getName(),
            result: $result,
            success: true,
            error: null
        );
    }

    /**
     * Helper method to create an error result
     */
    protected function error(string $error, string $toolCallId): ToolResult
    {
        return new ToolResult(
            toolCallId: $toolCallId,
            toolName: $this->getName(),
            result: null,
            success: false,
            error: $error
        );
    }
}
