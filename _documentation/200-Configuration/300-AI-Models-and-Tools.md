> **Partially outdated.** The conceptual architecture (config → sync → DB), deployment steps, command reference, and MCP tool sections are still accurate. The following sections reference class names from the pre-v2.5 codebase that **no longer exist**: "Database Registry" (old Eloquent class names), "Value Object vs Eloquent Model", "Model Sync" (old service class), the function-calling tool creation example, and "Service Providers". For current implementation details see [Backend → AI Service Layer](../500-Backend/500-AI-Service-Layer/index.md) and [Backend → Provider Adapters](../500-Backend/500-AI-Service-Layer/100-Provider-Adapters.md).

# AI Models & Tools

This document describes HAWKI's AI model registry and tool system: how models and providers are configured and persisted, how tools are registered and executed, and how tools are connected to specific models.

## Table of Contents

1. [Overview](#overview)
2. [Deployment Quick Start](#deployment-quick-start)
3. [AI Model System](#ai-model-system)
    - [Configuration Layer](#configuration-layer)
    - [Database Registry](#database-registry)
    - [Model Sync](#model-sync)
    - [Model Online Status](#model-online-status)
    - [Value Object vs Eloquent Model](#value-object-vs-eloquent-model)
4. [Tool System](#tool-system)
    - [Architecture: DB-First](#architecture-db-first)
    - [Tool Types](#tool-types)
    - [Tool Registry](#tool-registry)
    - [Function-Calling Tools](#function-calling-tools)
    - [MCP Tools](#mcp-tools)
    - [Tool Status vs Active State](#tool-status-vs-active-state)
5. [Model–Tool Assignments](#modeltool-assignments)
6. [Database Schema](#database-schema)
7. [Service Providers](#service-providers)
8. [Command Reference](#command-reference)
9. [How-To Guides](#how-to-guides)

---

## Overview

HAWKI's AI model and tool system is split into two complementary layers:

| Layer        | Source of truth                                                                | Purpose                                                                                                               |
|--------------|--------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------|
| **Config**   | `config/model_providers.php` + `config/model_lists/*.php` + `config/tools.php` | Defines available models, providers, and tool classes. Used **only at deployment time** to populate the database.     |
| **Database** | `ai_providers`, `ai_models`, `ai_tools`, `mcp_servers`, `ai_model_tools`       | Single source of truth **at runtime**. Persists model registry, online status, all tools, and tool–model assignments. |

Config files are **never read at runtime** for tools or models. After the initial sync, the database contains everything the application needs.

---

## Deployment Quick Start

Follow these steps in order when setting up HAWKI for the first time, or when adding new providers, models, or tools.

### Step 1 — Initial database setup

Run migrations. The first-run hook in `AppServiceProvider` will automatically sync models and function tools as soon as the tables are created:

```bash
php hawki migrate
```

At this point the `ai_models` and `ai_tools` tables are populated automatically from your config files.

### Step 2 — Verify model sync

```bash
php hawki models list
```

All providers and models from your config files should be visible. If the list is empty or incomplete, run the sync explicitly:

```bash
php hawki models sync
```

### Step 3 — Configure providers and default models

In your `.env` file, enable the providers you need and set their API keys:

```bash
# Enable providers
OPENAI_ACTIVE=true
OPENAI_API_KEY=your-key

GWDG_ACTIVE=true
GWDG_API_KEY=your-key

GOOGLE_ACTIVE=true
GOOGLE_API_KEY=your-key

# Set defaults
DEFAULT_MODEL=gpt-4.1-nano
DEFAULT_WEBSEARCH_MODEL=gemini-2.0-flash
DEFAULT_FILEUPLOAD_MODEL=qwen3-omni-30b-a3b-instruct
DEFAULT_VISION_MODEL=qwen3-omni-30b-a3b-instruct
```

After editing `.env`, clear the config cache and re-sync so the database reflects your changes:

```bash
php hawki clear-cache
php hawki models sync --force
```

### Step 4 — Sync function tools (if not done automatically)

If function tools were not auto-synced (e.g. the table was already populated from a previous install), sync them manually:

```bash
php hawki tools sync --function-only
```

Verify:

```bash
php hawki tools list
```

### Step 5 — Add MCP servers (optional)

If you use external MCP servers, add them interactively:

```bash
php hawki tools add-mcp-server https://your-mcp-server.example.com/mcp
```

Or for CI/CD pipelines, add the server to `config/tools.php` and sync:

```bash
php hawki tools sync --mcp-only
```

### Step 6 — Assign tools to models

```bash
php hawki tools assign
```

Follow the interactive prompts, or use direct flags:

```bash
php hawki tools assign --tool=my-tool --model=gpt-4.1
```

### Step 7 — Start the application

```bash
# Development
php hawki run -dev

# Production build
php hawki run -build
```

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

> Provider API keys are **never** stored in the database; they always come from environment variables.

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
            'stream'         => true,
            'file_upload'    => true,
            'vision'         => true,
            'tool_calling'   => true,       // set false to exclude from tool assignments
            'web_search'     => 'native',
            'knowledge_base' => 'native',
        ],
        'default_params' => [
            'temp'  => env('MODELS_OPENAI_GPT4_1_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_OPENAI_GPT4_1_PARAMS_TOP_P', 1.0),
        ],
    ],
    // ...
];
```

**Model capability flags** (`tools` array):

| Key                | Values                       | Meaning                                                                                                                                       |
|--------------------|------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| `stream`           | `true` / `false`             | Whether the model supports streaming                                                                                                          |
| `file_upload`      | `true` / `false`             | Whether the model can receive uploaded files                                                                                                  |
| `vision`           | `true` / `false`             | Whether the model can process images                                                                                                          |
| `tool_calling`     | `true` / `false`             | Whether the model supports function/tool calling. Omitting defaults to `true`. Set to `false` to exclude the model from all tool assignments. |
| `web_search`       | `'native'` / `'unsupported'` | Whether the provider handles web search natively                                                                                              |
| `knowledge_base`   | `'native'` / `string`        | Strategy for knowledge-base retrieval                                                                                                         |
| `<capability_key>` | `string`                     | Name of a registered tool capability the model may invoke                                                                                     |

---

### Database Registry

The database mirrors the config-defined providers and models so that the system has a persistent, queryable record of every known model. This enables:

- Model online-status tracking
- Tool–model relationship management
- Admin tooling (listing, deactivating models without changing config files)

The relevant Eloquent models are (current class names as of v2.5):

| Class                      | Table          | Role                                     |
|----------------------------|----------------|------------------------------------------|
| `App\Models\Ai\AiProvider` | `ai_providers` | One row per provider (e.g. openAi, gwdg) |
| `App\Models\Ai\AiModel`    | `ai_models`    | One row per model definition             |
| `App\Models\Ai\AiTool`     | `ai_tools`     | Registered function and MCP tools        |
| `App\Models\Ai\McpServer`  | `mcp_servers`  | MCP server registrations                 |

---

### Model Sync

> **Note:** The `AiModelSyncService` class referenced in older versions of this document no longer exists. Sync is now triggered via artisan commands (see Command Reference below).

Sync rules:

- Providers and models are **created or updated** (`updateOrCreate`).
- Existing database records are **never deleted** — operators may deactivate a model in the DB independently of config.
- The `active` flag is **always overridden from config** so that environment-variable changes take effect on the next sync.

After changing `.env` model settings, always clear the config cache and re-sync:

```bash
php hawki clear-cache
php hawki models sync --force
```

---

### Model Online Status

Each model's availability is tracked in `ai_model_statuses` with three possible states:

| Status    | Meaning                                       |
|-----------|-----------------------------------------------|
| `ONLINE`  | Provider API responded successfully           |
| `OFFLINE` | Provider API unreachable or returned an error |
| `UNKNOWN` | Status has never been checked                 |

The scheduled command `check:model-status` runs every 15 minutes. You can also trigger it manually:

```bash
php hawki models check-status
# artisan equivalent: php artisan check:model-status
```

---

---

## Tool System

> **This section has been superseded.** The tool system architecture, ToolInterface, function-calling tools, MCP integration, and deployment how-tos are now documented in [Backend → AI Tools & MCP](../500-Backend/500-AI-Service-Layer/300-Tools-and-MCP.md).


---

## Model–Tool Assignments

The `ai_model_tools` pivot table links specific AI models to the tools they are permitted to use. Only models with `tool_calling` set to `true` (or unset, which defaults to `true`) in their config are eligible for tool assignments.

### Pivot columns

| Column        | Description                   |
|---------------|-------------------------------|
| `ai_model_id` | FK to `ai_models.id`          |
| `ai_tool_id`  | FK to `ai_tools.id`           |
| `type`        | Tool type (`mcp`, `function`) |
| `source_id`   | Optional external reference   |

### Managing assignments

```bash
php hawki tools assign --list                                             # view all assignments
php hawki tools assign                                                    # interactive
php hawki tools assign --tool=hawki-rag-search --model=gpt-4.1           # direct assignment
php hawki tools assign --tool=hawki-rag-search --provider=openAi         # assign to all OpenAI models
php hawki tools assign --tool=hawki-rag-search --model=gpt-4.1 --detach  # remove assignment
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
├── model_id    (unique string, e.g. "gpt-4.1")
├── label
├── active      (bool)
├── input       (json — ["text","image"])
├── output      (json — ["text"])
├── tools       (json — capability flags map)
├── default_params (json — {temp, top_p})
└── provider_id (FK → ai_providers.id)

ai_model_statuses
├── model_id    (PK string, references ai_models.model_id)
└── status      (enum: ONLINE | OFFLINE | UNKNOWN)

mcp_servers
├── id (PK)
├── url               (unique)
├── server_label
├── version
├── protocolVersion
├── description
├── require_approval  ('never' | 'always' | 'auto')
├── timeout
├── discovery_timeout
└── api_key           (TEXT — stored encrypted via Laravel Crypt)

ai_tools
├── id (PK)
├── name        (unique — "{server_label}-{tool_name}" for MCP; tool name for function)
├── class_name  (nullable — FQCN for function tools, null for MCP tools)
├── description
├── inputSchema (json — JSON Schema for parameters)
├── outputSchema (json — optional)
├── capability  (string — capability key referenced in model configs)
├── type        ('mcp' | 'function')
├── status      ('active' | 'inactive' — system-managed, set by ai:tools:check-status)
├── active      (bool — user-managed toggle, set by ai:tools:configure)
└── server_id   (FK → mcp_servers.id, nullable — null for function tools)

ai_model_tools  (pivot)
├── id (PK)
├── ai_model_id (FK → ai_models.id)
├── ai_tool_id  (FK → ai_tools.id)
├── type
└── source_id
```

---

---

## How-To Guides

### Add a new AI provider

> The implementation details for adding a new provider have changed significantly in v2.5. See [Backend → Provider Adapters](../500-Backend/500-AI-Service-Layer/100-Provider-Adapters.md) for the current `ProviderAdapterInterface` approach.

1. Create `config/model_lists/myprovider_models.php` following the structure of the existing list files.
2. Add the provider entry to `config/model_providers.php`.
3. Implement a class conforming to `ProviderAdapterInterface` and register it via `ProviderAdapterRegistry::declare()`.
4. Run `php hawki models sync --force`.

### Activate or deactivate a model without touching source code

```bash
# In .env
MODELS_OPENAI_GPT5_ACTIVE=false

# Clear cache and re-sync
php hawki clear-cache
php hawki models sync --force
```

> Tool management how-tos (adding function tools, MCP servers, disabling/re-enabling tools, rotating APP_KEY) are now documented in [Backend → AI Tools & MCP](../500-Backend/500-AI-Service-Layer/300-Tools-and-MCP.md).

---

For further details on individual components see:

- [Backend → AI Service Layer](../500-Backend/500-AI-Service-Layer/index.md) — current provider adapter implementation
- [Backend → Provider Adapters](../500-Backend/500-AI-Service-Layer/100-Provider-Adapters.md) — `ProviderAdapterInterface` and `ProviderAdapterRegistry`
- [Model Configuration Variables](200-Model-Configuration-Variables.md) — environment variable reference for fine-grained model control
- [dot Env](100-Dot-Env.md) — complete `.env` reference
- [HAWKI CLI](700-HAWKI-CLI.md) — full CLI command reference
