# AI Tools System

This directory contains the implementation of the AI Tools system, which enables AI models to call external functions and tools during conversation.

## Architecture Overview

```
Tools/
├── Interfaces/          # Tool interfaces
│   ├── ToolInterface.php
│   └── MCPToolInterface.php
├── Value/               # Value objects
│   ├── ToolCall.php
│   ├── ToolDefinition.php
│   └── ToolResult.php
├── Implementations/     # Concrete tool implementations
│   ├── TestTool.php
│   └── DmcpTool.php    # D&D dice rolling MCP tool
├── MCP/                 # Model Context Protocol support
│   ├── MCPToolAdapter.php
│   └── MCPSSEClient.php  # SSE-based MCP client
├── ToolRegistry.php     # Central tool registry
├── ToolExecutionService.php  # Tool execution orchestration
├── AbstractTool.php     # Base class for tools
└── ToolServiceProvider.php   # Laravel service provider
```

## How Tool Calling Works

1. **Request Phase**: Model receives request with available tools in the payload
2. **Tool Call Phase**: Model responds with `tool_calls` indicating which tools to execute
3. **Execution Phase**: Application executes the requested tools
4. **Follow-up Phase**: Application sends tool results back to model
5. **Final Response**: Model provides final answer using tool results

## Creating a New Tool

### Step 1: Create Tool Class

Create a new class extending `AbstractTool`:

```php
<?php

namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\AbstractTool;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;

class MyCustomTool extends AbstractTool
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'my_custom_tool',
            description: 'Describes what this tool does',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'param1' => [
                        'type' => 'string',
                        'description' => 'Description of param1',
                    ],
                ],
                'required' => ['param1'],
            ]
        );
    }

    public function execute(array $arguments, string $toolCallId): ToolResult
    {
        // Your tool logic here
        $result = [
            'status' => 'success',
            'data' => '...',
        ];

        return new ToolResult(
            toolCallId: $toolCallId,
            toolName: $this->getName(),
            result: $result,
            success: true
        );
    }
}
```

### Step 2: Register the Tool

Add your tool to `ToolServiceProvider::registerBuiltInTools()`:

```php
private function registerBuiltInTools(ToolRegistry $registry): void
{
    $registry->register(new TestTool());
    $registry->register(new MyCustomTool()); // Add this line
}
```

### Step 3: Enable in Model Configuration

Update your model configuration to enable the tool:

```php
'models' => [
    [
        'id' => 'meta-llama-3.1-8b-instruct',
        'label' => 'Llama 3.1 8B',
        'tools' => [
            'stream' => true,
            'function_calling' => true,
            'enabled_tools' => [
                'test_tool',
                'my_custom_tool', // Add this
            ],
        ],
    ],
],
```

## Documentation

- **[AUTOMATIC_EXECUTION.md](./AUTOMATIC_EXECUTION.md)** - How tool execution works automatically in AiService
- **[MCP_IMPLEMENTATION.md](./MCP_IMPLEMENTATION.md)** - Complete guide to MCP server integration
- **[IMPLEMENTATION_SUMMARY.md](./IMPLEMENTATION_SUMMARY.md)** - Technical implementation details
- **[CONFIGURATION_EXAMPLE.md](./CONFIGURATION_EXAMPLE.md)** - Configuration examples

## MCP Tool Support

The system supports MCP (Model Context Protocol) tools, which are external tools that communicate via the MCP specification.

**See [MCP_IMPLEMENTATION.md](./MCP_IMPLEMENTATION.md) for complete MCP documentation.**

### Included MCP Tools

- **DmcpTool** - D&D dice rolling tool (connects to https://dmcp-server.deno.dev/sse)

### Creating an MCP Tool

Extend `MCPToolAdapter`:

```php
<?php

namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\MCP\MCPToolAdapter;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;

class MyMCPTool extends MCPToolAdapter
{
    public function getName(): string
    {
        return 'my_mcp_tool';
    }

    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'my_mcp_tool',
            description: 'An MCP-based tool',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Query parameter',
                    ],
                ],
                'required' => ['query'],
            ]
        );
    }

    public function getMCPCategory(): string
    {
        return 'custom';
    }

    public function execute(array $arguments, string $toolCallId): ToolResult
    {
        // Send request to MCP server
        $response = $this->sendMCPRequest('tools/call', [
            'name' => $this->getName(),
            'arguments' => $arguments,
        ]);

        return new ToolResult(
            toolCallId: $toolCallId,
            toolName: $this->getName(),
            result: $response,
            success: true
        );
    }
}
```

### MCP Configuration

Enable MCP in model config:

```php
'models' => [
    [
        'id' => 'gpt-4',
        'tools' => [
            'function_calling' => true,
            'mcp' => true,  // Enable MCP support
            'enabled_tools' => [
                'my_mcp_tool',
            ],
        ],
    ],
],
```

## Model Configuration Reference

### Tool Flags

- `function_calling`: Enable function calling for this model
- `mcp`: Enable MCP tool support
- `stream`: Enable streaming responses
- `vision`: Enable image processing
- `file_upload`: Enable file attachments

### Enabled Tools

The `enabled_tools` array controls which tools are available:

```php
'tools' => [
    'function_calling' => true,
    'enabled_tools' => [
        'test_tool',        // Only these tools will be sent
        'weather_tool',     // to this model
    ],
],

// Empty array = all tools available
'tools' => [
    'function_calling' => true,
    'enabled_tools' => [],  // All registered tools available
],

// Not specified = all tools available
'tools' => [
    'function_calling' => true,
    // No enabled_tools key = all tools available
],
```

## Using ToolExecutionService

The `ToolExecutionService` handles tool execution and follow-up requests:

```php
use App\Services\AI\Tools\ToolExecutionService;

$executionService = app(ToolExecutionService::class);

// Check if response needs tool execution
if ($executionService->requiresToolExecution($response)) {
    // Build follow-up request with tool results
    $followUpRequest = $executionService->buildFollowUpRequest(
        $originalRequest,
        $response
    );

    // Send follow-up request to get final answer
    $finalResponse = $model->getClient()->sendRequest($followUpRequest);
}
```

## Testing Tools

Use the built-in `TestTool` to verify your setup:

1. Enable `test_tool` in your model config
2. Send a message asking the model to use the test tool
3. Check logs for tool execution details

Example prompt:
```
"Please use the test_tool with message 'hello' and count 3"
```

## Provider Support

Currently implemented for:
- ✅ GWDG (streaming and non-streaming)
- ⏳ OpenAI (planned)
- ⏳ Google (planned)
- ⏳ Anthropic (planned)

## Debugging

Enable debug logging to see tool execution:

```php
\Log::debug('Tool call received', ['tool_calls' => $response->toolCalls]);
```

Check logs for:
- `Tool call parsed` - Tool call successfully parsed
- `Tool executed successfully` - Tool execution completed
- `Tool execution failed` - Tool execution error

## Security Considerations

1. **Validate Arguments**: Always validate tool arguments in `execute()`
2. **Rate Limiting**: Consider rate limiting tool executions
3. **Permission Checks**: Implement permission checks for sensitive tools
4. **Input Sanitization**: Sanitize user inputs before passing to tools
5. **Error Handling**: Never expose internal errors to the model

## Future Enhancements

- [ ] Parallel tool execution
- [ ] Tool caching
- [ ] Tool versioning
- [ ] Tool marketplace
- [ ] Enhanced MCP protocol support
- [ ] Tool composition (chaining)
