<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Interfaces;

use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;

/**
 * Tool Interface
 *
 * Defines the contract for all AI tools.
 * Tool availability is determined by model configuration (model_lists/*.php),
 * not by the tool itself.
 */
interface ToolInterface
{
    /**
     * Get the unique name of the tool
     * This name is used in model configuration to reference the tool
     */
    public function getName(): string;

    /**
     * Get the tool definition including schema
     * Used to build the tool payload for model requests
     */
    public function getDefinition(): ToolDefinition;

    /**
     * Execute the tool with given arguments
     *
     * @param array $arguments The arguments passed by the model
     * @param string $toolCallId The ID of the tool call for tracking
     * @return ToolResult The result of the tool execution
     */
    public function execute(array $arguments, string $toolCallId): ToolResult;
}
