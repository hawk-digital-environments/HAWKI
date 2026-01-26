<?php
declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\Interfaces\ToolInterface;
use App\Services\AI\Tools\Value\ToolResult;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

/**
 * Tool Registry
 *
 * Manages tool registration and execution.
 * Tool availability is determined by model configuration, not by the registry.
 */
#[Singleton]
class ToolRegistry
{
    /**
     * @var array<string, ToolInterface>
     */
    private array $tools = [];

    /**
     * Register a tool in the registry
     */
    public function register(ToolInterface $tool): void
    {
        $name = $tool->getName();
        $this->tools[$name] = $tool;

//        if ($tool instanceof MCPToolInterface) {
//            Log::debug("Registered MCP tool: {$name}");
//        } else {
//            Log::debug("Registered tool: {$name}");
//        }
    }

    /**
     * Get a tool by name
     */
    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Get all registered tools
     *
     * @return array<string, ToolInterface>
     */
    public function getAll(): array
    {
        return $this->tools;
    }

    /**
     * Get all MCP tools
     *
     * @return array<string, MCPToolInterface>
     */
    public function getMCPTools(): array
    {
        return array_filter($this->tools, fn($tool) => $tool instanceof MCPToolInterface);
    }

    /**
     * Check if a tool exists
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Unregister a tool
     */
    public function unregister(string $name): void
    {
        unset($this->tools[$name]);
        Log::info("Unregistered tool: {$name}");
    }

    /**
     * Execute a tool by name
     *
     * Handles both regular tools and MCP tools uniformly.
     * The tool's execute() method will handle the specifics.
     */
    public function execute(string $toolName, array $arguments, string $toolCallId): ToolResult
    {
        $tool = $this->get($toolName);

        if (!$tool) {
//            Log::error("Tool not found: {$toolName}");
            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $toolName,
                result: ['error' => 'Tool not found'],
                success: false,
                error: "Tool '{$toolName}' is not registered"
            );
        }

        try {
//            Log::debug("Executing tool: {$toolName}", [
//                'is_mcp' => $tool instanceof MCPToolInterface,
//                'arguments' => $arguments,
//            ]);

            return $tool->execute($arguments, $toolCallId);
        } catch (\Exception $e) {
//            Log::error("Tool execution failed: {$toolName}", [
//                'error' => $e->getMessage(),
//                'trace' => $e->getTraceAsString(),
//            ]);

            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $toolName,
                result: ['error' => $e->getMessage()],
                success: false,
                error: $e->getMessage()
            );
        }
    }
}
