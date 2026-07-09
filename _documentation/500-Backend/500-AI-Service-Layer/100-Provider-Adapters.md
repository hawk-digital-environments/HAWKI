---
sidebar_position: 2
---

# Provider Adapters

This article covers the pluggable provider system: how adapters are declared, what contract they must fulfil, how the driver is built for each provider, and the gateway-level extensions HAWKI adds on top of the Laravel AI SDK.

## What Is a Provider Adapter?

A provider adapter is the bridge between a specific external AI service (OpenAI, Anthropic, Gemini, Ollama, etc.) and HAWKI's generic agent and model infrastructure. Each adapter encapsulates everything HAWKI needs to know about one provider: how to create its driver, how to list its models, how to check their status, and whether it supports certain capabilities natively.

Adapters are registered by string key in `ProviderAdapterRegistry`. Every persisted `AiProvider` record in the database carries an `adapter_key` field that selects which adapter handles requests for that provider.

## `ProviderAdapterInterface` — All 8 Methods

`App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface` defines the full contract. There are 8 methods — not all of them require complex implementations. `AbstractProviderAdapter` provides no-op or sensible defaults for the optional ones.

### `getNameLabel(): string|null`

Returns a translation label key for the provider's display name as shown in the admin UI, or `null` to fall back to the name stored in the database record.

### `getDescriptionLabel(): string|null`

Returns a translation label key for the provider's description, or `null` when no description should be displayed.

### `createDriver(AiProvider $provider, DriverFactory $factory): Driver`

Instantiates the framework-level Laravel AI driver for a persisted `AiProvider` record. The `$factory` parameter is a `DriverFactory` created specifically for this provider — adapters call `$factory->make($driverName, $config)` rather than resolving the manager themselves.

Typical implementation:

```php
public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
{
    return $factory->make('openai', [
        'api_key' => $provider->settings->api_key,
        'url' => $provider->settings->url,
    ]);
}
```

### `getModels(AiProviderProxy $provider): Collection`

Queries the provider's REST API and returns a collection of unsaved `AiModel` instances. These are hydrated but not persisted — the enrichment pipeline and sync command handle persistence. Implementations typically call the provider's `/v1/models` endpoint (or equivalent) and map each entry to an `AiModel`.

### `checkModelStatus(AiModelOnlineStatusCollection, AiModelDemandCollection, AiProviderProxy): void`

Probes the provider to mark each known model as online or offline. The default implementation in `AbstractProviderAdapter` calls `getModels()` and marks every returned model as online. Override this when the provider exposes a dedicated health or availability endpoint.

### `getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array`

Returns provider-specific options to merge into the agent request just before dispatch. Return an empty array when the provider needs no extra options. This is the hook Anthropic's adapter uses to forward extended-thinking settings from the request context.

### `supportsFileAsAttachment(FileInterface $file): bool`

Returns whether the provider can accept the given file as a native inline attachment. When `false`, HAWKI falls back to embedding the file's text content in the message instead of uploading it directly.

### `getNativeToolFactoryForCapability(string $capability): ?Closure`

Returns a factory closure for a provider-built-in tool matching a `WellKnownCapabilities` key, or `null` when the provider does not natively support that capability.

The closure receives the current `AgentRequestContext` and the tool's settings array and must return a framework `ProviderTool` instance. For example, OpenAI's web search and Gemini's Google Search grounding are implemented via this method — the adapter returns a factory for the native tool, and HAWKI uses it instead of falling back to a function-calling tool.

## `ProviderAdapterRegistry`

`App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry` (`#[Singleton]`) is the central map from string keys to adapter class names. Adapter instances are resolved from the container lazily via a `LazySingletonList` and cached for the lifetime of the request.

**Registration (built-in adapters, in `AiServiceProvider`):**

```php
$this->app->extend(
    ProviderAdapterRegistry::class,
    fn(ProviderAdapterRegistry $registry) => $registry
        ->declare(WellKnownAdapterKeys::OPENAI, OpenAiAdapter::class)
        ->declare(WellKnownAdapterKeys::ANTHROPIC, AnthropicAdapter::class)
        // ...
);
```

**Adding a custom adapter (in your own `ServiceProvider::boot()`):**

```php
$this->app->extend(
    ProviderAdapterRegistry::class,
    fn(ProviderAdapterRegistry $registry) => $registry
        ->declare('my_provider', MyProviderAdapter::class)
);
```

No core code change is required. The `declare()` method validates that the class exists and implements `ProviderAdapterInterface`, then stores the class name. Instantiation is deferred to the first call to `get()`.

:::info[Extension point]
`ProviderAdapterRegistry::declare()` is marked `@api`. It is the primary extension point for adding new AI providers to HAWKI. Plugins will use this exact call in their service provider — the wiring becomes automatic in v3 via the plugin system, but the API itself does not change. See [Plugin System Preview](../1000-Infrastructure/100-Plugin-System-Preview.md).
:::

**Built-in adapter keys** (`WellKnownAdapterKeys`):

`anthropic`, `openai`, `openai_like`, `openai_azure`, `ollama`, `open_web_ui`, `gemini`, `gwdg`, `open_router`, `mistral`, `zai`, `huggingface`, `deepseek`, `xai`, `aws_bedrock`, `cohere`

## `DriverFactory` and `DriverFactoryFactory`

These two classes separate "which config to use" from "which driver to create", making adapters easier to test and reuse.

**`DriverFactoryFactory`** creates one `DriverFactory` per `AiProvider` record. It is an internal factory-for-factories, used by the provider resolution pipeline to ensure each provider gets its own scoped factory.

**`DriverFactory`** is what adapters actually receive in `createDriver()`. Its `make()` method assembles configuration from three layers (last wins):

1. HAWKI defaults: `name`, `driver`, `store = false`.
2. Provider-level adapter settings from `ProviderSettings::getAdapterSettings()` (stored in the DB record's `settings` column).
3. The adapter-supplied `$config` array (e.g. `api_key`, `url`).

After merging, `DriverFactory::make()` calls `ExtendedAiManager::instanceWithConfig()` to resolve the gateway. An optional `$builder` closure can be passed when the driver requires constructor-injected framework services (e.g. an event dispatcher).

## `ExtendedAiManager` and `ProviderDriverPortal`

These two classes work together to let HAWKI pass a pre-resolved `Driver` instance through the Laravel AI `stream()` / `prompt()` API, which only accepts plain strings for the provider name.

**`ProviderDriverPortal`** (`App\Services\Ai\LaravelAi\Values\ProviderDriverPortal`) is a one-shot static transfer registry. The flow:

1. Before calling `stream()`, `AbstractLaravelAgent` registers the pre-built `Driver` in the portal and receives a portal object whose `__toString()` returns a generated transfer ID string.
2. The transfer ID is passed as the `provider` string to `stream()`.
3. `ExtendedAiManager::instance()` checks whether the string is an active transfer ID. If it is, it retrieves the `Driver` from the portal (consuming and removing the entry) and returns it immediately.

Each entry is consumed exactly once. If the transfer ID is unknown or already consumed, `InvalidTransferIdException` is thrown.

**`ExtendedAiManager`** (`App\Services\Ai\LaravelAi\ExtendedAiManager`) is a decorator on Laravel AI's `AiManager`, wired in via `DecoratorTrait::createDecoratedOf()`. Beyond portal resolution it provides:

- **`instanceWithConfig($name, $config)`** — resolves a driver with an ephemeral config array that is applied only for this call and then discarded. Uses a `try/finally` to guarantee the previous config is restored even on exception.
- **`getDefaultInstance()` always throws** — because HAWKI always resolves providers by explicit name. There is no meaningful global default provider.

## Gateway Extensions

The Laravel AI SDK defines `Citation` as a typed `StreamEvent`, but its upstream gateway classes do not actually emit it. HAWKI adds two extended gateways that intercept the SSE stream and emit `Citation` events.

### `ExtendedGeminiGateway`

`App\Services\Ai\LaravelAi\Drivers\GeminiExtended\ExtendedGeminiGateway` extends `GeminiGateway` with two fixes.

**Fix 1 — Request body post-processing.** The upstream `GeminiGateway` serialises provider-supplied `generationConfig` options into a nested `generationConfig.generationConfig` structure instead of flattening them into the top-level object. This gateway calls `buildTextRequestBody()` on the parent, then post-processes the result to:

- Hoist `generationConfig.generationConfig` keys up to the top-level `generationConfig` object.
- Promote `safetySettings` from inside `generationConfig` to the request root, where the Gemini API actually reads them.

Without this fix, provider-level generation config (temperature overrides, safety settings) silently has no effect.

**Fix 2 — Citation extraction.** The gateway overrides `parseServerSentEvents()` to record the last raw SSE data frame. It then overrides `processTextStream()` to yield all parent events as normal, but inserts `Citation` events immediately before the `StreamEnd` event by parsing the final frame.

Two citation formats are supported:
- **Legacy `citationMetadata`** — simple list of `citationSources` URIs.
- **Google Search grounding** — `groundingSupports` entries that map segment byte ranges to `groundingChunks`. Multiple supports for the same URL are merged into a single `UrlMultiCitation` with accumulated byte ranges.

### `ExtendedOpenAiGateway`

`App\Services\Ai\LaravelAi\Drivers\OpenAiExtended\ExtendedOpenAiGateway` extends `OpenAiGateway` with citation extraction only (no request body fix is needed for OpenAI).

The OpenAI Responses API can annotate message content blocks with `url_citation` entries in the final SSE frame's `response.output` array. The gateway records the last SSE frame via `parseServerSentEvents()`, then injects `Citation` events before `StreamEnd` by scanning `output[*].content[*].annotations` for `url_citation` type entries. Multiple annotations for the same URL are merged into one `UrlMultiCitation` with accumulated character-offset ranges.

:::note
Both gateways use `UrlMultiCitation` (a HAWKI value object) rather than the SDK's plain `Citation` payload. `UrlMultiCitation` accumulates multiple byte or character ranges for the same URL, so callers receive one citation object per unique source URL regardless of how many text spans reference it.
:::

## Config Files and Sync

**Static config files.** `config/model_providers.php` and `config/model_lists/` hold deployment-level provider and model configuration. These are read by the sync command, not at runtime.

**`ai:config:sync`** (alias `ai:models:sync`) — reads the static config files and upserts providers and models to the database. The `--force` flag bypasses change-detection hashing and unconditionally re-applies the config.

**Status polling.** The `ai:check-status` command delegates to two separate updater classes:
- `ModelStatusUpdater` — calls `ProviderAdapterInterface::checkModelStatus()` on each adapter and writes `online`/`offline` state to the `ai_models` table.
- `McpServerStatusUpdater` — pings each registered MCP server and writes its status to the `mcp_servers` table.

Adapter authors who override `checkModelStatus()` should be aware that `ModelStatusUpdater` drives the poll cycle, not the adapter itself.

## Key Classes

| Class | Location | Role |
|---|---|---|
| `ProviderAdapterInterface` | `Providers/Adapters/Contracts/` | Adapter contract (8 methods) |
| `AbstractProviderAdapter` | `Providers/Adapters/` | No-op defaults for optional methods |
| `ProviderAdapterRegistry` | `Providers/Adapters/` | Key → adapter class map |
| `WellKnownAdapterKeys` | `Providers/Adapters/` | Built-in adapter key constants |
| `DriverFactory` | `Providers/Adapters/` | Merges config and creates a driver |
| `DriverFactoryFactory` | `Providers/Adapters/` | Creates one `DriverFactory` per provider |
| `ExtendedAiManager` | `LaravelAi/` | Portal resolution + ephemeral config |
| `ProviderDriverPortal` | `LaravelAi/Values/` | One-shot Driver transfer registry |
| `ExtendedGeminiGateway` | `LaravelAi/Drivers/GeminiExtended/` | Request fix + citation extraction |
| `ExtendedOpenAiGateway` | `LaravelAi/Drivers/OpenAiExtended/` | Citation extraction |
| `UrlMultiCitation` | `LaravelAi/Values/` | Multi-range citation value object |
