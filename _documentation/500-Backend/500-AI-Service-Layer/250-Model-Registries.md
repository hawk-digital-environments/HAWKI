---
sidebar_position: 4
---

# Model Registries

HAWKI exposes three extensible singleton registries that control per-model runtime behaviour, UI metadata, and usage-context assignment. These are the primary extension points for adapter authors and plugin developers who need to add new model settings, capabilities, or usage rules without modifying core code.

## `AiModelSettingRegistry`

`App\Services\Ai\Models\Settings\AiModelSettingRegistry` (`#[Singleton]`) is the registry for per-model runtime behaviour toggles. A setting is a named key with a default value. Every setting key must be declared here before it can be written to a model or read at runtime.

At runtime, `AiModelSettings` (the value object on `AiModel`) reads the declared default from this registry as a fallback when no explicit per-model value has been stored in the database.

**Built-in keys** (`WellKnownModelSettings`):

| Key | Type | Meaning |
|---|---|---|
| `max_tool_calling_rounds` | `int` | Maximum non-streaming tool-call rounds before giving up (prevents infinite loops) |
| `max_tool_calling_rounds_streaming` | `int` | Same limit for streaming calls |
| `file_upload` | `bool` | Whether file uploads are allowed for this model |
| `tool_calling` | `bool` | Whether the model is allowed to call tools at all |
| `native_capabilities` | `bool` | Whether provider-native tools (e.g. OpenAI web search) are allowed |

**Declaration API:**

```php
$registry->declare(
    key: 'my_plugin.feature_x',
    defaultValue: false,
    titleTranslationLabel: 'settings.feature_x.title',
    descriptionTranslationLabel: 'settings.feature_x.description',
);
```

**Extension point:**

```php
// In your ServiceProvider::boot()
$this->app->extend(
    AiModelSettingRegistry::class,
    fn(AiModelSettingRegistry $registry) => $registry
        ->declare('my_provider.max_context_length', 4096, 'settings.max_context.title')
);
```

:::info[Extension point]
`AiModelSettingRegistry` is marked `@api`. Use this pattern in your service provider to add custom per-model toggles without touching core registration code.
:::

## `AiModelCapabilityRegistry`

`App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry` (`#[Singleton]`) is the parallel registry for capability declarations with UI metadata. While `WellKnownCapabilities` defines the key constants, this registry is where those keys get their human-readable title, description, and icon — the data the frontend uses to display what a model can do.

**Declaration API:**

```php
$registry->declare(
    key: WellKnownCapabilities::WEB_SEARCH,
    titleTranslationLabel: 'capabilities.web_search.title',
    descriptionTranslationLabel: 'capabilities.web_search.description',
    iconPath: resource_path('icons/web-search.svg'), // absolute filesystem path
);
```

The `iconPath` must be an absolute filesystem path, not a public URL. The API layer converts it to a base64 data URI before sending it to clients.

**Extension point:**

```php
// In your ServiceProvider::boot()
$this->app->extend(
    AiModelCapabilityRegistry::class,
    fn(AiModelCapabilityRegistry $registry) => $registry
        ->declare(
            'my_capability',
            'my_plugin.capability.title',
            'my_plugin.capability.description',
            resource_path('icons/my-capability.svg')
        )
);
```

The registry is iterable and yields `AiModelCapabilityDefinition` objects keyed by capability key. The `ai-model-flags` JSON:API resource uses this iterator to build the capability list in the API response.

:::info[Extension point]
`AiModelCapabilityRegistry` is marked `@api`. Register custom capabilities alongside their UI labels using the same `$app->extend()` pattern.
:::

## Usage Rules and `WellKnownModelTypes`

Model usage rules answer the question: "in which contexts does this model appear?" A model must have an assigned usage type to show up in the corresponding context — for example, a model without the `chat` usage type will not appear in the chat model selector.

This is a distinct access-control layer from flags (what a model *is*) and capabilities (what a model *can do*). Usage rules control *where* a model is visible.

**`WellKnownModelTypes`** defines the built-in usage types:

| Constant | Key | Context |
|---|---|---|
| `CHAT` | `chat` | Standard chat interface |
| `IMAGE_GENERATION` | `image_generation` | Image generation requests |
| `VIDEO_GENERATION` | `video_generation` | Video generation requests |

**`AiModelUsageRuleRepository`** manages assignments:

```php
// Assign a usage type to a model
$repository->assignTypeToModel($model, WellKnownModelTypes::CHAT);

// Remove a usage type from a model
$repository->removeTypeFromModel($model, WellKnownModelTypes::IMAGE_GENERATION);

// Toggle — assign if $enabled, remove if not
$repository->toggleTypeOfModel($model, WellKnownModelTypes::CHAT, $enabled);
```

Each rule is a `(ai_model_id, usage_type)` pair stored in the `ai_model_usage_rules` table. `assignTypeToModel()` is idempotent — calling it when the assignment already exists returns the existing rule without error.

The `UsageTypeFilterScope` contextual scope uses these rules to filter the `ai_models` query, so repositories and controllers automatically see only the models relevant to the current usage context (`UsageContext`) without having to apply the filter manually.

## Reference: `WellKnownCapabilities` and `WellKnownModelFlags`

These two PHP interfaces are the authoritative source for string constants used throughout the AI layer. Use them when:

- Writing an enricher that sets flags on a model.
- Checking a model's flags before deciding how to handle a request.
- Declaring capabilities in `AiModelCapabilityRegistry`.
- Implementing `getNativeToolFactoryForCapability()` on a provider adapter.

Always reference the constant (e.g. `WellKnownCapabilities::WEB_SEARCH`) rather than the raw string (`'web_search'`). This gives you a compile-time reference that IDEs can navigate and refactors can track.

## Key Classes

| Class | Location | Role |
|---|---|---|
| `AiModelSettingRegistry` | `Models/Settings/` | Per-model runtime behaviour toggles |
| `WellKnownModelSettings` | `Models/Settings/Values/` | Built-in setting key constants |
| `AiModelCapabilityRegistry` | `Models/Capabilities/` | Capability declarations with UI metadata |
| `AiModelCapabilityDefinition` | `Models/Capabilities/Values/` | Capability metadata value object |
| `WellKnownCapabilities` | `Models/Capabilities/Values/` | Capability key constants |
| `WellKnownModelFlags` | `Models/Flags/Values/` | Model flag key constants |
| `AiModelUsageRuleRepository` | `Models/Repositories/` | Usage rule CRUD |
| `WellKnownModelTypes` | `Models/ModelTypes/Values/` | Usage type constants |
