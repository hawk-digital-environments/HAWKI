# Automatic Tool Execution

## Overview

Tool execution is now **fully automatic**. When a model requests a tool, the `AiService` will:
1. Detect the tool calls in the response
2. Execute the requested tools
3. Send a follow-up request with the results
4. Return/stream the final answer

You don't need to manually handle tool execution - it's built into the request flow.

## How It Works

### Non-Streaming Requests

```php
use App\Services\AI\AiService;

$aiService = app(AiService::class);

// Just call sendRequest - tool execution happens automatically
$response = $aiService->sendRequest([
    'model' => 'meta-llama-3.1-8b-instruct',
    'messages' => [
        [
            'role' => 'user',
            'content' => ['text' => 'What is the weather in Paris?']
        ]
    ],
    'stream' => false,
]);

// $response will be the FINAL response after all tools have been executed
echo $response->content['text'];
```

**What happens internally:**

```
Request 1: User asks "What is the weather in Paris?"
  ↓
Response 1: Model returns tool_calls: [get_weather(location: "Paris")]
  ↓
[AiService detects tool calls]
  ↓
[ToolExecutionService executes get_weather]
  ↓
Request 2: Previous messages + assistant message with tool_calls + tool result
  ↓
Response 2: Model returns "The weather in Paris is sunny, 22°C"
  ↓
[No more tool calls, return final response]
```

### Streaming Requests

```php
use App\Services\AI\AiService;

$aiService = app(AiService::class);

$onData = function($chunk) {
    echo $chunk->content['text'];
};

// Tool execution happens automatically between streams
$aiService->sendStreamRequest([
    'model' => 'meta-llama-3.1-8b-instruct',
    'messages' => [...],
    'stream' => true,
], $onData);
```

**What happens internally:**

```
Stream 1: User message → Model responds with tool_calls
  ↓ onData called for each chunk
  ↓ Stream completes with tool_calls
  ↓
[AiService detects tool calls]
  ↓
[ToolExecutionService executes tools]
  ↓
Stream 2: Previous messages + tool results → Model responds with final answer
  ↓ onData called for each chunk
  ↓ Stream completes without tool_calls
  ↓
[Done]
```

## Multi-Round Tool Calling

The system supports multiple rounds of tool calling (up to 5 by default):

```php
// The model can call tools multiple times
$response = $aiService->sendRequest($payload);

// Example flow:
// Round 1: Model calls get_weather("Paris")
// Round 2: Model calls get_weather("London")
// Round 3: Model calls compare_cities("Paris", "London")
// Final: Model returns comparison result
```

### Customizing Max Rounds

```php
// Allow more tool rounds
$response = $aiService->sendRequest($payload, maxToolRounds: 10);

// For streaming
$aiService->sendStreamRequest($payload, $onData, maxToolRounds: 10);
```

## Preventing Infinite Loops

The `maxToolRounds` parameter prevents infinite loops where the model keeps calling tools:

- Default: 5 rounds
- If max rounds reached, **tools are disabled** and a final request is sent to force a text response
- A warning is logged: `"Max tool execution rounds reached"`
- A status message is sent to the frontend: `"Maximum tool execution rounds reached. Generating final response..."`

```php
// Example of hitting max rounds (with maxToolRounds: 3)
Round 1: get_weather("Paris")
Round 2: get_weather("London")
Round 3: get_weather("Berlin")
[Max rounds reached]
  ↓
[Tools disabled, final request sent]
  ↓
Final: Model returns text response without tool calls
```

**Important:** When max rounds is reached, the system sends a **final request with tools disabled**, ensuring the model always provides a text response to the user rather than leaving them without an answer.

## Logging

Tool execution is logged automatically:

```
[info] Tool execution required {"round":1,"tool_count":1}
[info] Tool call parsed {"name":"test_tool","arguments":{...}}
[info] TestTool executed {"tool_call_id":"...","arguments":{...}}
[info] Tool executed successfully {"tool":"test_tool","tool_call_id":"..."}
```

For streaming:
```
[info] Tool execution required in stream {"round":1,"tool_count":1}
```

## Manual Control (Optional)

If you need manual control over tool execution, you can use `ToolExecutionService` directly:

```php
use App\Services\AI\Tools\ToolExecutionService;
use App\Services\AI\Interfaces\ClientInterface;

$client = $model->getClient();
$toolService = app(ToolExecutionService::class);

// Send initial request
$response = $client->sendRequest($request);

// Manually check and execute tools
if ($toolService->requiresToolExecution($response)) {
    // Execute tools
    $followUpRequest = $toolService->buildFollowUpRequest($request, $response);

    // Send follow-up
    $finalResponse = $client->sendRequest($followUpRequest);
}
```

**Note:** This is rarely needed since `AiService` handles it automatically.

## Error Handling

If a tool execution fails:

```php
try {
    $response = $aiService->sendRequest($payload);
} catch (\Exception $e) {
    // Tool execution errors are caught and logged
    // The error is passed back to the model in the tool result
    Log::error('Request failed', ['error' => $e->getMessage()]);
}
```

Tool errors are sent back to the model as tool results:

```json
{
  "role": "tool",
  "tool_call_id": "call-123",
  "content": "{\"error\":\"Database connection failed\"}"
}
```

The model can then respond appropriately (e.g., "I'm sorry, I couldn't fetch that data").

## Testing Automatic Execution

### Step 1: Enable test_tool

```php
'models' => [
    [
        'id' => 'meta-llama-3.1-8b-instruct',
        'tools' => [
            'function_calling' => true,
            'enabled_tools' => ['test_tool'],
        ],
    ],
],
```

### Step 2: Send a test request

```php
$response = $aiService->sendRequest([
    'model' => 'meta-llama-3.1-8b-instruct',
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                'text' => 'Please use test_tool with message "Hello" and count 2'
            ]
        ]
    ],
    'stream' => false,
]);

// The response should contain the final answer AFTER tool execution
var_dump($response->content);
```

### Step 3: Check logs

You should see:
```
[info] Tool execution required {"round":1,"tool_count":1}
[info] Tool call parsed {"name":"test_tool",...}
[info] TestTool executed {...}
[info] Tool executed successfully {...}
```

## Comparison: Before vs After

### Before (Manual - Not Implemented)

```php
// You would have needed to write this yourself:
$response = $client->sendRequest($request);

if ($response->hasToolCalls()) {
    // Manually execute each tool
    foreach ($response->toolCalls as $toolCall) {
        $tool = $registry->get($toolCall->name);
        $result = $tool->execute($toolCall->arguments, $toolCall->id);
        $toolResults[] = $result;
    }

    // Manually build follow-up request
    $payload['messages'][] = [/* assistant message */];
    $payload['messages'][] = [/* tool results */];

    // Send follow-up
    $finalResponse = $client->sendRequest(new AiRequest(payload: $payload));
}
```

### After (Automatic)

```php
// Just call sendRequest - everything happens automatically
$response = $aiService->sendRequest($request);
```

## Performance Considerations

### Network Requests

Each tool round requires an additional network request:

- No tools: 1 request
- 1 tool round: 2 requests
- 2 tool rounds: 3 requests
- etc.

### Latency

Tool execution adds latency:
- Tool execution time (varies by tool)
- Additional network round-trip
- Model processing time for follow-up

For latency-sensitive applications, consider:
1. Using fast tools
2. Reducing `maxToolRounds`
3. Caching tool results

### Streaming UX

For streaming requests with tools, users will see:
1. First stream (may be empty or partial if tool_calls finish_reason)
2. Status message: "Executing tool_name..."
3. Brief pause while tools execute
4. Second stream with final answer

**Important:** The `isDone` flag is **masked** during tool execution. When the first stream completes with tool calls, `isDone` is sent as `false` to the frontend to keep the connection open. Only when the entire conversation is complete (no more tool calls) is `isDone: true` sent.

### Status Messages

The system sends status updates to inform users about tool execution:

```php
$onData = function($chunk) {
    if ($chunk->type === 'status') {
        echo "Status: {$chunk->statusMessage}\n";
    } else if ($chunk->type === 'content') {
        echo $chunk->content['text'];
    }
};

$aiService->sendStreamRequest($payload, $onData);
```

**Status Message Types:**
- `"Executing tool_name..."` - Sent before each tool execution round
- `"Maximum tool execution rounds reached. Generating final response..."` - Sent when max rounds is hit

### AiResponse Type System

`AiResponse` now includes a `type` field to differentiate response types:

- `type: 'content'` - Regular text content from the model
- `type: 'status'` - Status message about tool execution
- `type: 'tool_call'` - Reserved for future use
- `type: 'tool_result'` - Reserved for future use

Frontend code can use the type to handle different response types appropriately.

## Advanced: Custom Tool Execution

If you need to customize tool execution behavior:

```php
// Create a custom service extending ToolExecutionService
class CustomToolExecutionService extends ToolExecutionService
{
    public function executeToolCalls(array $toolCalls): array
    {
        // Add custom logic (rate limiting, caching, etc.)
        return parent::executeToolCalls($toolCalls);
    }
}

// Bind in service provider
$this->app->singleton(ToolExecutionService::class, CustomToolExecutionService::class);
```

## Summary

✅ **Automatic** - No manual intervention required
✅ **Multi-round** - Handles multiple tool calls automatically
✅ **Safe** - Max rounds prevent infinite loops
✅ **Logged** - Full execution trace in logs
✅ **Streaming** - Works seamlessly with streaming responses
✅ **Error handling** - Tool errors sent back to model

You just call `sendRequest()` or `sendStreamRequest()` and the system handles the rest!
