# MCP Integration Architecture

## Overview

This document explains the correct architecture for integrating Model Context Protocol (MCP) tools with AI providers like OpenAI and GWDG.

## Key Principle

**Laravel acts as the MCP client and orchestrates all tool calls. AI providers do NOT directly call MCP servers.**

## Architecture

### Execution Strategies

HAWKI supports two tool execution strategies:

1. **`function_call`**: Laravel executes local code (e.g., TestTool)
2. **`mcp`**: Laravel calls external MCP server (e.g., DmcpTool for dice rolling)

**Important**: Both strategies send tools to AI providers in the SAME format (as function tools). The difference is only in how Laravel executes them.

### Workflow

```
1. User sends message
   ↓
2. Laravel builds tool definitions
   - function_call tools → get ToolDefinition from local tools
   - mcp tools → get ToolDefinition from MCP tools
   ↓
3. Laravel converts ALL to provider format
   - OpenAI Response API: FLAT format {type, name, description, parameters}
   - GWDG/Chat API: NESTED format {type, function: {name, description, parameters}}
   ↓
4. Send to AI provider (OpenAI/GWDG/etc.)
   - All tools sent as type: 'function'
   ↓
5. AI returns tool_call
   - Laravel parses from provider response format
   ↓
6. Laravel executes tool
   - function_call strategy: Runs local code
   - mcp strategy: Calls MCP server via MCPSSEClient
   ↓
7. Laravel formats result
   - Converts to provider message format
   ↓
8. Laravel sends follow-up request
   - Includes tool result in messages
   ↓
9. AI returns final response
```

## Implementation

### ToolAwareConverter Trait

Located at: `app/Services/AI/Providers/Traits/ToolAwareConverter.php`

**Methods:**
```php
// Get tools with function_call strategy
protected function buildFunctionCallTools(AiModel $model): array

// Get tools with mcp strategy
protected function buildMCPTools(AiModel $model): array

// Check if tools should be disabled
protected function shouldDisableTools(array $rawPayload): bool
```

### Provider Converters

#### OpenAI Response API

```php
// Merge both strategies
$toolDefinitions = array_merge(
    $this->buildFunctionCallTools($model),
    $this->buildMCPTools($model)
);

// Convert to FLAT format
foreach ($toolDefinitions as $toolDef) {
    $tools[] = $toolDef->toOpenAiResponseFormat();
}
```

#### GWDG (Chat API)

```php
// Merge both strategies
$toolDefinitions = array_merge(
    $this->buildFunctionCallTools($model),
    $this->buildMCPTools($model)
);

// Convert to NESTED format
$payload['tools'] = array_map(fn($toolDef) => [
    'type' => 'function',
    'function' => $toolDef->toOpenAiChatFormat(),
], $toolDefinitions);
```

### Tool Execution

#### AbstractTool (Local Execution)

```php
abstract class AbstractTool implements ToolInterface
{
    abstract public function execute(array $arguments, string $toolCallId): ToolResult;
}
```

#### AbstractMCPTool (MCP Execution)

```php
abstract class AbstractMCPTool extends AbstractTool implements MCPToolInterface
{
    final public function execute(array $arguments, string $toolCallId): ToolResult
    {
        if (!$this->isServerAvailable()) {
            return $this->error('MCP server not available', $toolCallId);
        }

        try {
            $result = $this->executeMCP($arguments);  // Calls external MCP server
            return $this->success($result, $toolCallId);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $toolCallId);
        }
    }

    abstract protected function executeMCP(array $arguments): mixed;
}
```

## Configuration

### config/tools.php

```php
return [
    'available_tools' => [
        \App\Services\AI\Tools\Implementations\TestTool::class,
        \App\Services\AI\Tools\Implementations\DmcpTool::class,
    ],

    'mcp_servers' => [
        'dice_roll' => [
            'url' => env('DMCP_SERVER_URL', 'https://dmcp-server.deno.dev/sse'),
            'server_label' => 'dnd_dice_roller',  // Machine-readable identifier
            'description' => 'D&D Dice Roller',    // Human-readable description
            'require_approval' => 'never',
        ],
    ],
];
```

### config/model_lists/openai_models.php

```php
'gpt-4.1' => [
    'tools' => [
        'stream' => 'native',
        'file_upload' => 'native',
        'vision' => 'native',
        'test_tool' => 'function_call',  // Local execution
        'dice_roll' => 'mcp',            // MCP server execution
    ],
],
```

### config/model_lists/gwdg_models.php

```php
'gpt-4o' => [
    'tools' => [
        'stream' => 'native',
        'file_upload' => 'native',
        'test_tool' => 'function_call',
        'dice_roll' => 'function_call',  // GWDG uses function_call for MCP tools
    ],
],
```

## Common Misconceptions

### ❌ WRONG: AI Provider Calls MCP Server Directly

```php
// This was the wrong approach
$tools[] = [
    'type' => 'mcp',  // ❌ Providers don't support this
    'server_url' => 'https://mcp-server.com',
    'server_label' => 'my_server',
];
```

### ✅ CORRECT: Laravel Orchestrates MCP Calls

```php
// Both strategies send as function tools
$tools[] = [
    'type' => 'function',
    'name' => 'dice_roll',
    'description' => 'Roll D&D dice',
    'parameters' => {...},
];

// Laravel decides execution strategy based on config
// When tool is called, AbstractMCPTool.execute() calls MCP server
```

## Adding a New MCP Tool

1. **Create tool class** extending `AbstractMCPTool`:
```php
class MyMcpTool extends AbstractMCPTool
{
    public function getName(): string {
        return 'my_tool';
    }

    public function getDefinition(): ToolDefinition {
        return new ToolDefinition(
            name: 'my_tool',
            description: 'My tool description',
            parameters: [...],
        );
    }

    public function getMCPServerConfig(): array {
        return [
            'url' => 'https://my-mcp-server.com',
            'server_label' => 'my_tool_server',
            'description' => 'My Tool Server',
        ];
    }

    protected function executeMCP(array $arguments): mixed {
        $client = new MCPSSEClient($this->serverConfig['url']);
        return $client->callTool('tool_name', $arguments);
    }
}
```

2. **Register in config/tools.php**:
```php
'available_tools' => [
    \App\Services\AI\Tools\Implementations\MyMcpTool::class,
],

'mcp_servers' => [
    'my_tool' => [
        'url' => env('MY_TOOL_SERVER_URL', 'https://my-mcp-server.com'),
        'server_label' => 'my_tool_server',
        'description' => 'My Tool Server',
    ],
],
```

3. **Configure in model configs**:
```php
'tools' => [
    'my_tool' => 'mcp',  // Use mcp strategy for external execution
],
```

## Benefits of This Architecture

1. **Unified Interface**: AI providers only see function tools
2. **Provider Agnostic**: Works with any provider that supports function calling
3. **Flexible Execution**: Switch between local and external execution via config
4. **Laravel Control**: Full control over tool execution and error handling
5. **Security**: Laravel can validate, log, and control all MCP calls
6. **Caching**: Laravel can cache MCP responses if needed
7. **Error Handling**: Consistent error handling across all tools

## Files Modified

```
✅ app/Services/AI/Providers/Traits/ToolAwareConverter.php
   - Added buildMCPTools() method
   - Returns ToolDefinition objects for MCP tools

✅ app/Services/AI/Providers/OpenAi/OpenAiRequestConverter.php
   - Merges function_call and mcp tools
   - Sends all as type: 'function' in FLAT format

✅ app/Services/AI/Providers/Gwdg/GwdgRequestConverter.php
   - Merges function_call and mcp tools
   - Sends all as type: 'function' in NESTED format

✅ config/tools.php
   - Separated server_label (machine-readable) from description (human-readable)

✅ app/Services/AI/Tools/Implementations/DmcpTool.php
   - Updated default config with server_label and description
```

## Status

✅ Architecture implemented and working
✅ Local function calling works (TestTool)
✅ MCP tool calling works (DmcpTool for dice rolling)
✅ Multi-round iteration works
✅ Unified across OpenAI Response API and GWDG Chat API
