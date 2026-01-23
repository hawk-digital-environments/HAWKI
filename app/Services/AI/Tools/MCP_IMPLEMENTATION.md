# MCP (Model Context Protocol) Implementation

## Overview

HAWKI supports connecting to external MCP servers that provide additional tool functionality. MCP servers follow the [Model Context Protocol specification](https://modelcontextprotocol.io/) and communicate via SSE (Server-Sent Events).

**Key Design Principles:**
1. MCP tools are sent to the AI model as regular tool definitions
2. The model decides when to call MCP tools (just like built-in tools)
3. When called, HAWKI forwards the request to the external MCP server
4. The MCP server response is returned to the model as a tool result
5. Only models with `mcp: true` in configuration can access MCP tools

**Provider Support:**
- ✅ OpenAI (when implemented) - Native MCP support
- ✅ Google (when implemented) - Native MCP support
- ✅ Anthropic (when implemented) - Native MCP support
- ❌ GWDG - No MCP support, won't see MCP tools

## Architecture

### Request Flow

```
1. User Message
   ↓
2. HAWKI builds payload
   ├─► Built-in tools (TestTool, etc.) → Sent to model
   └─► MCP tools (DmcpTool, etc.) → Sent ONLY if model has 'mcp: true'
   ↓
3. Model receives tools and decides which to call
   ↓
4a. If model calls built-in tool:
   Execute locally → Return result

4b. If model calls MCP tool:
   ┌─────────────────────┐
   │  DmcpTool.execute() │
   └──────────┬──────────┘
              │
              ▼ SSE/HTTP
   ┌─────────────────────┐
   │   MCP Server        │
   │ (dmcp-server.deno)  │
   └──────────┬──────────┘
              │
              ▼ JSON-RPC Response
   Return result to model
   ↓
5. Model uses tool result(s) to generate final response
```

### Model Configuration

```php
// Models WITH MCP support (OpenAI, Google, Anthropic)
'tools' => [
    'function_calling' => true,
    'mcp' => true,  // ← ENABLES MCP TOOLS
    'enabled_tools' => ['dmcp_roll_dice'],
],

// Models WITHOUT MCP support (GWDG)
'tools' => [
    'function_calling' => true,
    // No 'mcp' flag → MCP tools are filtered out
    'enabled_tools' => ['test_tool'],
],
```

## Components

### 1. MCPSSEClient

**Purpose:** Handles SSE communication with MCP servers

**Location:** `app/Services/AI/Tools/MCP/MCPSSEClient.php`

**Key Methods:**
- `request(method, params)` - Send JSON-RPC request to MCP server
- `listTools()` - Get available tools from server
- `callTool(name, arguments)` - Execute a tool on the server
- `isAvailable()` - Health check

**Example Usage:**
```php
$client = new MCPSSEClient('https://dmcp-server.deno.dev/sse');

// List available tools
$tools = $client->listTools();

// Call a tool
$result = $client->callTool('roll', ['dice' => '1d20']);
```

### 2. MCPToolAdapter

**Purpose:** Abstract base class for MCP tool implementations

**Location:** `app/Services/AI/Tools/MCP/MCPToolAdapter.php`

**Key Features:**
- Extends `MCPToolInterface`
- Server availability checking
- Model compatibility checking
- Abstract `execute()` method for tool-specific logic

### 3. DmcpTool

**Purpose:** D&D dice rolling tool implementation

**Location:** `app/Services/AI/Tools/Implementations/DmcpTool.php`

**MCP Server:** https://dmcp-server.deno.dev/sse

**Tool Name:** `dmcp_roll_dice`

**Parameters:**
- `dice` (required): Dice notation (e.g., "1d20", "2d6+3", "1d20 advantage")
- `reason` (optional): Reason for the roll (e.g., "Attack roll")

**Example:**
```php
$tool = new DmcpTool([
    'server_url' => 'https://dmcp-server.deno.dev/sse',
]);

$result = $tool->execute([
    'dice' => '1d20',
    'reason' => 'Initiative roll',
], 'tool-call-123');
```

## Creating a New MCP Tool

### Step 1: Implement the Tool Class

```php
<?php
namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\MCP\MCPToolAdapter;
use App\Services\AI\Tools\MCP\MCPSSEClient;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;

class MyMcpTool extends MCPToolAdapter
{
    public function __construct(array $serverConfig)
    {
        parent::__construct($serverConfig);
    }

    public function getName(): string
    {
        return 'my_mcp_tool';
    }

    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'my_mcp_tool',
            description: 'Description of what your tool does',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'param1' => [
                        'type' => 'string',
                        'description' => 'Parameter description',
                    ],
                ],
                'required' => ['param1'],
            ],
            strict: false
        );
    }

    public function getMCPCategory(): string
    {
        return 'category'; // e.g., 'web', 'filesystem', 'gaming'
    }

    public function execute(array $arguments, string $toolCallId): ToolResult
    {
        if (!$this->serverAvailable || !$this->client) {
            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $this->getName(),
                result: ['error' => 'MCP server not available'],
                success: false,
                error: 'Server unavailable'
            );
        }

        try {
            // Call the MCP server
            $response = $this->client->callTool('tool_name', $arguments);

            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $this->getName(),
                result: $response,
                success: true
            );
        } catch (\Exception $e) {
            return new ToolResult(
                toolCallId: $toolCallId,
                toolName: $this->getName(),
                result: ['error' => $e->getMessage()],
                success: false,
                error: $e->getMessage()
            );
        }
    }

}
```

### Step 2: Register the Tool

Edit `app/Services/AI/Tools/ToolServiceProvider.php`:

```php
use App\Services\AI\Tools\Implementations\MyMcpTool;

private function registerMCPTools(ToolRegistry $registry): void
{
    $mcpServers = [
        [
            'type' => 'mcp',
            'class' => MyMcpTool::class,
            'server_label' => 'my_mcp',
            'server_description' => 'Description of my MCP server',
            'server_url' => 'https://my-mcp-server.com/sse',
            'require_approval' => 'always',
        ],
    ];

    foreach ($mcpServers as $serverConfig) {
        // ... registration logic
    }
}
```

### Step 3: Enable for Models

Edit your model configuration (e.g., `config/model_lists/gwdg_models.php`):

```php
'tools' => [
    'function_calling' => true,
    'mcp' => true,
    'enabled_tools' => ['my_mcp_tool'],
],
```

## MCP Protocol Details

### JSON-RPC Request Format

```json
{
    "jsonrpc": "2.0",
    "id": "mcp_abc123",
    "method": "tools/call",
    "params": {
        "name": "roll",
        "arguments": {
            "dice": "1d20"
        }
    }
}
```

### SSE Response Format

```
data: {"jsonrpc":"2.0","id":"mcp_abc123","result":{"total":15,"rolls":[15]}}

```

### Supported Methods

- `tools/list` - List available tools
- `tools/call` - Execute a tool

## Server Communication

MCP tools communicate with servers **only when the model calls them**:

1. **No boot-time checks** - Tools are registered immediately
2. **Lazy connection** - Connection to MCP server happens at execution time
3. **Timeout** - Default 30 seconds for MCP tool calls
4. **Error handling** - Connection/timeout errors are returned as tool results

## Error Handling

MCP tools handle several error scenarios:

### Server Unavailable
```php
ToolResult(
    success: false,
    error: 'MCP server not available',
    result: ['error' => 'Server unavailable']
)
```

### Invalid Request
```php
ToolResult(
    success: false,
    error: 'Missing required parameter',
    result: ['error' => 'Dice notation is required']
)
```

### Server Error
```php
ToolResult(
    success: false,
    error: 'MCP Error (-32600): Invalid Request',
    result: ['error' => 'Failed to execute: ...']
)
```

## Testing MCP Tools

### Using TestTool
```php
$response = $aiService->sendRequest([
    'model' => 'meta-llama-3.1-8b-instruct',
    'messages' => [
        ['role' => 'user', 'content' => ['text' => 'Roll 1d20 for initiative']]
    ],
]);
```

### Check Logs
```
[info] DMCP tool executing {"tool_call_id":"...","dice":"1d20","reason":"Initiative"}
[debug] MCP SSE Request {"url":"https://dmcp-server.deno.dev/sse","method":"tools/call"}
[debug] MCP SSE Response {"method":"tools/call","response":{...}}
[info] DMCP tool executed successfully {"tool_call_id":"...","result":{...}}
```

## Security Considerations

1. **URL Validation**: Always validate MCP server URLs
2. **Timeout**: Use appropriate timeouts to prevent hanging requests
3. **Input Validation**: Validate tool arguments before sending to server
4. **Error Handling**: Never expose internal server errors to users
5. **Rate Limiting**: Consider implementing rate limiting for MCP calls

## Future Enhancements

- Support for stdio-based MCP servers
- Tool approval workflow (require user confirmation)
- Caching of MCP tool results
- Dynamic tool registration from config files
- WebSocket support for MCP communication

## Example: D&D Dice Rolling

```php
// User message: "Roll for initiative"
// Model calls: dmcp_roll_dice(dice: "1d20", reason: "Initiative")

// MCP server responds:
{
    "total": 18,
    "rolls": [18],
    "dice": "1d20"
}

// Model responds: "You rolled an 18 for initiative!"
```

## Debugging

Enable debug logging:

```php
// In your .env
LOG_LEVEL=debug

// Check logs
tail -f storage/logs/laravel.log | grep MCP
```

Common issues:
- **Server not available**: Check firewall, network connectivity
- **Timeout**: Increase timeout in MCPSSEClient constructor
- **Invalid response**: Check MCP server implements protocol correctly
- **Tool not registered**: Verify ToolServiceProvider registration

## Resources

- [Model Context Protocol Specification](https://modelcontextprotocol.io/)
- [DMCP Server (Example)](https://github.com/modelcontextprotocol/servers/tree/main/src/dmcp)
- [MCP TypeScript SDK](https://github.com/modelcontextprotocol/typescript-sdk)
