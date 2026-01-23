<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\MCP;

use App\Services\AI\Tools\Interfaces\MCPToolInterface;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;
use App\Services\AI\Value\AiModel;
use Illuminate\Support\Facades\Log;

/**
 * Abstract adapter for MCP (Model Context Protocol) tools
 *
 * MCP tools communicate with external servers that provide tool functionality.
 * This adapter handles the communication and protocol translation.
 */
abstract class MCPToolAdapter implements MCPToolInterface
{
    protected array $serverConfig = [];

    public function __construct(array $serverConfig = [])
    {
        $this->serverConfig = $serverConfig;
    }

    /**
     * @inheritDoc
     */
    public function getMCPServerConfig(): array
    {
        return $this->serverConfig;
    }

    /**
     * @inheritDoc
     */
    public function isServerAvailable(): bool
    {
        // Server availability is checked at execution time, not boot time
        return !empty($this->serverConfig['server_url'] ?? '');
    }

    /**
     * @inheritDoc
     */
    public function isAvailableForProvider(string $providerClass): bool
    {
        // MCP tools should work with providers that support function calling
        // Override in subclass if you need provider-specific logic
        return true;
    }

    /**
     * @inheritDoc
     *
     * MCP tools are ONLY available to models that explicitly have 'mcp' => true
     * This ensures providers like GWDG (without MCP support) don't see these tools
     */
    public function isEnabledForModel(AiModel $model): bool
    {
        // MCP tools require both function_calling AND mcp support
        if (!$model->hasTool('function_calling')) {
            return false;
        }

        // CRITICAL: Only models with explicit MCP support can use MCP tools
        if (!$model->hasTool('mcp')) {
            return false;
        }

        // Check if this specific tool is enabled
        $enabledTools = $model->getTools()['enabled_tools'] ?? [];

        // Empty array means all tools (including MCP) are available
        if (empty($enabledTools)) {
            return true;
        }

        // Check if this specific MCP tool is in the enabled list
        return in_array($this->getName(), $enabledTools, true);
    }

    /**
     * Execute the MCP tool by communicating with the MCP server
     *
     * This is called when the AI model requests this tool.
     * The implementation should:
     * 1. Validate the server configuration
     * 2. Send request to the MCP server
     * 3. Parse and return the response
     *
     * @inheritDoc
     */
    abstract public function execute(array $arguments, string $toolCallId): ToolResult;
}
