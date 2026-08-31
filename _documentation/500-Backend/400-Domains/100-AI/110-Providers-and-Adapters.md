# Providers & Adapters

The pluggable provider system: how adapters are declared, what contract they fulfil, how the driver is built per provider, and the gateway-level extensions HAWKI adds on top of the Laravel AI SDK.

## What a provider adapter is

A provider adapter is the bridge between HAWKI and the underlying `laravel/ai` SDK. The adapter tells HAWKI how to create the SDK driver for a specific provider, how to list its models, how to check their status, and whether it supports certain capabilities natively. The actual connection to the external AI service (OpenAI, Anthropic, Gemini, Ollama, …) is then handled by the SDK's gateway classes.

Adapters are registered by string key in `ProviderAdapterRegistry`. Every persisted `AiProvider` record in the database carries an `adapter_key` field that selects which adapter handles requests for that provider.

## `ProviderAdapterInterface`

`App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface` defines eight methods. `AbstractProviderAdapter` provides no-op or sensible defaults for the optional ones — you only override what your provider needs.

The load-bearing methods:

- `createDriver(AiProvider $provider, DriverFactory $factory): Driver` — instantiates the framework-level Laravel AI driver. Call `$factory->make($driverName, $config)` with the provider's settings.

```php
public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
{
    return $factory->make('openai', [
        'api_key' => $provider->settings->api_key,
        'url'     => $provider->settings->url,
    ]);
}
```

- `getModels(AiProviderProxy $provider): Collection` — queries the provider's REST API and returns unsaved `AiModel` instances. Hydrated but not persisted; the enrichment pipeline and sync command handle persistence.
- `checkModelStatus(...)` — probes the provider to mark each known model online/offline. The default implementation in `AbstractProviderAdapter` calls `getModels()` and marks every returned model as online. Override when the provider exposes a dedicated health endpoint.
- `getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array` — returns provider-specific options merged into the agent request just before dispatch. Anthropic uses this to forward extended-thinking settings.
- `supportsFileAsAttachment(FileInterface $file): bool` — whether the provider can accept the given file as a native inline attachment. When `false`, HAWKI falls back to embedding the file's text content in the message.
- `getNativeToolFactoryForCapability(string $capability): ?Closure` — returns a factory closure for a provider-built-in tool matching a `WellKnownCapabilities` key, or `null`. OpenAI's web search and Gemini's Google Search grounding are wired through this — the adapter returns a factory for the native tool, HAWKI uses it instead of falling back to a function-calling tool.

The display methods (`getNameLabel`, `getDescriptionLabel`) return translation label keys for the admin UI, or `null` to fall back to the DB record's name.

## Register an adapter

```php
$this->app->extend(
    ProviderAdapterRegistry::class,
    fn(ProviderAdapterRegistry $registry) => $registry
        ->declare('my_provider', MyProviderAdapter::class)
);
```

No core code change required. `declare()` validates that the class exists and implements `ProviderAdapterInterface`, then stores the class name. Instantiation is deferred to the first call to `get()`. See [Extending HAWKI](../../200-Concepts/220-Extending-HAWKI.md).

The built-in adapter keys live in `WellKnownAdapterKeys` (`anthropic`, `openai`, `openai_like`, `openai_azure`, `ollama`, `open_web_ui`, `gemini`, `gwdg`, `open_router`, `mistral`, `zai`, `huggingface`, `deepseek`, `xai`, `aws_bedrock`, `cohere`). Open that class for the canonical list.

## `DriverFactory` and `DriverFactoryFactory`

These two classes separate "which config to use" from "which driver to create", making adapters easier to test and reuse.

- `DriverFactoryFactory` creates one `DriverFactory` per `AiProvider` record — an internal factory-for-factories used by the provider resolution pipeline to scope each provider's config.
- `DriverFactory` is what adapters receive in `createDriver()`. Its `make()` method assembles configuration from three layers (last wins): HAWKI defaults (`name`, `driver`, `store = false`); provider-level adapter settings from `ProviderSettings::getAdapterSettings()` (stored in the DB record's `settings` column); the adapter-supplied `$config` array.

After merging, `DriverFactory::make()` calls `ExtendedAiManager::instanceWithConfig()` to resolve the gateway. An optional `$builder` closure can be passed when the driver requires constructor-injected framework services.

## `ExtendedAiManager` and `ProviderDriverPortal`

See [AI Service Layer](./index.md) for the `ProviderDriverPortal` one-shot transfer mechanism. `ExtendedAiManager` is the decorator that resolves transfer IDs and supports `instanceWithConfig()` for ephemeral per-call config. `getDefaultInstance()` always throws — HAWKI has no meaningful global default provider.

## Gateway extensions

:::warning[Temporary — pending upstream PR]
The following gateway extensions are workarounds for limitations in the upstream `laravel/ai` SDK. An [open PR](https://github.com/laravel/ai/pull/778) aims to get citation emission and the `generationConfig` fix into the SDK itself. Once merged, these extended gateways will be removed.
:::

The Laravel AI SDK defines `Citation` as a typed `StreamEvent`, but its upstream gateways do not emit it. HAWKI adds two extended gateways that intercept the SSE stream and emit `Citation` events before `StreamEnd`:

- **`ExtendedGeminiGateway`** — two fixes: (1) hoists `generationConfig.generationConfig` keys up to the top-level `generationConfig` object and promotes `safetySettings` to the request root (without this, provider-level generation config silently has no effect); (2) extracts citations from the final SSE frame, supporting both legacy `citationMetadata` and Google Search grounding `groundingSupports` (merging multiple supports for the same URL into one `UrlMultiCitation`).
- **`ExtendedOpenAiGateway`** — citation extraction only. Scans `output[*].content[*].annotations` for `url_citation` entries in the final SSE frame's `response.output`; merges multiple annotations for the same URL into one `UrlMultiCitation`.

Both gateways use `UrlMultiCitation` (a HAWKI value object) rather than the SDK's plain `Citation` payload, so callers receive one citation object per unique source URL regardless of how many text spans reference it.

## Config and sync

:::danger[DEPRECATED / LEGACY]
The static config file approach (`config/model_providers.php`, `config/model_lists/`, `ai:config:sync`) is legacy. It will be removed once the new admin panel lands, which will let operators configure providers, models, and tools through the UI. Do not build new workflows on top of the config-file sync mechanism.
:::

Static config files `config/model_providers.php` and `config/model_lists/` hold deployment-level provider and model configuration, read by the sync command, not at runtime. `ai:config:sync` (alias `ai:models:sync`) upserts providers and models to the database; `--force` bypasses change-detection hashing. See [Artisan Commands](../../500-Reference/100-Artisan-Commands.md).

`ai:check-status` delegates to `ModelStatusUpdater` (calls `checkModelStatus()` on each adapter) and `McpServerStatusUpdater` (pings each registered MCP server). Adapter authors who override `checkModelStatus()` should be aware that `ModelStatusUpdater` drives the poll cycle, not the adapter itself.
