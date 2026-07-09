---
sidebar_position: 3
---

# AI Models and Enrichment

This article explains the `AiModel` value-object structure, how capability and flag metadata is determined, and how the enrichment pipeline fills in what provider APIs don't tell you directly.

## The `AiModel` Eloquent Model

`App\Models\Ai\AiModel` is the central model record for each AI model available in HAWKI. Several of its attributes are structured value objects rather than plain scalars, giving you typed access to the model's properties:

| Attribute | Type | What it represents |
|---|---|---|
| `input` / `output` | `AiModelIoMethods` | Supported input and output modalities |
| `parameters` | `AiModelParameters` | Default sampling parameters (temperature, top-p, max tokens) |
| `capabilities` | `ModelCapabilities` | Capability flags active for this model |
| `status` | `OnlineStatus` | Whether the model is currently reachable |
| `demand` | `ModelDemand` | Demand/load classification |
| `flags` | `AiModelFlags` | Feature and character flag set |

These value objects are persisted as cast JSON columns via `AsInstance` casts. They are hydrated automatically when you access the attribute.

## Capabilities

Capabilities represent features a model can perform. They are defined as string constants in `WellKnownCapabilities`:

| Constant | Key | Meaning |
|---|---|---|
| `WEB_SEARCH` | `web_search` | The model has access to a web search tool |
| `WEB_FETCH` | `web_fetch` | The model can fetch and read the content of a specific URL |
| `CODE_EXECUTION` | `code_execution` | The model can run code in a sandboxed environment |
| `KNOWLEDGE_BASE` | `knowledge_base` | The model can query a connected knowledge base (RAG) |
| `TOOL_CALLING` | `tool_calling` | The model can call function tools (required for all other capabilities) |

:::note
All capabilities — even "native" ones like Google Search grounding — are ultimately implemented as tools under the hood. The `tool_calling` model setting must be enabled for any capability to function. Native capabilities differ only in whether they go through HAWKI's `ToolInterface` or through a provider-built-in mechanism via `getNativeToolFactoryForCapability()`.
:::

Capabilities are stored per model in the database and exposed to the frontend via the `native_capabilities` field on the `ai-models` JSON:API resource. The frontend's `AiModelStore` reads this field to determine which UI affordances to show.

## Model Flags

Flags describe the character and feature set of a model. They are string constants defined in `WellKnownModelFlags`. Unlike capabilities (which describe what a model *does*), flags describe what a model *is* or *supports*.

**Character flags** — shown to users as model characteristics:

| Flag | Meaning |
|---|---|
| `open-weights` | Open-source weights, freely usable |
| `eco-friendly` | Low carbon footprint |
| `self-hosted` | Running on HAWKI's own infrastructure (no third-party data-privacy concerns) |
| `multi-modal` | Can process text, images, and other modalities |
| `strength-creative-writing` | Strong at creative and narrative content |
| `strength-code-generation` | Strong at code generation |
| `strength-math` | Strong at mathematical reasoning |
| `strength-role-playing` | Strong at role-playing and dialogue |
| `strength-reasoning` | Strong at complex reasoning tasks |

**Feature flags** — used internally for conditional behaviour:

| Flag | Meaning |
|---|---|
| `feature-streaming` | Supports streaming output |
| `feature-sampling-parameters` | Supports temperature, top-p, max-tokens overrides |
| `feature-response-schema` | Supports structured JSON output with a schema |
| `feature-prompt-caching` | Supports prompt caching |
| `feature-reasoning-none` through `feature-reasoning-max` | Reasoning effort can be set to the named level |

Feature flags drive conditional logic throughout the agent layer. For example, `AbstractTextGeneratingAgent` checks `hasFeatureSamplingParameters()` before forwarding temperature and top-p values — if the flag is absent, `null` is returned so the provider applies its own defaults.

## The Enrichment Pipeline

When a provider's model list is fetched (via `ai:config:sync`), HAWKI knows the model's identifier but may not know its context window, pricing, supported flags, or documentation URL. The enrichment pipeline fills in those gaps.

`AiModelInfoEnrichmentPipeline` (`#[Singleton]`) is an ordered, injectable collection of `ModelInfoEnricherInterface` implementations. Enrichers are registered via the `register()` method with optional topological ordering constraints.

**The `ModelInfoEnricherInterface` contract:**

```php
interface ModelInfoEnricherInterface
{
    public function enrichModelInfo(
        AiModel         $modelInfo,
        AiProviderProxy $provider,
        JobMetrics      $jobMetrics
    ): AiModel;
}
```

Each enricher receives a partially-populated `AiModel`, adds its data, and returns the updated instance. Enrichers must be non-destructive — they should only fill fields that are not yet set, so earlier enrichers' data is preserved.

**Built-in enrichers:**

### `LiteLlmApiEnricher`

The primary enricher. It queries the [LiteLLM model catalog API](https://api.litellm.ai/model_catalog) to retrieve pricing, context window sizes, feature flags, and model mode (chat / image / etc.). Results are cached for 24 hours via `LiteLlmApiDataStore`.

When the live API is unavailable, it falls back to `StaticLiteLlmDataStore`, which reads from pre-generated PHP files committed to the repository at `resources/static_llm_data/lite_llm/`. These static files cover 100+ providers and are refreshed locally via `dev:ai:update-lite-llm-static-data`.

### `StaticDocumentationUrlEnricher`

Adds documentation URLs for known providers based on a hardcoded mapping. This runs after `LiteLlmApiEnricher` so it only fills in the URL if not already set.

### `StaticGwdgEnricher`

Adds GWDG-specific metadata for models hosted on GWDG Academic Cloud infrastructure.

## Per-Model Descriptions and Flags (JSON:API)

Two JSON:API resources expose model metadata to the frontend:

- **`ai-model-descriptions`** — locale-aware textual descriptions of models. Filtered by the `LocaleAwareScope` contextual scope so each client only sees its language.
- **`ai-model-flags`** — flag metadata with human-readable UI labels (title, description). The `AiModelCapabilityRegistry` drives what labels appear alongside each flag.

## `ModelPermissionFilterEvent`

`ModelPermissionFilterEvent` is a filter event (`DispatchableFilter`) that lets listeners control which models a particular user can see. Listeners registered via event auto-discovery can inspect the current user and remove models from the collection before it is returned by the API.

This is the primary access-control hook for per-user or per-group model visibility — for example, hiding non-self-hosted models for users in a restricted group.

## Key Classes

| Class | Location | Role |
|---|---|---|
| `AiModel` | `app/Models/Ai/` | Central model record with typed value objects |
| `WellKnownCapabilities` | `Models/Capabilities/Values/` | Capability key constants |
| `WellKnownModelFlags` | `Models/Flags/Values/` | Flag key constants |
| `AiModelInfoEnrichmentPipeline` | `ModelInformation/Enrichment/` | Ordered enricher collection |
| `ModelInfoEnricherInterface` | `ModelInformation/Enrichment/Contracts/` | Enricher contract |
| `LiteLlmApiEnricher` | `ModelInformation/Enrichment/Implementations/` | Primary LiteLLM-backed enricher |
| `StaticLiteLlmDataStore` | `ModelInformation/Enrichment/Implementations/LiteLlm/` | Static fallback data source |
| `LiteLlmApiDataStore` | `ModelInformation/Enrichment/Implementations/LiteLlm/` | 24h-cached live API store |
| `StaticDocumentationUrlEnricher` | `ModelInformation/Enrichment/Implementations/` | Documentation URL enricher |
| `StaticGwdgEnricher` | `ModelInformation/Enrichment/Implementations/` | GWDG-specific enricher |
