# Tools

HAWKI's function-calling tool system: how agents gain access to capabilities beyond text generation — web search, knowledge base queries, code execution, and external tool servers.

## Tools vs capabilities

A **tool** is a concrete PHP class (or MCP server function) the agent can call. A **capability** is a manually mapped category that groups multiple tools under a shared key — for example, `web_search` is a capability that could be fulfilled by HAWKI's built-in search tool, a provider-native tool (OpenAI web search, Gemini Google Search grounding), or an MCP-hosted search service. Multiple tools can share the same capability key.

A model can also declare a **native** capability — meaning the provider handles it internally without going through HAWKI's `ToolInterface` (see `getNativeToolFactoryForCapability()` in [Providers & Adapters](./110-Providers-and-Adapters.md)). In the frontend, the user is presented with the option to choose a specific tool or let HAWKI decide automatically which tool to use for a given capability.

See [Models & Registries](./120-Models-and-Registries.md) for the full capabilities concept.

## `ToolInterface`

`App\Services\Ai\Tools\Contracts\ToolInterface` extends Laravel AI's `Tool` interface with one HAWKI-specific method:

```php
interface ToolInterface extends Tool
{
    public function capability(): string|null;
    public function name(): string;
}
```

`capability()` returns the `WellKnownCapabilities` key this tool fulfils (e.g. `'web_search'`, `'knowledge_base'`), or `null` when the tool does not map to any well-known capability. The agent layer uses this to locate the right tool when a model declares that it supports a capability.

## `AbstractTool`

The base class for all HAWKI function-calling tools. Concrete subclasses implement `__invoke()` for their tool logic and provide `name()`, `description()`, and `schema()`.

The invocation flow — Laravel AI calls `handle(Request $request)` on the tool, and `AbstractTool::handle()`:

1. Validates the incoming arguments against the schema returned by `schema()` using `JsonSchemaValidator`.
2. Enforces an optional per-instance call-count ceiling (set via `setMaxRuns()`).
3. Dispatches `__invoke()` with the validated arguments via the `ServiceLocator`, which allows the container to inject additional dependencies (services, repositories) beyond the raw argument map. See [ServiceLocator](../../200-Concepts/110-Dependency-Injection/100-ServiceLocator.md).
4. Converts the return value to a string — JSON-encoding arrays and objects automatically.
5. Catches any exception, logs it, and returns a structured `[ERROR] ...` string that the AI model can understand and decide not to retry.

```php
class GetCurrentWeatherTool extends AbstractTool
{
    public function name(): string { return 'get_current_weather'; }
    public function description(): string { return 'Returns the current weather for a city.'; }
    public function capability(): string|null { return null; }

    public function schema(JsonSchema $schema): array
    {
        return ['city' => $schema->string()->required()];
    }

    // 'city' is resolved from the validated argument map;
    // WeatherClient is injected by the container
    public function __invoke(WeatherClient $client, string $city): array
    {
        return $client->current($city);
    }
}
```

`AbstractTool` also implements `SettingsAwareToolInterface` (`setSettings(array)` / `getSettings()`). The agent layer calls `setSettings()` with the settings stored in the `ai_tools` database table for this tool before invoking it. Inside your tool, `$this->getSettings()` returns the configured settings object — use it to read per-model configuration like API keys or result counts.

## Database-backed tool records

Tools are not just PHP classes — they are persisted in the database so operators can control which tools are enabled, assigned to which models, and with what settings.

`AiTool` (`App\Models\Ai\AiTool`) is the Eloquent model for a tool record. It carries the tool's name, type (`function` or `mcp`), capability key, settings, and an `active` flag. `AiToolCapability` tracks which capability keys are available for each tool. Tools are exposed via the `ai-tools` JSON:API resource, which lets the admin UI manage tool-model assignments.

## Register a function tool

:::warning[Subject to change]
The container-tag registration pattern will be replaced by a registry (like `ProviderAdapterRegistry` and `AgentRegistry`) once the new admin panel lands. See [Technical Debt](../../900-Technical-Debt.md).
:::

Tag the tool class with `ToolInterface::class` (or the `'ai.tool'` tag) in `ServiceProvider::register()`:

```php
$this->app->tag([GetCurrentWeatherTool::class], ToolInterface::class);
```

:::danger[DEPRECATED — `FunctionToolSyncer`]
`FunctionToolSyncer` is deprecated. With the new admin panel, the system will expose an API to retrieve all currently registered function tools from the registry, and operators will configure them through the UI instead of syncing from config files. See [Technical Debt](../../900-Technical-Debt.md).
:::

`FunctionToolSyncer` collects all tagged `ToolInterface` implementations and upserts each into the `ai_tools` table via `AiToolRepository::upsertFunction()`. This runs at deployment time via `ai:tools:sync`. Tools no longer registered are **not** automatically removed — deletion is a deliberate manual step to avoid accidental data loss.

See [Extending HAWKI](../../200-Concepts/220-Extending-HAWKI.md) for the registration pattern.

## Filter events in the tool layer

| Event | When it fires |
|---|---|
| `ToolByNameResolvedFilterEvent` | After a tool is resolved by name — allows replacement |
| `ToolForCapabilityResolvedFilterEvent` | After a tool is resolved for a capability — allows replacement |
| `NativeToolResolvedFilterEvent` | After a native provider tool is resolved |
| `McpToolCalledFilterEvent` | After an MCP tool call completes |

See [Events & Listeners](../../200-Concepts/170-Events-and-Listeners.md) for the filter-event contract.

## `config/tools.php`

Deployment-level configuration, read only by `ai:tools:sync` — not at runtime:

```php
return [
    'available_tools' => [
        \App\Services\Ai\Tools\Implementations\TestTool::class,
        // Add your tool classes here, then run: ai:tools:sync --function-only
    ],
    'mcp_servers' => [
        'my-rag-server' => [
            'type' => 'sse',
            'url'  => env('RAG_MCP_URL', 'http://localhost:8080/mcp'),
        ],
    ],
];
```

Tool names from MCP servers are prefixed with the server's label key to avoid conflicts across servers (a tool named `search` from a server registered as `hawki-rag` becomes `hawki-rag-search`).

See [Artisan Commands](../../500-Reference/100-Artisan-Commands.md) for `ai:tools:sync` and its `--function-only` / `--mcp-only` flags.
