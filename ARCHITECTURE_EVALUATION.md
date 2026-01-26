# Architecture Evaluation: Tools and MCPs Simplification

## Executive Summary

The current tools and MCPs architecture has significant complexity due to:
1. **Dual system approach**: Legacy ToolRegistry + new Capability system coexisting
2. **Scattered configuration**: Tool flags in model configs + hardcoded matrices in PHP
3. **Unclear responsibilities**: Multiple places determining what tools/capabilities are available

This document evaluates approaches to simplify the architecture while maintaining backward compatibility.

---

## Current State Analysis

### Components Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     CURRENT ARCHITECTURE                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Config Files (model_lists/*.php)                           │
│  ├─ tools.stream                                            │
│  ├─ tools.function_calling                                  │
│  ├─ tools.test_tool                                         │
│  └─ tools.dmcp_roll_dice                                    │
│                          ↓                                   │
│  ProviderCapabilityMatrix (hardcoded)                       │
│  ├─ Provider → Capability → ExecutionStrategy               │
│  └─ Determines HOW capabilities execute                     │
│                          ↓                                   │
│  ModelCapabilityOverride (hardcoded)                        │
│  └─ Model-specific overrides                                │
│                          ↓                                   │
│  CapabilityResolver                                         │
│  └─ Resolves execution strategy                             │
│                          ↓                                   │
│  CapabilityAwareConverter (trait)                           │
│  └─ Builds tools/MCP configs for requests                   │
│                          ↓                                   │
│  ToolRegistry (legacy)                                      │
│  └─ Executes tools when called                              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Pain Points

1. **Dual Configuration**
   - Model config: `'test_tool' => true`
   - ProviderCapabilityMatrix: `'code_execution' => ExecutionStrategy::EXTERNAL`
   - Which takes precedence? Unclear.

2. **Hardcoded Business Logic**
   - ProviderCapabilityMatrix is hardcoded in PHP
   - ModelCapabilityOverride is hardcoded in PHP
   - TODO comments indicate planned migration to config

3. **Mixed Responsibilities**
   - AbstractTool checks model config directly
   - CapabilityResolver has separate logic
   - Not clear which system is authoritative

4. **Legacy System Still Active**
   - ToolRegistry still used for execution
   - AbstractTool.isEnabledForModel() checks model config
   - Capability system doesn't fully replace it

---

## Question 1: Approaches to Simplify

### ❌ Approach A: Merge Everything into AiModel (NOT RECOMMENDED)

**Concept**: Add methods like `$model->getAvailableTools()`, `$model->canExecute($capability)`

**Pros:**
- Simple API surface
- Single entry point for capabilities

**Cons:**
- Violates Single Responsibility Principle
- AiModel becomes bloated with business logic
- Hard to test capability resolution in isolation
- Tight coupling between model data and capability logic
- Makes AiModel mutable when it should be a value object

**Verdict**: ❌ **Do NOT pursue this approach**

### ✅ Approach B: Config-Driven Capability System (RECOMMENDED)

**Concept**: Make config files the single source of truth, keep services separate

```
Config Files (Single Source of Truth)
    ↓
CapabilityResolver (Decision Engine)
    ↓
Tools/MCPs (Execution Layer)
```

**Architecture:**

```php
// config/model_lists/openai_models.php
[
    'id' => 'gpt-4.1',
    'capabilities' => [
        'code_execution' => 'mcp_direct',  // or 'external', 'native', 'unsupported'
        'web_search' => 'unsupported',
        'dice_roll' => 'mcp_direct',
    ],
    'tools' => [
        'stream' => true,
        'file_upload' => true,
        'vision' => true,
    ],
]
```

**Benefits:**
- Config files are single source of truth
- No hardcoded matrices in PHP
- Easy to add/modify capabilities without code changes
- Clear separation: Config → Resolver → Execution
- AiModel remains a simple value object

**Implementation:**
1. Add `capabilities` array to model configs
2. Update CapabilityResolver to read from model config
3. Remove hardcoded ProviderCapabilityMatrix
4. Keep ModelCapabilityOverride as fallback for special cases
5. Deprecate tool-specific flags in favor of capabilities

### ✅ Approach C: Hybrid with Provider Defaults (ALTERNATIVE)

**Concept**: Provider-level defaults in `model_providers.php` + Model-level overrides

```php
// config/model_providers.php
'providers' => [
    'openAi' => [
        'capabilities' => [
            'code_execution' => 'mcp_direct',
            'web_search' => 'unsupported',
            'dice_roll' => 'mcp_direct',
        ],
        'models' => require 'model_lists/openai_models.php',
    ],
]

// config/model_lists/openai_models.php
[
    'id' => 'gpt-4.1',
    'capabilities' => [
        // Only override what differs from provider defaults
        'web_search' => 'native',  // Override: GPT-4.1 has native search
    ],
]
```

**Benefits:**
- DRY: Provider defaults reduce duplication
- Model configs only specify differences
- Still config-driven, no hardcoded logic

**Drawbacks:**
- Two places to check for capabilities (provider + model)
- More complex resolution logic

---

## Question 2: Merge Capabilities with AiModel?

### Recommendation: **NO - Keep Separated**

**Reasons:**

1. **Single Responsibility Principle**
   - AiModel should represent model data/configuration
   - Capability resolution is business logic, belongs in services

2. **Testability**
   - CapabilityResolver can be tested independently
   - Mock capability resolution without creating full AiModel instances

3. **Flexibility**
   - Can swap capability resolution strategies
   - Can add caching, logging, analytics to resolver
   - Can support different resolution policies per use case

4. **Maintainability**
   - Clear boundaries: Data vs Logic
   - Changes to capability logic don't affect AiModel
   - Easier to understand for new developers

**However, AiModel CAN have convenience methods:**

```php
// AiModel.php
public function getCapabilityStrategy(CapabilityName $capability): ExecutionStrategy
{
    // Delegate to resolver (dependency injection)
    return app(CapabilityResolver::class)->resolveStrategy($this, $capability);
}

public function hasCapability(CapabilityName $capability): bool
{
    return $this->getCapabilityStrategy($capability) !== ExecutionStrategy::UNSUPPORTED;
}
```

This keeps AiModel thin while providing a convenient API.

---

## Question 3: Config as Single Source of Truth

### Recommendation: **YES - Enforce Strictly**

**Current Problem:**
- Tool flags: `'test_tool' => true` in model config
- Capability matrix: Hardcoded in ProviderCapabilityMatrix.php
- Overrides: Hardcoded in ModelCapabilityOverride.php

### Proposed Solution:

#### 1. **Standardize Model Config Format**

```php
// config/model_lists/gwdg_models.php
[
    'id' => 'meta-llama-3.1-8b-instruct',
    'label' => 'GWDG Meta Llama 3.1 8B Instruct',
    'input' => ['text'],
    'output' => ['text'],

    // Basic features (keep as-is)
    'tools' => [
        'stream' => true,
        'file_upload' => true,
        'vision' => false,
    ],

    // Capability execution strategies (NEW)
    'capabilities' => [
        'code_execution' => 'external',      // Uses TestTool
        'dice_roll' => 'mcp_function',       // HAWKI orchestrates via function calling
        'web_search' => 'unsupported',       // Not available
        'image_generation' => 'unsupported',
    ],
]
```

#### 2. **Remove Hardcoded Matrices**

```php
// DELETE or deprecate:
// - app/Services/AI/Capabilities/Matrix/ProviderCapabilityMatrix.php (move to config)
// - app/Services/AI/Capabilities/Matrix/ModelCapabilityOverride.php (move to config)
```

#### 3. **Update CapabilityResolver**

```php
// app/Services/AI/Capabilities/CapabilityResolver.php
public function resolveStrategy(AiModel $model, CapabilityName $capability): ExecutionStrategy
{
    // Priority 1: Model-level configuration
    $modelCapabilities = $model->getCapabilities(); // Read from raw config
    if (isset($modelCapabilities[$capability->value])) {
        return ExecutionStrategy::from($modelCapabilities[$capability->value]);
    }

    // Priority 2: Provider-level defaults (if implemented)
    $providerCapabilities = $this->getProviderDefaults($model->getProvider());
    if (isset($providerCapabilities[$capability->value])) {
        return ExecutionStrategy::from($providerCapabilities[$capability->value]);
    }

    // Priority 3: Global fallback
    return ExecutionStrategy::UNSUPPORTED;
}
```

#### 4. **Deprecate Tool-Specific Flags**

**Current (to be deprecated):**
```php
'tools' => [
    'test_tool' => true,
    'dmcp_roll_dice' => false,
]
```

**New approach:**
```php
'capabilities' => [
    'code_execution' => 'external',  // Implies test_tool available
    'dice_roll' => 'unsupported',    // Replaces dmcp_roll_dice => false
]
```

**Migration path:**
1. Support both formats during transition
2. Log deprecation warnings for old format
3. Remove old format support in next major version

---

## Recommended Implementation Plan

### Phase 1: Config Migration (No Breaking Changes)

**Goal**: Move hardcoded matrices to config files

1. ✅ Add `capabilities` field to all model configs
2. ✅ Update CapabilityResolver to read from config first, fall back to hardcoded matrix
3. ✅ Add provider-level capability defaults to `model_providers.php`
4. ✅ Keep existing tool flags working (backward compatibility)

**Changes:**
- Add `AiModel::getCapabilities(): array` method
- Update CapabilityResolver to check config before matrices
- No breaking changes for existing code

### Phase 2: Deprecate Tool-Specific Flags

**Goal**: Gradually move from tool flags to capability strategies

1. ✅ Mark tool-specific flags as deprecated in documentation
2. ✅ Add validation to check both old and new format
3. ✅ Log warnings when old format is used
4. ✅ Provide migration guide

**Changes:**
- Add deprecation notices in AbstractTool
- Update documentation
- No breaking changes yet

### Phase 3: Remove Hardcoded Matrices

**Goal**: Config files become only source of truth

1. ✅ Ensure all models have `capabilities` defined in config
2. ✅ Remove ProviderCapabilityMatrix.php (or mark as deprecated)
3. ✅ Remove ModelCapabilityOverride.php (or mark as deprecated)
4. ✅ Update tests to use config-based approach

**Changes:**
- Delete or deprecate matrix classes
- Update CapabilityResolver to only read from config
- May be breaking change (major version bump)

### Phase 4: Cleanup Legacy Tool System (Optional)

**Goal**: Simplify by removing dual system

1. ✅ Evaluate if ToolRegistry still needed (likely yes for execution)
2. ✅ Remove tool-specific flags from AbstractTool
3. ✅ Streamline tool availability checks

**Changes:**
- Keep ToolRegistry for execution only
- Remove availability/filtering logic from AbstractTool (delegate to CapabilityResolver)

---

## Proposed New Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     PROPOSED ARCHITECTURE                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Config Files (Single Source of Truth)                      │
│  ├─ model_providers.php                                     │
│  │  └─ Provider-level capability defaults                   │
│  └─ model_lists/*.php                                       │
│     └─ Model-level capability strategies                    │
│                          ↓                                   │
│  AiModel (Value Object)                                     │
│  ├─ getCapabilities(): array                                │
│  ├─ hasCapability(name): bool (convenience)                 │
│  └─ Delegates to CapabilityResolver                         │
│                          ↓                                   │
│  CapabilityResolver (Decision Engine)                       │
│  ├─ Reads from config only                                  │
│  ├─ No hardcoded logic                                      │
│  └─ Returns ExecutionStrategy                               │
│                          ↓                                   │
│  CapabilityAwareConverter (trait)                           │
│  └─ Builds tools/MCP configs for requests                   │
│                          ↓                                   │
│  ToolRegistry (Execution Only)                              │
│  └─ Executes tools when called                              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

**Key Principles:**
1. **Config is king**: All capability strategies defined in config files
2. **Separation of concerns**: AiModel (data) vs CapabilityResolver (logic)
3. **Single flow**: Config → Resolver → Converter → Execution
4. **No hardcoded matrices**: Everything is configurable
5. **Backward compatible migration**: Gradual transition with deprecation warnings

---

## Example: Before vs After

### Before (Current State)

**ProviderCapabilityMatrix.php (hardcoded):**
```php
'gwdg' => [
    'dice_roll' => ExecutionStrategy::MCP_FUNCTION,
    'code_execution' => ExecutionStrategy::EXTERNAL,
]
```

**Model config:**
```php
[
    'id' => 'meta-llama-3.1-8b-instruct',
    'tools' => [
        'function_calling' => true,
        'test_tool' => true,
        'dmcp_roll_dice' => false,  // Conflicts with matrix?
    ],
]
```

**Problem**: Two sources of truth, unclear precedence

### After (Proposed State)

**model_providers.php:**
```php
'gwdg' => [
    'capabilities' => [
        'code_execution' => 'external',
        'dice_roll' => 'mcp_function',
        'web_search' => 'unsupported',
    ],
    'models' => require 'model_lists/gwdg_models.php',
]
```

**Model config:**
```php
[
    'id' => 'meta-llama-3.1-8b-instruct',
    'tools' => [
        'stream' => true,
        'file_upload' => true,
    ],
    'capabilities' => [
        // Inherits from provider defaults
        // Override only if different:
        'dice_roll' => 'unsupported',  // Disable for this specific model
    ],
]
```

**Benefit**: Single, clear source of truth. Model overrides provider defaults when needed.

---

## Recommendations Summary

### ✅ DO:
1. **Use Approach B or C**: Config-driven capability system
2. **Keep AiModel as value object**: Don't add business logic
3. **Make config files single source of truth**: Move all matrices to config
4. **Implement in phases**: Gradual migration with backward compatibility
5. **Use provider defaults**: Reduce duplication across models

### ❌ DON'T:
1. **Don't merge capabilities into AiModel**: Keep separation of concerns
2. **Don't keep hardcoded matrices**: Everything should be in config
3. **Don't break existing code**: Maintain backward compatibility during migration
4. **Don't mix tool flags and capabilities**: Deprecate old flags, use capabilities

### 🎯 Next Steps:
1. Review and approve this architecture plan
2. Implement Phase 1 (config migration without breaking changes)
3. Test thoroughly with existing models
4. Document the new capability system
5. Gradually deprecate old tool flags
6. Eventually remove hardcoded matrices

---

## Impact Assessment

### Files to Modify:
- `config/model_providers.php` - Add capability defaults per provider
- `config/model_lists/*.php` - Add capabilities to each model
- `app/Services/AI/Value/AiModel.php` - Add getCapabilities() method
- `app/Services/AI/Capabilities/CapabilityResolver.php` - Read from config
- `app/Services/AI/Capabilities/Matrix/ProviderCapabilityMatrix.php` - Mark deprecated
- `app/Services/AI/Capabilities/Matrix/ModelCapabilityOverride.php` - Mark deprecated

### Files to Keep:
- `app/Services/AI/Tools/ToolRegistry.php` - Keep for execution
- `app/Services/AI/Tools/ToolExecutionService.php` - Keep
- `app/Services/AI/Providers/Traits/CapabilityAwareConverter.php` - Keep
- All tool implementations - Keep

### Breaking Changes:
- **Phase 1-2**: None (backward compatible)
- **Phase 3**: Remove hardcoded matrices (major version)
- **Phase 4**: Remove tool-specific flags (major version)

---

## Conclusion

The recommended approach is **Approach B (Config-Driven)** with gradual migration:

1. Keep CapabilityResolver as separate service (don't merge into AiModel)
2. Move all capability strategies from hardcoded PHP to config files
3. Support both old and new formats during transition
4. Eventually deprecate and remove hardcoded matrices and tool flags

This approach provides:
- ✅ Clear, maintainable architecture
- ✅ Config files as single source of truth
- ✅ Backward compatibility during migration
- ✅ No breaking changes in existing AI service
- ✅ Focus on Tools services only (as requested)
