# Tool Architecture Migration Status

## Completed Steps ✅

### 1. Core Structure Created
- ✅ Created `app/Services/AI/Tools/Enums/ExecutionStrategy.php`
- ✅ Created `config/tools.php` for tool registration
- ✅ Created `AbstractMCPTool.php` base class
- ✅ Updated `AbstractTool.php` (removed availability logic, added helper methods)

### 2. Interfaces Updated
- ✅ Simplified `ToolInterface` (removed availability methods)
- ✅ Updated `MCPToolInterface` (removed getMCPCategory)

### 3. Tool Implementations Updated
- ✅ Updated `TestTool.php` to use new base class
- ✅ Refactored `DmcpTool.php` to extend `AbstractMCPTool`
- ✅ Changed tool name from `dmcp_roll_dice` to `dice_roll`

### 4. Registry & Service Provider
- ✅ Simplified `ToolRegistry.php` (removed availability checking)
- ✅ Updated `ToolServiceProvider.php` to read from config/tools.php

### 5. AiModel Enhanced
- ✅ Added `getToolStrategy(string $toolName): ?string`
- ✅ Added `hasToolAvailable(string $toolName): bool`

### 6. New Trait Created
- ✅ Created `ToolAwareConverter.php` trait with:
  - `buildFunctionCallTools()` - for function_call strategy
  - `buildMCPServers()` - for mcp strategy
  - `shouldDisableTools()` - check if tools disabled
  - `hasToolsWithStrategy()` - utility method

## Remaining Steps 🚧

### Step 9: Update RequestConverters to use ToolAwareConverter

Need to update these files:
1. `app/Services/AI/Providers/OpenAi/OpenAiRequestConverter.php`
2. `app/Services/AI/Providers/Google/GoogleRequestConverter.php`
3. `app/Services/AI/Providers/Gwdg/GwdgRequestConverter.php`
4. `app/Services/AI/Providers/Ollama/OllamaRequestConverter.php`
5. `app/Services/AI/Providers/OpenWebUI/OpenWebUiRequestConverter.php`

Changes needed:
- Replace `use CapabilityAwareConverter` with `use ToolAwareConverter`
- Replace `buildToolsFromCapabilities()` with `buildFunctionCallTools()`
- Replace `buildMCPServersFromCapabilities()` with `buildMCPServers()`

### Step 10: Update Model Configurations

Need to update model config files to use new format:
- `config/model_lists/openai_models.php`
- `config/model_lists/gwdg_models.php`
- `config/model_lists/google_models.php`
- Other model list files

Example transformation:
```php
// OLD (deprecated)
'tools' => [
    'stream' => true,
    'function_calling' => true,
    'test_tool' => true,
    'dmcp_roll_dice' => false,
]

// NEW
'tools' => [
    'stream' => 'native',          // @deprecated - will migrate to capabilities
    'file_upload' => 'native',     // @deprecated - will migrate to capabilities
    'vision' => 'native',          // @deprecated - will migrate to capabilities
    'test_tool' => 'function_call', // Tool execution strategy
    'dice_roll' => 'unsupported',   // Or omit entirely
]
```

### Step 11: Delete Capabilities Folder

After confirming everything works, delete:
- `app/Services/AI/Capabilities/` (entire folder)
- `app/Services/AI/Providers/Traits/CapabilityAwareConverter.php` (old trait)
- `app/Services/AI/Tools/MCP/MCPToolAdapter.php` (replaced by AbstractMCPTool)

### Step 12: Add Deprecation Notices

Add `@deprecated` comments in relevant files:
- Mark boolean tool flags as deprecated in AiModel documentation
- Update any services that check for old-style tool flags

### Step 13: Testing

Test the following scenarios:
1. Model with `function_call` strategy tools
2. Model with `mcp` strategy tools
3. Model with `native` strategy tools
4. Model with mixed strategies
5. Tool execution for both regular and MCP tools
6. Unsupported/omitted tools are ignored

## Migration Guide for Users

When deploying this update, users need to:

1. **Update model configs** to use execution strategies:
   ```php
   'tools' => [
       'test_tool' => 'function_call',  // Was: 'test_tool' => true
       'dice_roll' => 'mcp',            // Was: 'dmcp_roll_dice' => true
   ]
   ```

2. **Update tool names**:
   - `dmcp_roll_dice` → `dice_roll`

3. **Understand execution strategies**:
   - `native` - Model handles internally
   - `mcp` - Model supports MCP protocol
   - `function_call` - HAWKI orchestrates
   - `unsupported` or omit - Not available

## Benefits of New Architecture

1. **Simpler**: Config-driven, no hardcoded matrices
2. **Maintainable**: Clear separation of concerns
3. **Flexible**: Easy to add new tools (just add to config/tools.php)
4. **User-friendly**: Strategies are self-explanatory
5. **Less code**: Removed entire Capabilities folder complexity

## Breaking Changes

- Tool name change: `dmcp_roll_dice` → `dice_roll`
- Config format change: Boolean flags → Strategy strings
- Removed: Capabilities folder and related classes
- API changes: Tool availability now determined by config, not tool methods
