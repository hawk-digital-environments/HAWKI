<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Traits;

use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Value\AiModel;
use Illuminate\Support\Facades\Log;

/**
 * AiTool-Aware Converter Trait
 *
 * Provides methods to build tool definitions based on model configuration.
 * Supports:
 * - Native capabilities: 'capability' => true (model handles natively)
 * - AiTool-based capabilities: 'capability' => 'tool-name' (project handles via tools)
 */
trait ToolAwareConverter
{
    /**
     * Build all tool definitions for the model
     *
     * Iterates through model capabilities and builds tool definitions
     * for non-native capabilities.
     *
     * Logic:
     * - If value is true/'native' → Skip (model handles it)
     * - If value is false → Skip (not supported)
     * - If value is string → Look up tool and add definition
     *
     * @param AiModel $model The model to build tools for
     * @return array Array of ToolDefinition objects
     */
    protected function buildAllTools(AiModel $model): array
    {
        $tools = [];
        $modelTools = $model->getTools();
        $registry = app(ToolRegistry::class);

        foreach ($modelTools as $capability => $value) {
            // Skip native capabilities (bool or 'native' string)
            if ($value === true || $value === 'native') {
                continue;
            }

            // Skip disabled capabilities
            if ($value === false) {
                continue;
            }

            // Value should be a tool name
            if (!is_string($value)) {
                Log::warning("Invalid tool value for capability '{$capability}': " . gettype($value));
                continue;
            }

            $toolName = $value;

            // Get tool from registry
            $tool = $registry->get($toolName);
            if (!$tool) {
                Log::warning("AiTool '{$toolName}' not found in registry for capability '{$capability}'");
                continue;
            }

            // Check if MCP server is available (for MCP tools)
            if ($tool instanceof MCPToolInterface && !$tool->isServerAvailable()) {
                Log::warning("MCP server not available for tool: {$toolName}");
                continue;
            }

            $tools[] = $tool->getDefinition();
//            Log::debug("Added tool for capability '{$capability}': {$toolName}");
        }

        return $tools;
    }

    public function buildSelectedTools(AiModel $model, array $capabilities): array
    {
        $tools = [];
        $modelTools = $model->getTools();
        $registry = app(ToolRegistry::class);

        foreach ($modelTools as $modelCapability => $toolName) {
            if($toolName === 'native') continue;

            foreach ($capabilities as $capability) {
                if($modelCapability === $capability) {

                    // Get tool from registry
                    $tool = $registry->get($toolName);
                    if (!$tool) {
                        Log::warning("AiTool '{$toolName}' not found in registry for capability '{$capability}'");
                        continue;
                    }

                    // Check if MCP server is available (for MCP tools)
                    if ($tool instanceof MCPToolInterface && !$tool->isServerAvailable()) {
                        Log::warning("MCP server not available for tool: {$toolName}");
                        continue;
                    }

                    $tools[] = $tool->getDefinition();
                }
            }
        }
        return $tools;
    }



    /**
     * Build tool definitions for function calling
     * @deprecated Use buildAllTools() instead
     */
    protected function buildFunctionCallTools(AiModel $model): array
    {
        return $this->buildAllTools($model);
    }

    /**
     * Build MCP tool definitions
     * @deprecated Use buildAllTools() instead
     */
    protected function buildMCPTools(AiModel $model): array
    {
        return [];  // All tools are now built in buildAllTools()
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
}
