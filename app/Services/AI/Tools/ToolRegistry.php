<?php
declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\Interfaces\ToolInterface;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;
use App\Services\AI\Value\AiModel;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
class ToolRegistry
{
    /**
     * @var array<string, ToolInterface>
     */
    private array $tools = [];

    /**
     * @var array<string, MCPToolInterface>
     */
    private array $mcpTools = [];

    /**
     * Register a tool in the registry
     */
    public function register(ToolInterface $tool): void
    {
//        Log::debug('registering TOOLS');

        $name = $tool->getName();

        if ($tool instanceof MCPToolInterface) {
            $this->mcpTools[$name] = $tool;
            Log::info("Registered MCP tool: {$name}", [
                'category' => $tool->getMCPCategory(),
            ]);
        } else {
            $this->tools[$name] = $tool;
//            Log::info("Registered tool: {$name}");
        }
    }

    /**
     * Get a tool by name
     */
    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? $this->mcpTools[$name] ?? null;
    }

    /**
     * Get all registered tools
     *
     * @return array<string, ToolInterface>
     */
    public function getAll(): array
    {
        return array_merge($this->tools, $this->mcpTools);
    }

    /**
     * Get tools available for a specific model
     *
     * @param AiModel $model
     * @return array<string, ToolInterface>
     */
    public function getAvailableForModel(AiModel $model): array
    {
        $allTools = $this->getAll();

        $availableTools = array_filter($allTools, function ($tool) use ($model) {
            return $tool->isEnabledForModel($model);
        });
//        Log::debug('Available Tools', $availableTools);
        return $availableTools;
    }

    /**
     * Get tool definitions for a specific model
     *
     * @param AiModel $model
     * @return array<ToolDefinition>
     */
    public function getDefinitionsForModel(AiModel $model): array
    {
        $tools = $this->getAvailableForModel($model);
        $definitions = [];

        foreach ($tools as $tool) {
            // Skip MCP tools that are not available
            if ($tool instanceof MCPToolInterface && !$tool->isServerAvailable()) {
                Log::warning("MCP tool not available: {$tool->getName()}");
                continue;
            }

            $definitions[] = $tool->getDefinition();
        }

        return $definitions;
    }

    /**
     * Get tools available for a specific provider
     *
     * @param string $providerClass The provider class name
     * @return array<string, ToolInterface>
     */
    public function getAvailableForProvider(string $providerClass): array
    {
        $allTools = $this->getAll();
        $availableTools = [];

        foreach ($allTools as $name => $tool) {
            if ($tool->isAvailableForProvider($providerClass)) {
                $availableTools[$name] = $tool;
            }
        }

        return $availableTools;
    }

    /**
     * Execute a tool by name
     */
    public function execute(string $toolName, array $arguments, string $toolCallId): ToolResult
    {
        $tool = $this->get($toolName);

        if (!$tool) {
            Log::error("Tool not found: {$toolName}");
            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $toolName,
                result: ['error' => 'Tool not found'],
                success: false,
                error: "Tool '{$toolName}' is not registered"
            );
        }

        try {
            return $tool->execute($arguments, $toolCallId);
        } catch (\Exception $e) {
            Log::error("Tool execution failed: {$toolName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $toolName,
                result: ['error' => $e->getMessage()],
                success: false,
                error: $e->getMessage()
            );
        }
    }

    /**
     * Get all MCP tools
     *
     * @return array<string, MCPToolInterface>
     */
    public function getMCPTools(): array
    {
        return $this->mcpTools;
    }

    /**
     * Check if a tool exists
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]) || isset($this->mcpTools[$name]);
    }

    /**
     * Unregister a tool
     */
    public function unregister(string $name): void
    {
        unset($this->tools[$name], $this->mcpTools[$name]);
        Log::info("Unregistered tool: {$name}");
    }
}
