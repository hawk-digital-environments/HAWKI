# Models & Registries

The `AiModel` Eloquent model, the capabilities and flags it carries, the enrichment pipeline that fills in what provider APIs don't tell you directly, and the registries that expose per-model runtime toggles and capability declarations.

## `AiModel`

`App\Models\Ai\AiModel` is the central record for each AI model available in HAWKI. Several of its attributes are structured value objects rather than plain scalars, hydrated automatically from cast JSON columns via `AsInstance` casts (see [Model Casts](../../200-Concepts/160-Model-Casts/index.md)):

- `input` / `output` — `AiModelIoMethods` (supported modalities)
- `parameters` — `AiModelParameters` (default sampling parameters)
- `capabilities` — `ModelCapabilities`
- `status` — `OnlineStatus`
- `demand` — `ModelDemand`
- `flags` — `AiModelFlags`

The model uses `HasContextualScopesTrait` with `active_filter` and `usage_type_filter` scopes (see [Contextual Scopes](../../200-Concepts/140-Contextual-Scopes.md)). Descriptions live on a separate `AiModelDescription` model with its own `locale_aware` scope.

## Capabilities

Capabilities are manually mapped categories of tools. Multiple tools can share the same capability key, and a model can also declare a "native" capability (handled by the provider internally). See [Tools](./130-Tools.md) for the full tools-vs-capabilities distinction. The capability key constants live in `WellKnownCapabilities`.

## Model flags

Flags describe what a model *is* or *supports*, as string constants in `WellKnownModelFlags`. They split into character flags (shown to users as model characteristics — `open-weights`, `eco-friendly`, `self-hosted`, `multi-modal`, `strength-*` for creative writing / code / math / role-playing / reasoning) and feature flags (used internally for conditional behaviour — `feature-streaming`, `feature-sampling-parameters`, `feature-response-schema`, `feature-prompt-caching`, `feature-reasoning-*`).

Feature flags drive conditional logic throughout the agent layer. For example, `AbstractTextGeneratingAgent` checks `hasFeatureSamplingParameters()` before forwarding temperature and top-p values — if the flag is absent, `null` is returned so the provider applies its own defaults.

Open `WellKnownModelFlags` for the canonical constant list.

## The enrichment pipeline

When a provider's model list is fetched (via `ai:config:sync`), HAWKI knows the model's identifier but may not know its context window, pricing, supported flags, or documentation URL. The enrichment pipeline fills in those gaps.

`AiModelInfoEnrichmentPipeline` (`#[Singleton]`) is an ordered, injectable collection of `ModelInfoEnricherInterface` implementations:

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

Each enricher receives a partially-populated `AiModel`, adds its data, and returns the updated instance. Enrichers must be non-destructive — fill only fields that are not yet set, so earlier enrichers' data is preserved.

Built-in enrichers:

- **`LiteLlmApiEnricher`** — the primary enricher. Queries the [LiteLLM model catalog API](https://api.litellm.ai/model_catalog) for pricing, context window sizes, feature flags, model mode. Results cached for 24 hours. When the live API is unavailable, falls back to `StaticLiteLlmDataStore`, which reads from pre-generated PHP files at `resources/static_llm_data/lite_llm/` (refreshed via `dev:ai:update-lite-llm-static-data`).
- **`StaticDocumentationUrlEnricher`** — adds documentation URLs for known providers from a hardcoded mapping. Runs after `LiteLlmApiEnricher` so it only fills in the URL if not already set.
- **`StaticGwdgEnricher`** — adds GWDG-specific metadata for models hosted on GWDG Academic Cloud infrastructure.

## Registries — per-model runtime toggles and capabilities

Two registries let you extend per-model metadata without changing core code. The registration pattern is in [Extending HAWKI](../../200-Concepts/220-Extending-HAWKI.md):

- **`AiModelSettingRegistry`** — per-model runtime toggles with default values and descriptions. Built-in keys live in `WellKnownModelSettings`: `max_tool_calling_rounds` / `max_tool_calling_rounds_streaming` (prevent infinite tool-call loops), `file_upload`, `tool_calling`, `native_capabilities`.
- **`AiModelCapabilityRegistry`** — capability declarations with UI metadata. Drives what labels appear alongside each flag on the `ai-model-flags` JSON:API resource.

## Usage types

Usage rules answer: "in which contexts does this model appear?" A model must have an assigned usage type to show up in the corresponding context — a model without the `chat` usage type will not appear in the chat model selector. This is a distinct access-control layer from flags (what a model *is*) and capabilities (what a model *can do*). Usage rules control *where* a model is visible.

`WellKnownModelTypes` defines the built-in usage types: `CHAT` (standard chat interface), `IMAGE_GENERATION`, `VIDEO_GENERATION`. Open the class for the canonical list.

`AiModelUsageRuleRepository` manages assignments:

```php
$repository->assignTypeToModel($model, WellKnownModelTypes::CHAT);
$repository->removeTypeFromModel($model, WellKnownModelTypes::IMAGE_GENERATION);
$repository->toggleTypeOfModel($model, WellKnownModelTypes::CHAT, $enabled);
```

Each rule is a `(ai_model_id, usage_type)` pair in the `ai_model_usage_rules` table. `assignTypeToModel()` is idempotent. The `UsageTypeFilterScope` contextual scope (see [Contextual Scopes](../../200-Concepts/140-Contextual-Scopes.md)) uses these rules to filter `ai_models` queries automatically based on the current `UsageContext`, so repositories and controllers see only the models relevant to the current usage context without applying the filter manually.

`AiModelUsageRuleRepository` is a repository — see [Repositories](../../200-Concepts/130-Repositories.md) for the base pattern.

## `ModelPermissionFilterEvent`

A filter event (`DispatchableFilter`) that lets listeners control which models a particular user can see. Listeners registered via event auto-discovery (see [Events & Listeners](../../200-Concepts/170-Events-and-Listeners.md)) can inspect the current user and remove models from the collection before it is returned by the API. The primary access-control hook for per-user or per-group model visibility — for example, hiding non-self-hosted models for users in a restricted group.
