---
sidebar_position: 13
---

# AI Models & Tools

This document describes the architecture of HAWKI's AI model registry and tool system, covering how models are configured and persisted, how tools are registered and executed, and how tools are connected to specific models.

## Table of Contents

1. [Overview](#overview)
2. [AI Model System](#ai-model-system)
   - [Configuration Layer](#configuration-layer)
   - [Database Registry](#database-registry)
   - [Model Sync](#model-sync)
   - [Model Online Status](#model-online-status)
   - [Value Object vs Eloquent Model](#value-object-vs-eloquent-model)
3. [Tool System](#tool-system)
   - [Tool Types](#tool-types)
   - [Tool Registry](#tool-registry)
   - [Function-Calling Tools](#function-calling-tools)
   - [MCP Tools](#mcp-tools)
4. [Model–Tool Assignments](#modeltool-assignments)
5. [Database Schema](#database-schema)
6. [Service Providers](#service-providers)
7. [Artisan Commands Reference](#artisan-commands-reference)
8. [How-To Guides](#how-to-guides)

---

## Overview

HAWKI's AI model and tool system is split into two complementary layers:

| Layer | Source of truth | Purpose |
|---|---|---|
| **Config** | `config/model_providers.php` + `config/model_lists/*.php` | Defines available models, their capabilities, and provider settings |
| **Database** | `ai_providers`, `ai_models`, `ai_tools`, `ai_model_tools` tables | Persists model registry, online status, MCP tools, and tool–model assignments |

The config layer drives the service layer at runtime — it is never replaced by the database.
The database layer mirrors the config, tracks live model status, and is the single source of truth for MCP tools and tool–model assignments.

---

## AI Model System

### Configuration Layer

Every AI provider and its models are declared in two levels of configuration files.

#### Provider configuration — `config/model_providers.php`

Defines global defaults, system models, and a list of providers:

```php
'default_models' => [
    'default_model'            => env('DEFAULT_MODEL', 'gpt-4.1-nano'),
    'default_web_search_model' => env('DEFAULT_WEBSEARCH_MODEL', 'gemini-2.0-flash'),
    'default_file_upload_model'=> env('DEFAULT_FILEUPLOAD_MODEL', 'qwen3-omni-30b-a3b-instruct'),
    'default_vision_model'     => env('DEFAULT_VISION_MODEL', 'qwen3-omni-30b-a3b-instruct'),
],

'system_models' => [
    'title_generator' => env('TITLE_GENERATOR_MODEL', 'gpt-4.1-nano'),
    'prompt_improver' => env('PROMPT_IMPROVEMENT_MODEL', 'gpt-4.1-nano'),
    'summarizer'      => env('SUMMARIZER_MODEL', 'gpt-4.1-nano'),
],

'providers' => [
    'openAi' => [
        'active'   => env('OPENAI_ACTIVE', true),
        'api_key'  => env('OPENAI_API_KEY'),
        'api_url'  => env('OPENAI_URL', 'https://api.openai.com/v1/responses'),
        'ping_url' => env('OPENAI_PING_URL', 'https://api.openai.com/v1/models'),
        'models'   => require __DIR__ . '/model_lists/openai_models.php',
    ],
    // ... gwdg, google, ollama, openWebUi
]
```

API keys are **never** stored in the database; they always come from environment variables.

#### Model list files — `config/model_lists/*.php`

Each file returns an array of model definitions:

```php
// config/model_lists/openai_models.php
return [
    [
        'active'        => env('MODELS_OPENAI_GPT4_1_ACTIVE', true),
        'id'            => 'gpt-4.1',
        'label'         => 'OpenAI GPT 4.1',
        'input'         => ['text', 'image'],
        'output'        => ['text'],
        'tools' => [
            'stream'       => true,
            'file_upload'  => true,
            'vision'       => true,
            'web_search'   => 'native',
            'knowledge_base' => 'native',
        ],
        'default_params'=> [
            'temp'  => env('MODELS_OPENAI_GPT4_1_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_OPENAI_GPT4_1_PARAMS_TOP_P', 1.0),
        ],
    ],
    // ...
];
```

**Model capability flags** (`tools` array):

| Key | Values | Meaning |
|---|---|---|
| `stream` | `true` / `false` / `'native'` / `'unsupported'` | Whether the model supports streaming |
| `file_upload` | `true` / `false` | Whether the model can receive uploaded files |
| `vision` | `true` / `false` | Whether the model can process images |
| `web_search` | `'native'` / `'unsupported'` | Whether the provider handles web search natively |
| `knowledge_base` | `'native'` / `string` | Strategy for knowledge-base retrieval |
| `<tool_name>` | `string` | Name of a registered tool the model may invoke |

Old boolean values (`true`/`false`) are supported for backward compatibility and are mapped to `'native'` / `'unsupported'` internally.

---

### Database Registry

The database mirrors the config-defined providers and models so that the system has a persistent, queryable record of every known model. This enables:

- Model online-status tracking
- Tool–model relationship management
- Admin tooling (listing, deactivating models without changing config files)

The three relevant Eloquent models are:

| Class | Table | Role |
|---|---|---|
| `App\Models\Ai\AiProvider` | `ai_providers` | One row per provider (e.g. openAi, gwdg) |
| `App\Models\Ai\AiModel` | `ai_models` | One row per model definition |
| `App\Models\Ai\AiModelStatus` | `ai_model_statuses` | Live online/offline status, keyed by `model_id` |

#### Eloquent relationships

```
AiProvider  ──< hasMany   ── AiModel
AiModel     ──  hasOne    ── AiModelStatus   (via model_id string)
AiModel     ──< BelongsToMany >── AiTool     (pivot: ai_model_tools)
```

---

### Model Sync

Because the database registry must reflect the configuration, a dedicated service keeps both layers in sync.

#### `AiModelSyncService`

Location: `app/Services/AI/Db/AiModelSyncService.php`

```php
// Sync all providers and models from config into the database
$syncService->sync();

// Returns true when at least one model row already exists
$syncService->isSynced();
```

Sync rules:
- Providers and models are **created or updated** (`updateOrCreate`).
- Existing database records are **never deleted** — an operator may deactivate a model in the DB independently of config.
- The `active` flag is **always overridden from config** so that environment-variable changes (e.g. `MODELS_OPENAI_GPT5_ACTIVE=false`) take effect on the next sync.

#### Automatic first-run sync

`AppServiceProvider::boot()` calls the sync automatically **once** when running in a CLI context and the `ai_models` table is empty (i.e. fresh installation after migrations):

```
php artisan migrate          # tables created, boot() runs → models synced
php artisan models:sync      # explicit re-sync at any time
php artisan models:sync --force  # force re-sync even if models already exist
```

This ensures that after a fresh `migrate`, no additional manual step is required.

#### Manual sync command

```bash
php artisan models:sync           # sync if not yet synced
php artisan models:sync --force   # always re-sync
php artisan models:list           # review results
```

---

### Model Online Status

Each model's availability is tracked in `ai_model_statuses` with three possible states:

| Status | Meaning |
|---|---|
| `ONLINE` | Provider API responded successfully |
| `OFFLINE` | Provider API unreachable or returned an error |
| `UNKNOWN` | Status has never been checked |

**`ModelStatusDb`** (`app/Services/AI/Db/ModelStatusDb.php`) is the service-layer interface for reading and writing model status. It batch-loads all statuses on first access to avoid N+1 queries.

The scheduled command `check:model-status` runs every 15 minutes and updates the database:

```bash
php artisan check:model-status
```

---

### Value Object vs Eloquent Model

The codebase contains **two** classes named `AiModel` that serve different purposes:

| Class | Location | Role |
|---|---|---|
| `App\Services\AI\Value\AiModel` | Service layer | **Value object** — wraps a raw config array. Used by `AiFactory`, `AiService`, all providers and request converters. Exposes `getTools()`, `getClient()`, `getStatus()`, etc. |
| `App\Models\Ai\AiModel` | Eloquent layer | **Database model** — maps to the `ai_models` table. Used for DB persistence, admin commands, and tool assignments. |

The service layer **never** uses the Eloquent `AiModel` directly; it always works with the value object constructed from config. The Eloquent model is used exclusively for:

1. Persisting model metadata (via `AiModelSyncService`)
2. Tool–model assignment management (pivot `ai_model_tools`)
3. Admin commands (`models:list`, `tools:assign`, etc.)

---

## Tool System

### Tool Types

HAWKI supports two categories of tools that AI models can invoke:

| Type | Config key | Storage | Execution |
|---|---|---|---|
| **Function-calling** | `config/tools.available_tools` | In-memory (no DB) | Runs locally in the PHP process |
| **MCP** | `ai_tools` table | Database | Proxied to an external MCP server over HTTP |

---

### Tool Registry

`App\Services\AI\Tools\ToolRegistry` is a singleton that holds all registered tool instances in memory, keyed by tool name. It is populated during application boot by `ToolServiceProvider`.

```php
$registry = app(ToolRegistry::class);

// Check if a tool is available
$registry->has('test_tool');          // bool

// Execute a tool by name
$result = $registry->execute('test_tool', $arguments, $toolCallId);

// List all registered tools
$registry->getAll();    // array<string, ToolInterface>
$registry->getMCPTools();  // array<string, MCPToolInterface>
```

Tool availability per model request is determined by the model's `tools` capability flags in the config, **not** by the registry alone. The registry is the execution engine; the model config is the permission layer.

---

### Function-Calling Tools

These are PHP classes that implement `ToolInterface` and run entirely inside the application.

#### Interface

```php
interface ToolInterface
{
    public function getName(): string;          // unique identifier
    public function getDefinition(): ToolDefinition;  // JSON schema for the model
    public function execute(array $arguments, string $toolCallId): ToolResult;
}
```

#### Creating a new function-calling tool

**1. Create the class** in `app/Services/AI/Tools/Implementations/`:

```php
<?php
namespace App\Services\AI\Tools\Implementations;

use App\Services\AI\Tools\AbstractTool;
use App\Services\AI\Tools\Value\ToolDefinition;
use App\Services\AI\Tools\Value\ToolResult;

class MyCustomTool extends AbstractTool
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'my_custom_tool',
            description: 'Describe what this tool does for the model.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type'        => 'string',
                        'description' => 'The input value',
                    ],
                ],
                'required' => ['query'],
            ]
        );
    }

    public function execute(array $arguments, string $toolCallId): ToolResult
    {
        $query  = $arguments['query'] ?? '';
        $result = ['answer' => "Processed: {$query}"];
        return $this->getSuccessResult($result, $toolCallId);
    }
}
```

**2. Register it** in `config/tools.php`:

```php
'available_tools' => [
    \App\Services\AI\Tools\Implementations\MyCustomTool::class,
],
```

**3. Enable it on a model** by adding the tool name to the model's `tools` array in the relevant model list file:

```php
// config/model_lists/openai_models.php
'tools' => [
    'stream'         => true,
    'my_custom_tool' => 'my_custom_tool',  // tool name must match getName()
],
```

---

### MCP Tools

MCP (Model Context Protocol) tools communicate with external HTTP servers that expose a JSON-RPC tool interface. HAWKI orchestrates tool calls by acting as an MCP client.

#### MCP Server record

Each MCP server is stored in the `mcp_servers` table via the `McpServer` Eloquent model:

| Field | Description |
|---|---|
| `url` | HTTP endpoint of the MCP server |
| `server_label` | Short identifier used to prefix tool names (e.g. `hawki-rag`) |
| `description` | Human-readable description |
| `require_approval` | `never` / `always` / `auto` |
| `timeout` | Execution timeout in seconds |
| `discovery_timeout` | Timeout used during initial tool discovery |
| `api_key` | Optional Bearer token for the server |

#### AiTool record

Each tool exposed by an MCP server is stored in the `ai_tools` table via the `AiTool` Eloquent model:

| Field | Description |
|---|---|
| `name` | Prefixed tool name: `{server_label}-{mcp_tool_name}` |
| `description` | Tool description from the MCP server |
| `inputSchema` | JSON Schema defining the tool's parameters |
| `server_id` | Foreign key to `mcp_servers` |
| `type` | Always `mcp` for MCP tools |
| `status` | `active` / `inactive` |

#### Adding an MCP server

Use the interactive command:

```bash
php artisan tools:add-mcp-server https://my-mcp-server.example.com/mcp
```

The command will:
1. Prompt for label, description, approval mode, timeouts, and optional API key.
2. Create a `McpServer` record.
3. Connect to the server and list all available tools.
4. Let you select which tools to register as `AiTool` records.
5. Let you select which active AI models may use those tools (creates `ai_model_tools` pivot rows).

After the command completes, restart the application so that `ToolServiceProvider` loads the new tools from the database.

#### MCP execution flow

When an AI model invokes a registered MCP tool:

```
AiService
  └─ ToolExecutionService::buildFollowUpRequest()
       └─ ToolRegistry::execute('hawki-rag-search', $args, $callId)
            └─ DynamicMCPTool::execute()
                 └─ AbstractMCPTool::execute()
                      └─ DynamicMCPTool::executeMCP()
                           └─ MCPSSEClient::callTool()  →  HTTP JSON-RPC  →  MCP Server
```

The `MCPSSEClient` (`app/Services/AI/Tools/MCP/MCPSSEClient.php`) sends a `tools/call` JSON-RPC request and returns the result.

#### Tool naming convention

To avoid name collisions across MCP servers, all tool names are prefixed with the server label:

```
{server_label}-{mcp_tool_name}
```

Example: a tool named `search` on server `hawki-rag` becomes `hawki-rag-search`.

---

## Model–Tool Assignments

The `ai_model_tools` pivot table links specific AI models to the tools they are permitted to use. This is independent of the model's `tools` capability flags in the config — those flags describe *what kind* of tool calling the model supports at the protocol level, while the pivot records describe *which specific MCP tools* are available to each model.

### Pivot columns

| Column | Description |
|---|---|
| `ai_model_id` | FK to `ai_models.id` |
| `ai_tool_id` | FK to `ai_tools.id` |
| `type` | Tool type (`mcp`, `function`) |
| `source_id` | Optional external reference |

### Managing assignments

```bash
# View current assignments
php artisan tools:assign --list

# Assign a tool to one or more models interactively
php artisan tools:assign

# Assign by name directly
php artisan tools:assign --tool=hawki-rag-search --model=gpt-4.1

# Remove an assignment
php artisan tools:assign --tool=hawki-rag-search --model=gpt-4.1 --detach
```

---

## Database Schema

```
ai_providers
├── id (PK)
├── provider_id  (unique string, e.g. "openAi")
├── name
├── active (bool)
├── api_url
└── ping_url

ai_models
├── id (PK)
├── model_id   (unique string, e.g. "gpt-4.1")
├── label
├── active (bool)
├── input      (json — ["text","image"])
├── output     (json — ["text"])
├── tools      (json — capability flags map)
├── default_params (json — {temp, top_p})
└── provider_id (FK → ai_providers.id)

ai_model_statuses
├── model_id   (PK string, FK-like ref to ai_models.model_id)
└── status     (enum: ONLINE | OFFLINE | UNKNOWN)

mcp_servers
├── id (PK)
├── server_label
├── url
├── description
├── require_approval
├── timeout
├── discovery_timeout
└── api_key

ai_tools
├── id (PK)
├── name       (unique — "{server_label}-{tool_name}")
├── description
├── inputSchema (json — JSON Schema)
├── capability
├── type       ("mcp" | "function")
├── status     ("active" | "inactive")
└── server_id  (FK → mcp_servers.id, nullable)

ai_model_tools  (pivot)
├── id (PK)
├── ai_model_id (FK → ai_models.id)
├── ai_tool_id  (FK → ai_tools.id)
├── type
└── source_id
```

---

## Service Providers

### `AppServiceProvider`

Located at `app/Providers/AppServiceProvider.php`.

- **`register()`** — Binds `AiModelSyncService` as a singleton.
- **`boot()`** — When running in a CLI context (`runningInConsole()`), checks if `ai_models` is empty and automatically runs a sync. This makes the initial install seamless: after `php artisan migrate`, the model registry is populated without any manual step.

### `ToolServiceProvider`

Located at `app/Providers/ToolServiceProvider.php`.

Loads tools into the `ToolRegistry` in three steps on every boot:

1. **Class-based tools** — iterates `config/tools.available_tools` and instantiates each class.
2. **DB MCP tools** — queries `ai_tools` where `status = active` and `type = mcp`, eager-loads the related `mcp_servers` row, and creates a `DynamicMCPTool` instance for each.
3. **Legacy config MCP discovery** — runs `McpToolDiscoveryHandler` against `config/tools.mcp_servers` as a fallback for servers that have not yet been migrated to the database.

If the database is unavailable (e.g. during the initial `migrate` run), step 2 is silently skipped.

---

## Artisan Commands Reference

### Model commands

| Command | Description |
|---|---|
| `models:sync` | Sync providers and models from config into the database |
| `models:sync --force` | Re-sync even when records already exist |
| `models:list` | List all models stored in the database |
| `models:list --provider=gwdg` | Filter by provider |
| `models:list --active` | Show only active models |
| `models:list --json` | Output as JSON |
| `check:model-status` | Check and update live status of all models (runs every 15 min) |

### MCP server commands

| Command | Description |
|---|---|
| `tools:add-mcp-server {url}` | Add an MCP server, discover its tools, and assign to models |
| `tools:list-mcp-servers` | List all registered MCP servers |
| `tools:remove-mcp-server` | Remove an MCP server and its tools |
| `tools:remove-mcp-server {id} --force` | Remove without confirmation prompt |

### Tool management commands

| Command | Description |
|---|---|
| `tools:list` | List all tools (class-based + DB MCP) with model assignments |
| `tools:list --json` | Output as JSON |
| `tools:assign` | Interactively assign/detach tools to models |
| `tools:assign --list` | Show all current tool–model assignments |
| `tools:assign --tool={name} --model={model_id}` | Assign directly by name |
| `tools:assign --detach --tool={name} --model={model_id}` | Remove an assignment |
| `tools:discover` | (Legacy) Discover tools from `config/tools.mcp_servers` |

---

## How-To Guides

### Add a new AI provider

1. Create `config/model_lists/myprovider_models.php` following the structure of the existing list files.
2. Add the provider entry to `config/model_providers.php`:
   ```php
   'myprovider' => [
       'active'   => env('MYPROVIDER_ACTIVE', false),
       'api_key'  => env('MYPROVIDER_API_KEY'),
       'api_url'  => env('MYPROVIDER_API_URL'),
       'ping_url' => env('MYPROVIDER_PING_URL'),
       'models'   => require __DIR__ . '/model_lists/myprovider_models.php',
   ],
   ```
3. Implement the provider adapter in `app/Services/AI/Providers/Myprovider/` (see the [Model Connection](6-Model%20Connection.md) document).
4. Run `php artisan models:sync --force` to persist the new provider and its models.

### Activate or deactivate a model without touching source code

Set the environment variable and re-sync:

```bash
# In .env
MODELS_OPENAI_GPT5_ACTIVE=false

# Then
php artisan config:clear
php artisan models:sync --force
```

### Register an MCP server and its tools

```bash
php artisan tools:add-mcp-server https://my-server.example.com/mcp \
    --label=my-server \
    --require_approval=never \
    --timeout=30
```

Follow the interactive prompts to select which tools to register and which models may use them.

### Deactivate an MCP tool without deleting it

Directly update the record in the database:

```sql
UPDATE ai_tools SET status = 'inactive' WHERE name = 'my-server-my-tool';
```

Or remove the assignment without deleting the tool:

```bash
php artisan tools:assign --detach --tool=my-server-my-tool --model=gpt-4.1
```

### Verify which tools a model will receive in a request

The provider-side request converters (`ToolAwareConverter` trait) build the tools list by:
1. Reading the `tools` capability map from the model's config.
2. Looking up each named tool in the `ToolRegistry`.
3. Passing the `ToolDefinition` (name, description, JSON schema) to the provider API.

Only tools that are both listed in the model config **and** present in the `ToolRegistry` are sent to the API.

---

For further details on individual components see:

- [Model Connection](6-Model%20Connection.md) — provider adapter implementation
- [Model Configuration Variables](10.1-Model%20Configuration%20Variables.md) — environment variable reference
- [dot Env](10-dot%20Env.md) — complete `.env` reference
