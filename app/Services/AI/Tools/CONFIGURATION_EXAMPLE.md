# Tool Calling Configuration Examples

## Basic Model Configuration with Tools

```php
// config/ai_models.php or your model configuration

'models' => [
    [
        'id' => 'meta-llama-3.1-8b-instruct',
        'label' => 'Llama 3.1 8B Instruct',
        'active' => true,
        'tools' => [
            'stream' => true,
            'function_calling' => true,  // Enable function calling
            'enabled_tools' => [          // Specific tools for this model
                'test_tool',
            ],
        ],
    ],
],
```

## Full Configuration with All Tool Options

```php
'models' => [
    [
        'id' => 'gpt-4-turbo',
        'label' => 'GPT-4 Turbo',
        'active' => true,
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            // Core capabilities
            'stream' => true,
            'vision' => true,
            'file_upload' => true,

            // Tool calling
            'function_calling' => true,

            // MCP support
            'mcp' => true,

            // Enabled tools (empty = all available)
            'enabled_tools' => [
                'test_tool',
                'weather_tool',
                'database_query_tool',
                'mcp_filesystem_tool',
            ],
        ],
    ],
],
```

## Configuration: All Tools Enabled

If you want all registered tools to be available:

```php
'tools' => [
    'function_calling' => true,
    // Don't specify enabled_tools, or set it to empty array
],
```

Or:

```php
'tools' => [
    'function_calling' => true,
    'enabled_tools' => [],  // Empty = all tools
],
```

## Configuration: No Tools

To disable tool calling:

```php
'tools' => [
    'function_calling' => false,
    // Or simply omit function_calling
],
```

## Provider-Specific Configuration

Different providers may have different tool calling capabilities:

```php
'providers' => [
    'gwdg' => [
        'models' => [
            [
                'id' => 'llama-3.1-8b',
                'tools' => [
                    'function_calling' => true,
                    'enabled_tools' => ['test_tool'],
                ],
            ],
        ],
    ],

    'openai' => [
        'models' => [
            [
                'id' => 'gpt-4',
                'tools' => [
                    'function_calling' => true,
                    'mcp' => true,  // OpenAI supports MCP
                    'enabled_tools' => [
                        'test_tool',
                        'mcp_browser_tool',
                    ],
                ],
            ],
        ],
    ],
],
```

## MCP Tools Configuration

To configure MCP (Model Context Protocol) tools:

```php
// config/ai.php

return [
    'mcp_tools' => [
        [
            'class' => \App\Services\AI\Tools\Implementations\MCPFileSystemTool::class,
            'server' => [
                'command' => 'node',
                'args' => ['/path/to/mcp-server.js'],
                'env' => [
                    'MCP_API_KEY' => env('MCP_API_KEY'),
                ],
            ],
        ],
    ],
];
```

## Testing Configuration

For testing, use minimal configuration:

```php
'models' => [
    [
        'id' => 'test-model',
        'label' => 'Test Model',
        'tools' => [
            'function_calling' => true,
            'enabled_tools' => ['test_tool'],  // Only test tool
        ],
    ],
],
```

## Production Configuration Example

```php
'models' => [
    // Production model with carefully selected tools
    [
        'id' => 'gpt-4-turbo',
        'label' => 'GPT-4 Turbo (Production)',
        'active' => true,
        'external' => true,  // Available for external apps
        'tools' => [
            'stream' => true,
            'vision' => true,
            'function_calling' => true,

            // Only enable vetted, production-ready tools
            'enabled_tools' => [
                'weather_tool',
                'company_database_tool',
                'analytics_tool',
            ],
        ],
    ],

    // Internal model with more tools for staff
    [
        'id' => 'gpt-4-internal',
        'label' => 'GPT-4 (Internal)',
        'active' => true,
        'external' => false,  // Only for internal use
        'tools' => [
            'stream' => true,
            'vision' => true,
            'function_calling' => true,

            // More tools available for internal users
            'enabled_tools' => [
                'test_tool',
                'weather_tool',
                'company_database_tool',
                'analytics_tool',
                'admin_tool',
                'debug_tool',
            ],
        ],
    ],
],
```

## Environment-Specific Configuration

```php
// .env
AI_TOOLS_ENABLED=true
AI_MCP_ENABLED=false
AI_TEST_TOOL_ENABLED=true

// config/ai_models.php
'tools' => [
    'function_calling' => env('AI_TOOLS_ENABLED', false),
    'mcp' => env('AI_MCP_ENABLED', false),
    'enabled_tools' => env('AI_TEST_TOOL_ENABLED', false)
        ? ['test_tool']
        : [],
],
```

## Dynamic Tool Enabling

You can enable/disable tools programmatically:

```php
use App\Services\AI\Tools\ToolRegistry;

$registry = app(ToolRegistry::class);

// Unregister a tool temporarily
$registry->unregister('dangerous_tool');

// Re-register when needed
$registry->register(new DangerousTool());
```

## Migration from Hardcoded Tools

If you had hardcoded tools like the old `get_weather` example:

### Before:
```php
// In GwdgRequestConverter
$payload['tools'] = [
    'type' => 'function',
    'function' => [
        'name' => 'get_weather',
        // ... hardcoded definition
    ],
];
```

### After:
```php
// 1. Create WeatherTool.php
class WeatherTool extends AbstractTool { /* ... */ }

// 2. Register in ToolServiceProvider
$registry->register(new WeatherTool());

// 3. Enable in model config
'tools' => [
    'function_calling' => true,
    'enabled_tools' => ['weather_tool'],
],

// 4. GwdgRequestConverter automatically includes it
// (No hardcoded tools needed!)
```
