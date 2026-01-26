# Tool Payload Structure Fix

## Issue

OpenAI returned error: `INTERNAL ERROR: Missing required parameter: 'tools[0].name'`

## Root Cause

The tool payload had **double nesting** in the function structure:

### Incorrect Structure (Before)
```php
'tools' => [
    [
        'type' => 'function',
        'function' => [           // ❌ First function wrapper
            'type' => 'function',
            'function' => [       // ❌ Second function wrapper (wrong!)
                'name' => 'test_tool',
                'description' => '...',
                'parameters' => [...],
            ]
        ]
    ]
]
```

### Why This Happened

The code was wrapping `toOpenAiFormat()` output, which already includes the full structure:

```php
// OpenAiRequestConverter.php - WRONG
$tools[] = [
    'type' => 'function',
    'function' => $toolDef->toOpenAiFormat(),  // Already has 'type' and 'function'!
];
```

## Solution

### 1. OpenAiRequestConverter - Use Format Method Directly

**Before:**
```php
$functionCallTools = $this->buildFunctionCallTools($model);
foreach ($functionCallTools as $toolDef) {
    $tools[] = [
        'type' => 'function',
        'function' => $toolDef->toOpenAiFormat(),  // ❌ Double nesting
    ];
}
```

**After:**
```php
$functionCallTools = $this->buildFunctionCallTools($model);
foreach ($functionCallTools as $toolDef) {
    // toOpenAiFormat() already returns the complete structure
    $tools[] = $toolDef->toOpenAiFormat();  // ✅ Correct
}
```

### 2. GwdgRequestConverter - Map to Format

**Before:**
```php
$tools = $this->buildFunctionCallTools($model);
if (!empty($tools)) {
    $payload['tools'] = $tools;  // ❌ ToolDefinition objects, not formatted
}
```

**After:**
```php
$toolDefinitions = $this->buildFunctionCallTools($model);
if (!empty($toolDefinitions)) {
    // Convert ToolDefinition objects to OpenAI format
    $payload['tools'] = array_map(
        fn($toolDef) => $toolDef->toOpenAiFormat(),
        $toolDefinitions
    );  // ✅ Correct
}
```

## Correct Structure

### Expected Tool Payload
```php
'tools' => [
    // Function call tool
    [
        'type' => 'function',
        'function' => [
            'name' => 'test_tool',
            'description' => 'A test tool...',
            'parameters' => [
                'type' => 'object',
                'properties' => [...],
                'required' => [...]
            ],
            'strict' => false
        ]
    ],
    // MCP server
    [
        'type' => 'mcp',
        'server_label' => 'D&D Dice Roller',
        'server_description' => '',
        'server_url' => 'https://dmcp-server.deno.dev/sse',
        'require_approval' => 'never'
    ]
]
```

## Understanding the Flow

### 1. ToolDefinition Methods

```php
class ToolDefinition {
    public function toOpenAiFormat(): array {
        return [
            'type' => 'function',      // ← Already includes outer structure
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
                'strict' => $this->strict,
            ],
        ];
    }
}
```

### 2. ToolAwareConverter.buildFunctionCallTools()

Returns array of `ToolDefinition` objects:
```php
protected function buildFunctionCallTools(AiModel $model): array
{
    $tools = [];
    foreach ($modelTools as $toolName => $strategy) {
        if ($strategy === ExecutionStrategy::FUNCTION_CALL->value) {
            $tool = $registry->get($toolName);
            $tools[] = $tool->getDefinition();  // ← Returns ToolDefinition object
        }
    }
    return $tools;  // ← Array of ToolDefinition objects
}
```

### 3. RequestConverter Usage

**Pattern:**
```php
// Get ToolDefinition objects
$toolDefinitions = $this->buildFunctionCallTools($model);

// Convert to provider-specific format
$tools = array_map(fn($def) => $def->toOpenAiFormat(), $toolDefinitions);
// or: $def->toGoogleFormat()
// or: $def->toAnthropicFormat()
```

## Files Fixed

✅ `app/Services/AI/Providers/OpenAi/OpenAiRequestConverter.php`
- Line ~76: Removed double wrapping

✅ `app/Services/AI/Providers/Gwdg/GwdgRequestConverter.php`
- Line ~55: Added format conversion

## Testing

### Verify Correct Structure

Run this test to verify the payload structure:

```php
// In a test or controller
$model = /* get model */;
$converter = app(OpenAiRequestConverter::class);
$request = new AiRequest(/* ... */);
$payload = $converter->convertRequestToPayload($request);

// Check tools structure
if (isset($payload['tools'][0])) {
    $tool = $payload['tools'][0];

    // Should have these keys at top level
    assert(isset($tool['type']));
    assert($tool['type'] === 'function');

    // Function details should be one level deep
    assert(isset($tool['function']));
    assert(isset($tool['function']['name']));
    assert(isset($tool['function']['description']));
    assert(isset($tool['function']['parameters']));

    // Should NOT have nested 'type' or 'function'
    assert(!isset($tool['function']['type']));
    assert(!isset($tool['function']['function']));

    echo "✅ Tool structure is correct!";
}
```

### Expected vs Actual

**Expected (Correct):**
```
tools[0]['type'] = 'function'
tools[0]['function']['name'] = 'test_tool'
tools[0]['function']['parameters'] = {...}
```

**Before Fix (Wrong):**
```
tools[0]['type'] = 'function'
tools[0]['function']['type'] = 'function'        ← Extra nesting!
tools[0]['function']['function']['name'] = '...' ← Wrong!
```

## Summary

The issue was caused by unnecessary wrapping of `toOpenAiFormat()` output, which already includes the complete tool structure. The fix ensures that:

1. **OpenAI & GWDG**: Use `toOpenAiFormat()` directly
2. **Google**: Would use `toGoogleFormat()` if implemented
3. **Anthropic**: Would use `toAnthropicFormat()` if implemented

The payload now matches OpenAI's expected format, with proper nesting levels.

---

**Status: Fixed** ✅

The tool payload structure is now correct and should work with OpenAI's API.
