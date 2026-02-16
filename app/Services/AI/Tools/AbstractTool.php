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
     * Helper method to create a successful result
     */
    protected function getSuccessResult(mixed $result, string $toolCallId): ToolResult
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
    protected function getErrorResult(string $error, string $toolCallId): ToolResult
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
