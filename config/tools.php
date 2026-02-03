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
    | Use this for:
    | - Custom business logic tools
    | - Database queries
    | - Internal API calls
    | - Calculations and transformations
    |
    | To add a function calling tool:
    | 1. Create class in app/Services/AI/Tools/Implementations/
    | 2. Implement ToolInterface (or extend AbstractTool)
    | 3. Add the class to the array below
    | 4. Configure in model configs (model_lists/*.php)
    |
    | Example: DiceRollTool, CalculatorTool, DatabaseQueryTool
    |
    */
    'available_tools' => [
        // Add your function calling tools here
         \App\Services\AI\Tools\Implementations\TestTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Servers (Auto-Discovered Tools)
    |--------------------------------------------------------------------------
    |
    | MCP (Model Context Protocol) servers provide tools automatically.
    | Just add the server URL - tools are discovered and configured for you!
    |
    | 🚀 To add a new MCP server (3 simple steps):
    | 1. Add server configuration below with its URL
    | 2. (Optional) Run `php artisan tools:discover` to test connection
    | 3. Restart your application - tools are auto-registered!
    |
    | 🔐 Authentication:
    | - If your MCP server requires authentication, add 'api_key' parameter
    | - The API key will be sent as: Authorization: Bearer <API_KEY>
    | - Store API keys in .env file for security
    |
    | Tool names are prefixed with server_label to avoid conflicts.
    | Example: "search" from "rawki_search" becomes "rawki_search.search"
    |
    | All tools are auto-discovered - no manual classes needed!
    |
    */
    'mcp_servers' => [
        // RAWKI MCP Server - provides web search and knowledge base tools
        'rawki' => [
            'url' => env('RAWKI_MCP_SERVER_URL', 'http://localhost:8080/mcp/rawki'),
            'server_label' => 'rawki',
            'description' => 'RAWKI Web Search and Knowledge Base',
            'require_approval' => 'never',
            'timeout' => 30,  // Timeout for tool execution (seconds)
            'discovery_timeout' => 5,  // Timeout for tool discovery (seconds)
            'api_key' => env('RAWKI_MCP_API_KEY'),  // Optional: API key for authentication
        ],

        // Example: Add another MCP server
        // 'my_mcp_server' => [
        //     'url' => env('MY_MCP_SERVER_URL', 'http://localhost:3000/mcp'),
        //     'server_label' => 'my_server',
        //     'description' => 'My Custom MCP Server',
        //     'require_approval' => 'never',
        //     'timeout' => 30,
        //     'discovery_timeout' => 5,
        //     'api_key' => env('MY_MCP_API_KEY'),  // Optional: API key for authentication
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache discovered MCP tool schemas (in seconds).
    | Caching improves boot time by avoiding repeated server queries.
    |
    | To refresh tools after MCP server changes:
    | - Run: php artisan tools:discover --force
    | - Or wait for cache to expire
    |
    | Default: 3600 (1 hour)
    | Set to 0 to disable caching (not recommended for production)
    |
    */
    'mcp_cache_ttl' => env('MCP_CACHE_TTL', 3600),

];
