<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Function Calling Tools
    |--------------------------------------------------------------------------
    |
    | Tools with LOCAL logic that run within your application.
    | These are NOT MCP tools - they execute code in your project.
    |
    | DEPLOYMENT ONLY: This list is read by `php artisan ai:tools:sync` to
    | populate the ai_tools table. It is NOT read at runtime.
    |
    | To add a function-calling tool:
    | 1. Create a class in app/Services/AI/Tools/Implementations/
    | 2. Implement ToolInterface (or extend AbstractTool)
    | 3. Add the class to the array below
    | 4. Run: php artisan ai:tools:sync --function-only
    |
    */
    'available_tools' => [
        \App\Services\AI\Tools\Implementations\TestTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Servers (Auto-Discovered Tools)
    |--------------------------------------------------------------------------
    |
    | MCP (Model Context Protocol) servers that provide tools automatically.
    |
    | DEPLOYMENT ONLY: This list is read by `php artisan ai:tools:sync` to
    | connect to each server, discover its tools, and store them in the DB.
    | It is NOT read at runtime.
    |
    | To add a new MCP server:
    | 1. Add server configuration below with its URL
    | 2. Run: php artisan ai:tools:sync --mcp-only
    |    (or use: php artisan tools:add-mcp-server for interactive setup)
    |
    | Authentication:
    | - If your MCP server requires authentication, add 'api_key' parameter
    | - The API key will be sent as: Authorization: Bearer <API_KEY>
    | - Store API keys in .env file for security
    |
    | Tool names are prefixed with server_label to avoid conflicts.
    | Example: "search" from "hawki-rag" becomes "hawki-rag-search"
    |
    */
    'mcp_servers' => [
        // RAWKI MCP Server - provides web search and knowledge base tools
        'hawki-rag' => [
            'url'               => env('HAWKI_RAG_MCP_API_URL', 'http://localhost:8080/mcp/rawki'),
            'server_label'      => 'hawki-rag',
            'description'       => 'HAWKI Web Search and Knowledge Base',
            'require_approval'  => 'never',
            'timeout'           => 30,
            'discovery_timeout' => 90,
            'api_key'           => env('HAWKI_RAG_MCP_API_KEY'),
        ],

        // Example: Add another MCP server
        // 'my_mcp_server' => [
        //     'url'               => env('MY_MCP_SERVER_URL', 'http://localhost:3000/mcp'),
        //     'server_label'      => 'my_server',
        //     'description'       => 'My Custom MCP Server',
        //     'require_approval'  => 'never',
        //     'timeout'           => 30,
        //     'discovery_timeout' => 5,
        //     'api_key'           => env('MY_MCP_API_KEY'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Status Filtering
    |--------------------------------------------------------------------------
    |
    | When enabled, only tools whose status is 'active' in the database will
    | be registered in the ToolRegistry and made available to AI models.
    | Tools marked 'inactive' (e.g. by the ai:tools:check-status command) will
    | be silently skipped.
    |
    | Set to false to load all tools regardless of their status field.
    | Useful during development or when the status command is not scheduled.
    |
    */
    'check_tool_status' => env('CHECK_TOOL_STATUS', true),

];
