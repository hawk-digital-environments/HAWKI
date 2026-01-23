<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Interfaces;

use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;
use App\Services\AI\Value\AiModel;

interface ToolInterface
{
    /**
     * Get the unique name of the tool
     */
    public function getName(): string;

    /**
     * Get the tool definition including schema
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

    /**
     * Check if this tool is available for a specific provider
     *
     * @param string $providerClass The provider class name (e.g., GwdgClient::class)
     * @return bool True if available for this provider
     */
    public function isAvailableForProvider(string $providerClass): bool;

    /**
     * Check if this tool is enabled for a specific model
     *
     * @param AiModel $model The model to check against
     * @return bool True if enabled for this model
     */
    public function isEnabledForModel(AiModel $model): bool;
}
