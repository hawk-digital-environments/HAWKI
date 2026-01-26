<?php
declare(strict_types=1);

namespace App\Services\AI\Tools\Enums;

/**
 * Tool Execution Strategy
 *
 * Defines how a tool should be executed for a given model.
 */
enum ExecutionStrategy: string
{
    /**
     * Model handles the tool internally (e.g., Google's native web search)
     */
    case NATIVE = 'native';

    /**
     * Model supports MCP protocol and communicates with MCP server directly
     * (e.g., OpenAI with MCP support)
     */
    case MCP = 'mcp';

    /**
     * HAWKI orchestrates the tool via function calling
     * Works for both regular tools and MCP tools that need orchestration
     */
    case FUNCTION_CALL = 'function_call';

    /**
     * Tool is not supported for this model
     */
    case UNSUPPORTED = 'unsupported';

    /**
     * Check if this strategy requires adding the tool to the request payload
     */
    public function requiresToolDefinition(): bool
    {
        return $this === self::FUNCTION_CALL;
    }

    /**
     * Check if this strategy requires MCP server configuration in payload
     */
    public function requiresMCPConfig(): bool
    {
        return $this === self::MCP;
    }

    /**
     * Check if the tool is available (not unsupported)
     */
    public function isAvailable(): bool
    {
        return $this !== self::UNSUPPORTED;
    }
}
