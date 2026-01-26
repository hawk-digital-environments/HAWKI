# Backward Compatibility Fixes

## Issue

After implementing the new tool architecture with string strategies, the system threw type errors because existing code expected boolean values but received strings.

**Error Example:**
```
TypeError: App\Services\AI\Value\AiModel::isStreamable(): Return value must be of type bool, string returned
```

## Root Cause

The new tool configuration format uses strings:
```php
'tools' => [
    'stream' => 'native',  // New: string
    // vs
    'stream' => true,      // Old: boolean
]
```

But methods like `isStreamable()` and `hasTool()` expected boolean values and had return type declarations requiring `bool`.

## Fixed Methods

### 1. `AiModel::isStreamable()`

**Before:**
```php
public function isStreamable(): bool
{
    return $this->getTools()['stream'] ?? false;  // ❌ Returns string, expects bool
}
```

**After:**
```php
public function isStreamable(): bool
{
    $tools = $this->getTools();
    if (!isset($tools['stream'])) {
        return false;
    }

    $stream = $tools['stream'];

    // Old format: boolean
    if (is_bool($stream)) {
        return $stream;
    }

    // New format: string strategy
    return $stream === 'native';  // ✅ Returns bool
}
```

### 2. `AiModel::hasTool()`

**Before:**
```php
public function hasTool(string $tool): bool
{
    $tools = $this->getTools();
    return array_key_exists($tool, $tools) && $tools[$tool] === true;  // ❌ Expects boolean
}
```

**After:**
```php
public function hasTool(string $tool): bool
{
    $tools = $this->getTools();

    if (!array_key_exists($tool, $tools)) {
        return false;
    }

    $value = $tools[$tool];

    // Old format: boolean
    if (is_bool($value)) {
        return $value;
    }

    // New format: string strategy
    return $value !== 'unsupported';  // ✅ Returns bool
}
```

### 3. `AiModel::getToolStrategy()`

**Enhanced to handle backward compatibility:**

```php
public function getToolStrategy(string $toolName): ?string
{
    $tools = $this->getTools();

    if (!isset($tools[$toolName])) {
        return null;
    }

    $value = $tools[$toolName];

    // Handle old boolean format for backward compatibility
    if (is_bool($value)) {
        return $value ? 'native' : 'unsupported';  // ✅ Converts bool to string
    }

    return $value;
}
```

### 4. `GoogleRequestConverter::convertRequestToPayload()`

**Fixed web_search boolean check:**

**Before:**
```php
if (array_key_exists('web_search', $availableTools) &&
    $availableTools['web_search'] == true) {  // ❌ Expects boolean
    // ...
}
```

**After:**
```php
if ($model->hasTool('web_search') &&
    $model->getToolStrategy('web_search') === 'native') {  // ✅ Uses helper methods
    // ...
}
```

## Backward Compatibility Strategy

### Format Support

The system now supports **both** formats simultaneously:

**Old Format (Boolean):**
```php
'tools' => [
    'stream' => true,
    'file_upload' => false,
    'test_tool' => true,
]
```

**New Format (String Strategy):**
```php
'tools' => [
    'stream' => 'native',
    'file_upload' => 'unsupported',
    'test_tool' => 'function_call',
]
```

### Conversion Logic

| Old Format | New Format Equivalent |
|-----------|----------------------|
| `true` | `'native'` |
| `false` | `'unsupported'` |

### Method Behavior

All tool-checking methods now:
1. **Check type first**: `is_bool()` vs string
2. **Handle boolean**: Return boolean value as-is or convert to string
3. **Handle string**: Check if not `'unsupported'` or matches expected strategy
4. **Maintain return type**: Always return correct type for method signature

## Migration Path

### Phase 1: Dual Support (Current)
- ✅ Both formats work
- ✅ No breaking changes
- ✅ Gradual migration possible

### Phase 2: Deprecation (Future)
- Mark boolean format as deprecated in documentation
- Add logging warnings when boolean format detected
- Provide migration guide

### Phase 3: String-Only (Future Major Version)
- Remove boolean format support
- Simplify methods (remove `is_bool()` checks)
- Update all model configs to string format

## Testing

To verify backward compatibility:

1. **Test Old Format:**
```php
'tools' => [
    'stream' => true,
    'test_tool' => true,
]
```
- ✅ `$model->isStreamable()` should return `true`
- ✅ `$model->hasTool('test_tool')` should return `true`
- ✅ `$model->getToolStrategy('stream')` should return `'native'`

2. **Test New Format:**
```php
'tools' => [
    'stream' => 'native',
    'test_tool' => 'function_call',
]
```
- ✅ `$model->isStreamable()` should return `true`
- ✅ `$model->hasTool('test_tool')` should return `true`
- ✅ `$model->getToolStrategy('test_tool')` should return `'function_call'`

3. **Test Mixed Format:**
```php
'tools' => [
    'stream' => true,              // Old
    'test_tool' => 'function_call', // New
]
```
- ✅ Both should work correctly

## Fixed Files

- ✅ `app/Services/AI/Value/AiModel.php`
  - `isStreamable()`
  - `hasTool()`
  - `getToolStrategy()`

- ✅ `app/Services/AI/Providers/Google/GoogleRequestConverter.php`
  - Web search tool check

## Summary

The system now gracefully handles both old boolean and new string formats, ensuring:
- **No breaking changes** for existing configurations
- **Smooth migration** to new format
- **Type safety** maintained
- **Clear semantics** with new string strategies

Users can migrate at their own pace, and the system will continue to work with both formats until the boolean format is officially deprecated in a future major version.
