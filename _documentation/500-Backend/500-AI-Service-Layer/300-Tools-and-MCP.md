---
sidebar_position: 5
---

# AI Tools and MCP

This article covers HAWKI's function-calling tool system and the Model Context Protocol (MCP) integration. Together they are how HAWKI's agents gain access to capabilities beyond text generation — web search, knowledge base queries, code execution, and external tool servers.

## `ToolInterface` — HAWKI's Tool Contract

`App\Services\Ai\Tools\Contracts\ToolInterface` extends Laravel AI's `Tool` interface with one HAWKI-specific method:

```php
interface ToolInterface extends Tool
{
    public function capability(): string|null;
    public function name(): string;
}
```

`capability()` returns the `WellKnownCapabilities` key that this tool fulfils (e.g. `'web_search'`, `'knowledge_base'`), or `null` when the tool does not map to any well-known capability. The agent layer uses this to locate the right tool when a model declares that it supports a capability.

## `AbstractTool` — The Base Implementation

`App\Services\Ai\Tools\AbstractTool` is the base class for all HAWKI function-calling tools. Concrete subclasses implement `__invoke()` for their tool logic and provide `name()`, `description()`, and `schema()`.

**The invocation flow.** Laravel AI calls `handle(Request $request)` on the tool. `AbstractTool::handle()`:

1. Validates the incoming arguments against the schema returned by `schema()` using `JsonSchemaValidator`.
2. Enforces an optional per-instance call-count ceiling (set via `setMaxRuns()`).
3. Dispatches `__invoke()` with the validated arguments via the `ServiceLocator`, which allows the container to inject additional dependencies (services, repositories) beyond the raw argument map.
4. Converts the return value to a string — JSON-encoding arrays and objects automatically.
5. Catches any exception, logs it, and returns a structured `[ERROR] ...` string that the AI model can understand and decide not to retry.

**Example:**

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

**Settings.** `AbstractTool` also implements `SettingsAwareToolInterface`, which exposes a `setSettings(array)` / `getSettings()` pair. The agent layer calls `setSettings()` with per-model tool configuration before invoking the tool. Use this when a tool has configurable behaviour that varies per model assignment (e.g. API keys, result counts).

## Database-Backed Tool Records

Tools are not just PHP classes — they are persisted in the database so operators can control which tools are enabled, assigned to which models, and with what settings.

**`AiTool`** (`App\Models\Ai\AiTool`) is the Eloquent model for a tool record. It carries the tool's name, type (`function` or `mcp`), capability key, settings, and an `active` flag.

**`AiToolCapability`** is a related model that tracks which capability keys are available for each tool.

Tools stored in the database are exposed via the `ai-tools` JSON:API resource, which allows the admin UI to manage tool-model assignments.

## Registering Function Tools

Function tools (PHP classes) are registered with the service container using the `ToolInterface::class` tag:

```php
// In your ServiceProvider::register()
$this->app->tag([GetCurrentWeatherTool::class], ToolInterface::class);
```

`FunctionToolSyncer` collects all tagged `ToolInterface` implementations and upserts each into the `ai_tools` table via `AiToolRepository::upsertFunction()`. This runs at deployment time via `ai:tools:sync`.

Tools no longer registered are **not** automatically removed — deletion is a deliberate manual step to avoid accidental data loss.

:::info[Extension point]
The container tag pattern is plugin-ready today. `$this->app->tag([MyTool::class], ToolInterface::class)` in your plugin's `ServiceProvider::register()` is sufficient to register a custom tool. `FunctionToolSyncer` and tag-based discovery already support this without any core API change. See [Plugin System Preview](../1000-Infrastructure/100-Plugin-System-Preview.md).
:::

## MCP Integration

[Model Context Protocol (MCP)](https://modelcontextprotocol.io/) is an open protocol for connecting AI agents to external tool servers. HAWKI integrates with MCP servers for tools that run outside the PHP process — for example, a RAG server, a web search service, or any third-party tool endpoint.

### `McpServer` — The Persisted Record

`App\Models\Ai\McpServer` is the Eloquent model for a registered MCP server. It stores the server URL, transport type (`sse` or `rest`), authentication config, and online status. Records are managed via the `mcp-servers` JSON:API resource and the `ai:tools:mcp:add` / `mcp:remove` artisan commands.

### `HawkiMcpClient` — Lazy Session Management

`App\Services\Ai\Tools\Mcp\HawkiMcpClient` wraps a single MCP `ClientSession` with two layers of laziness:

- **Session creation** — the underlying `ClientSession` is created on first use via an injected factory closure and reused for the lifetime of the instance.
- **Tool caching** — tool definitions fetched via `listToolDefinitions()` are cached in a `RewindableGenerator` after the first call. Repeated calls within the same request do not re-query the MCP server.

Key methods:
- `ping()` — used by `McpServerStatusUpdater` for health checks. Returns `false` on failure without throwing.
- `callTool(name, arguments)` — invokes a named tool and returns a `CallToolResult`. Errors are logged before re-throwing.
- `listToolDefinitions()` — yields `McpToolDefinition` value objects for all tools the server advertises. An optional `hawkiCapability` field on each raw tool definition maps the tool to a HAWKI capability key.

Instances are managed as lazy singletons per `McpServer` via a `LazySingletonList` binding in `AiServiceProvider`.

### `McpToolSyncer` — Discovery and Sync

`App\Services\Ai\Tools\Mcp\McpToolSyncer` runs at deployment time (via `ai:tools:sync --mcp-only`) to discover and persist MCP tools:

1. Iterates all registered `McpServer` records whose status is `ONLINE`.
2. For each server, opens a `HawkiMcpClient` and calls `listToolDefinitions()`.
3. Upserts each tool into the `ai_tools` table via `AiToolRepository::upsertMcp()`, collecting the IDs of all synced tools.
4. After all tools for a server are processed, calls `AiToolRepository::removeAllMcpToolsOf()` to delete rows for tools no longer advertised by the server.

Individual tool failures are caught without aborting the rest of the server's sync. Server-level failures are similarly isolated.

### `BeforeCallingMcpToolFilterEvent` — Intercept and Mock

`BeforeCallingMcpToolFilterEvent` is a filter event (`DispatchableFilter`) that fires immediately before any MCP tool call. Listeners receive the tool arguments, the `AiTool` record, and the `HawkiMcpClient`, and can short-circuit the real MCP call by calling `setResult()` with a synthetic response.

This is essential for testing — without this hook you would need a real MCP server running to test agent behaviour. With it, a test listener can inject a predetermined result:

```php
Event::listen(BeforeCallingMcpToolFilterEvent::class, function ($event) {
    if ($event->getTool()->name === 'my_mcp_tool') {
        $event->setResult(json_encode(['answer' => 'mocked value']));
    }
});
```

Other filter events in the tool layer:

| Event | When it fires |
|---|---|
| `ToolByNameResolvedFilterEvent` | After a tool is resolved by name — allows replacement |
| `ToolForCapabilityResolvedFilterEvent` | After a tool is resolved for a capability — allows replacement |
| `NativeToolResolvedFilterEvent` | After a native provider tool is resolved |
| `McpToolCalledFilterEvent` | After an MCP tool call completes |

## Configuration: `config/tools.php`

`config/tools.php` is the deployment-level configuration file for function tools and MCP servers. It is read only by `ai:tools:sync` — not at runtime.

```php
return [
    // Function-calling tools (PHP classes)
    'available_tools' => [
        \App\Services\Ai\Tools\Implementations\TestTool::class,
        // Add your tool classes here, then run: ai:tools:sync --function-only
    ],

    // MCP servers
    'mcp_servers' => [
        'my-rag-server' => [
            'type' => 'sse',
            'url'  => env('RAG_MCP_URL', 'http://localhost:8080/mcp'),
            // 'api_key' => env('RAG_MCP_API_KEY'),  // sent as Bearer token
        ],
    ],
];
```

Tool names from MCP servers are prefixed with the server's label key to avoid conflicts across servers (e.g. a tool named `search` from a server registered as `hawki-rag` becomes `hawki-rag-search`).

## The `ai:tools:sync` Command

`ai:tools:sync` is the single command that synchronises all tool definitions from config to the database. It can be targeted with flags:

- `--function-only` — sync only PHP function tools.
- `--mcp-only` — sync only MCP server tools.

Run this command at deployment time after any change to `config/tools.php` or after registering a new tool class.

## Key Classes

| Class | Location | Role |
|---|---|---|
| `ToolInterface` | `Tools/Contracts/` | HAWKI tool contract (extends Laravel AI's `Tool`) |
| `AbstractTool` | `Tools/` | Base class with validation, settings, error handling |
| `FunctionToolSyncer` | `Tools/` | Discovers and upserts tagged tool classes |
| `AiTool` | `app/Models/Ai/` | Eloquent model for a tool record |
| `HawkiMcpClient` | `Tools/Mcp/` | Lazy MCP session with tool caching |
| `McpToolSyncer` | `Tools/Mcp/` | MCP tool discovery and DB sync |
| `McpClientFactory` | `Tools/Mcp/` | Creates `HawkiMcpClient` instances |
| `BeforeCallingMcpToolFilterEvent` | `Tools/LaravelAi/Events/` | Pre-call intercept / mock hook |
| `ToolByNameResolvedFilterEvent` | `Tools/LaravelAi/Events/` | Post-resolution filter for named tools |
| `ToolForCapabilityResolvedFilterEvent` | `Tools/LaravelAi/Events/` | Post-resolution filter for capability tools |
| `AiToolRepository` | `Tools/Repositories/` | Tool CRUD (function + MCP upserts) |
| `McpServerRepository` | `Tools/Repositories/` | MCP server record access |
