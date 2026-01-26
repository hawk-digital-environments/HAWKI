<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Tools
    |--------------------------------------------------------------------------
    |
    | List of tool implementations that should be registered and available
    | for use by AI models. Each tool must implement ToolInterface.
    |
    | To add a new tool:
    | 1. Create the tool class in app/Services/AI/Tools/Implementations/
    | 2. Implement ToolInterface (or extend AbstractTool/AbstractMCPTool)
    | 3. Add the class to the array below
    | 4. Configure the tool in model configs (model_lists/*.php)
    |
    */
    'available_tools' => [
        \App\Services\AI\Tools\Implementations\TestTool::class,
        \App\Services\AI\Tools\Implementations\DmcpTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Descriptions
    |--------------------------------------------------------------------------
    |
    | Optional metadata for documentation purposes.
    | Maps tool names to human-readable descriptions.
    |
    */
    'tool_descriptions' => [
        'test_tool' => 'Test tool for validating function calling capabilities',
        'dice_roll' => 'D&D style dice roller via MCP server',
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server Configurations
    |--------------------------------------------------------------------------
    |
    | Default MCP server configurations for tools.
    | Tools can override these in their getMCPServerConfig() method.
    |
    */
    'mcp_servers' => [
        'dice_roll' => [
            'url' => env('DMCP_SERVER_URL', 'https://dmcp-server.deno.dev/sse'),
            'server_label' => 'dnd_dice_roller',  // Machine-readable: letters, digits, hyphens, underscores only
            'description' => 'D&D Dice Roller',    // Human-readable description
            'require_approval' => 'never',
        ],
    ],
];
