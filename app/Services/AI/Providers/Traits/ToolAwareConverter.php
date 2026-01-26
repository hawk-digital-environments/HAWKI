<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Traits;

use App\Services\AI\Tools\Enums\ExecutionStrategy;
use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Value\AiModel;
use Illuminate\Support\Facades\Log;

/**
 * Tool-Aware Converter Trait
 *
 * Provides methods to build tool definitions and MCP server configurations
 * based on model configuration and execution strategies.
 *
 * Replaces the old CapabilityAwareConverter approach with a simpler,
 * config-driven system.
 */
trait ToolAwareConverter
{
    /**
     * Build tool definitions for function calling (strategy: function_call)
     *
     * Returns tools that should be added to the model's request payload
     * for function calling execution.
     *
     * @param AiModel $model The model to build tools for
     * @return array Array of tool definitions in the provider's format
     */
    protected function buildFunctionCallTools(AiModel $model): array
    {
        $tools = [];
        $modelTools = $model->getTools();
        $registry = app(ToolRegistry::class);

        foreach ($modelTools as $toolName => $strategy) {
            // Skip basic features (deprecated boolean values)
            if (is_bool($strategy)) {
                continue;
            }

            // Only include tools with function_call strategy
            if ($strategy !== ExecutionStrategy::FUNCTION_CALL->value) {
                continue;
            }

            $tool = $registry->get($toolName);
            if ($tool) {
                $tools[] = $tool->getDefinition();
                Log::debug("Added function call tool: {$toolName}");
            } else {
                Log::warning("Tool not found in registry: {$toolName}");
            }
        }

        return $tools;
    }

    /**
     * Build MCP server configurations (strategy: mcp)
     *
     * Returns MCP server configs that should be added to the model's request payload
     * for direct MCP communication.
     *
     * @param AiModel $model The model to build MCP servers for
     * @return array Array of MCP server configurations
     */
    protected function buildMCPServers(AiModel $model): array
    {
        $servers = [];
        $modelTools = $model->getTools();
        $registry = app(ToolRegistry::class);

        foreach ($modelTools as $toolName => $strategy) {
            // Skip basic features (deprecated boolean values)
            if (is_bool($strategy)) {
                continue;
            }

            // Only include tools with mcp strategy
            if ($strategy !== ExecutionStrategy::MCP->value) {
                continue;
            }

            $tool = $registry->get($toolName);

            // Only MCP tools can be used with MCP strategy
            if ($tool instanceof MCPToolInterface) {
                if ($tool->isServerAvailable()) {
                    $servers[] = $tool->getMCPServerConfig();
                    Log::debug("Added MCP server: {$toolName}");
                } else {
                    Log::warning("MCP server not available: {$toolName}");
                }
            } else {
                Log::warning("Tool is not an MCP tool: {$toolName}");
            }
        }

        return $servers;
    }

    /**
     * Check if tool features should be disabled for this request
     *
     * @param array $rawPayload The raw request payload
     * @return bool True if tools should be disabled
     */
    protected function shouldDisableTools(array $rawPayload): bool
    {
        return $rawPayload['_disable_tools'] ?? false;
    }

    /**
     * Check if model has any tools configured with the given strategy
     *
     * @param AiModel $model The model to check
     * @param ExecutionStrategy $strategy The strategy to check for
     * @return bool True if model has tools with this strategy
     */
    protected function hasToolsWithStrategy(AiModel $model, ExecutionStrategy $strategy): bool
    {
        $modelTools = $model->getTools();

        foreach ($modelTools as $toolName => $toolStrategy) {
            if (is_bool($toolStrategy)) {
                continue;
            }

            if ($toolStrategy === $strategy->value) {
                return true;
            }
        }

        return false;
    }
}
