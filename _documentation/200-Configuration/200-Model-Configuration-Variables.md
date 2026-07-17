# Model Configuration Variables

This document describes the environment variables available for fine-grained control over AI model availability and capabilities in HAWKI. These variables let administrators customize model behavior without modifying source code.

## Table of Contents

1. [Overview](#overview)
2. [Configuration Architecture](#configuration-architecture)
3. [Applying Configuration Changes](#applying-configuration-changes)
4. [Variable Naming Convention](#variable-naming-convention)
5. [Model Activation Variables](#model-activation-variables)
6. [Tool Capability Variables](#tool-capability-variables)
7. [Provider-Level Settings](#provider-level-settings)
8. [Default and System Model Selection](#default-and-system-model-selection)
9. [Best Practices](#best-practices)
10. [Examples](#examples)

---

## Overview

HAWKI's model configuration uses three coordinated layers:

1. **`.env` file** — where you set values
2. **Config files** (`config/model_providers.php`, `config/model_lists/*.php`) — read the env values via `env()`
3. **Database** (`ai_models`, `ai_providers`) — the **runtime source of truth**, populated from config via sync

> **Important:** Changing an environment variable is not enough on its own. After editing `.env`, you must clear the config cache and re-sync the database for the change to take effect at runtime. See [Applying Configuration Changes](#applying-configuration-changes).

### Why Use Environment Variables?

- **No source code changes:** Enable or disable models and capabilities from `.env` only
- **Source control safety:** Site-specific settings stay out of version-controlled files
- **Per-environment control:** Use different `.env` files for development, staging, and production
- **Update-safe:** Config overrides survive HAWKI upgrades without merge conflicts

### Default Behavior

These variables are **not** required in `.env`. When absent, the defaults defined in the model list files are used. Only add a variable when you need to override the default.

> **v2.5 scope note:** The set of config-driven capability flags is intentionally limited in v2.5 to prepare for the admin backend coming in v2.6. Fine-grained per-model capability management (vision, web search, knowledge base) will move to the UI in v2.6.

---

## Configuration Architecture

### Layer 1 — Environment Variables (`.env`)

Your `.env` file is where all site-specific values live. Model configuration variables go here:

```bash
MODELS_OPENAI_GPT5_ACTIVE=false
MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD=true
DEFAULT_MODEL=gpt-4.1-nano
```

### Layer 2 — Config Files (deployment-time)

The config files consume env values via `env()` and define the complete model catalog:

- `config/model_providers.php` — providers, API endpoints, default/system model selection
- `config/model_lists/*.php` — individual model definitions and capability flags

These files are read by the sync command to populate the database. They are **not** read at runtime by the application itself.

### Layer 3 — Database (runtime)

After running `php hawki models sync`, the database holds a persistent copy of every provider and model. At runtime, HAWKI reads exclusively from the database — config files are never consulted.

```
.env  →  config files  →  (ai:config:sync)  →  database  →  application runtime
```

**Key implications:**

- The `active` flag in config (controlled by env vars) **always overwrites** the database value on each sync.
- A model can be deactivated in the database without touching config, but a subsequent `--force` sync will restore the config value.
- Provider API keys are never stored in the database. They are always read from `.env` at request time.

---

## Applying Configuration Changes

After any change to model-related environment variables, run:

```bash
php hawki clear-cache
php hawki models sync --force
```

The `clear-cache` step ensures Laravel picks up your new `.env` values. The `--force` flag re-syncs all providers and models regardless of whether records already exist.

To verify the result:

```bash
php hawki models list
php hawki models list --active
```

---

## Variable Naming Convention

### Model Activation Variables

```
MODELS_{PROVIDER}_{MODEL_ID}_ACTIVE
```

**Components:**

- `MODELS_` — fixed prefix
- `{PROVIDER}` — provider name in uppercase (e.g. `OPENAI`, `GWDG`, `GOOGLE`)
- `{MODEL_ID}` — model ID with special characters replaced by underscores, uppercased
- `_ACTIVE` — suffix

**Model ID transformation rules:**

1. Convert to uppercase
2. Replace hyphens (`-`) with underscores (`_`)
3. Replace dots (`.`) with underscores (`_`)
4. Remove any other special characters

**Examples:**

| Original Model ID             | Environment Variable                             |
|-------------------------------|--------------------------------------------------|
| `gpt-5`                       | `MODELS_OPENAI_GPT5_ACTIVE`                      |
| `gpt-4.1-nano`                | `MODELS_OPENAI_GPT4_1_NANO_ACTIVE`               |
| `gemini-2.5-pro`              | `MODELS_GOOGLE_GEMINI_2_5_PRO_ACTIVE`            |
| `meta-llama-3.1-8b-instruct`  | `MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_ACTIVE`  |
| `qwen3-omni-30b-a3b-instruct` | `MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_ACTIVE` |

### Tool Capability Variables

```
MODELS_{PROVIDER}_{MODEL_ID}_TOOLS_{CAPABILITY}
```

**Supported capability keys in v2.5:**

| Capability          | Variable suffix              | Providers      |
|---------------------|------------------------------|----------------|
| File upload         | `_TOOLS_FILE_UPLOAD`         | All            |
| Native capabilities | `_TOOLS_NATIVE_CAPABILITIES` | OpenAI, Google |

> Capabilities like `vision`, `web_search`, and `knowledge_base` are no longer config-driven in v2.5 — they are resolved from live provider metadata or via the tool assignment system. They will be fully manageable in the admin backend in v2.6.

**Examples:**

| Model ID                      | Capability          | Variable                                                    |
|-------------------------------|---------------------|-------------------------------------------------------------|
| `gpt-5`                       | file_upload         | `MODELS_OPENAI_GPT5_TOOLS_FILE_UPLOAD`                      |
| `gpt-5`                       | native_capabilities | `MODELS_OPENAI_GPT5_TOOLS_NATIVE_CAPABILITIES`              |
| `gemini-2.5-pro`              | file_upload         | `MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_FILE_UPLOAD`            |
| `qwen3-omni-30b-a3b-instruct` | file_upload         | `MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD` |

### Parameter Variables

```
MODELS_{PROVIDER}_{MODEL_ID}_PARAMS_{PARAM}
```

**Supported parameter keys:**

| Parameter   | Variable suffix | Description                          |
|-------------|-----------------|--------------------------------------|
| Temperature | `_PARAMS_TEMP`  | Sampling randomness (0.0–2.0)        |
| Top-p       | `_PARAMS_TOP_P` | Nucleus sampling threshold (0.0–1.0) |

---

## Model Activation Variables

These variables control whether a model appears in the model selection interface.

```bash
MODELS_{PROVIDER}_{MODEL_ID}_ACTIVE=true|false
```

Model activation defaults are defined in the provider's model list file. The list files are located at:

| Provider  | File                                      |
|-----------|-------------------------------------------|
| OpenAI    | `config/model_lists/openai_models.php`    |
| GWDG      | `config/model_lists/gwdg_models.php`      |
| Google    | `config/model_lists/google_models.php`    |
| Ollama    | `config/model_lists/ollama_models.php`    |
| OpenWebUI | `config/model_lists/openwebui_models.php` |

**Common use cases:**

```bash
# Disable expensive models in development
MODELS_OPENAI_GPT5_ACTIVE=false
MODELS_GOOGLE_GEMINI_2_5_PRO_ACTIVE=false

# Enable specific models for testing
MODELS_OPENAI_GPT4_1_NANO_ACTIVE=true
MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_ACTIVE=true
```

After changing these values, run:

```bash
php hawki clear-cache && php hawki models sync --force
```

---

## Tool Capability Variables

These variables control the two configurable per-model capability flags in v2.5.

```bash
MODELS_{PROVIDER}_{MODEL_ID}_TOOLS_{CAPABILITY}=true|false
```

### FILE_UPLOAD

Enables document and image file uploads for a model.

```bash
MODELS_OPENAI_GPT5_TOOLS_FILE_UPLOAD=false
MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_TOOLS_FILE_UPLOAD=true
MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_FILE_UPLOAD=false
```

> **Default policy:** External cloud API endpoints (OpenAI, Google) have file upload disabled by default. This protects data sovereignty — uploaded content must not leave academic infrastructure or be reused for model training. Enable external providers explicitly and at your own risk.

File upload requires a working converter:

```bash
FILE_CONVERTER=hawki_converter
HAWKI_FILE_CONVERTER_API_URL=127.0.0.1/extract
HAWKI_FILE_CONVERTER_API_KEY=your-key

# Or use GWDG Docling:
# FILE_CONVERTER=gwdg_docling
```

### NATIVE_CAPABILITIES

Enables the vendor's built-in tool suite for models that support it (currently OpenAI and Google). When enabled, the provider automatically selects which native tools (web search, code execution, etc.) to apply based on the model and request context.

```bash
# OpenAI — enable native tool suite (web search, code execution via Responses API)
MODELS_OPENAI_GPT5_TOOLS_NATIVE_CAPABILITIES=true
MODELS_OPENAI_GPT4_1_TOOLS_NATIVE_CAPABILITIES=true

# Google — enable native tool suite (grounding, code execution)
MODELS_GOOGLE_GEMINI_3_5_FLASH_TOOLS_NATIVE_CAPABILITIES=true
MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_NATIVE_CAPABILITIES=true
```

> Setting `NATIVE_CAPABILITIES=false` forces the model into HAWKI-registered-tool-only mode, disabling all vendor-provided native tools even if the model supports them.

### How capability flags appear in config

For OpenAI / Google models (native capabilities pattern):

```php
// config/model_lists/openai_models.php
'tools' => [
    'stream'              => true,
    'tool_calling'        => true,
    'file_upload'         => env('MODELS_OPENAI_GPT5_TOOLS_FILE_UPLOAD', false),
    'native_capabilities' => env('MODELS_OPENAI_GPT5_TOOLS_NATIVE_CAPABILITIES', true),
],
```

For GWDG models (explicit flags pattern):

```php
// config/model_lists/gwdg_models.php
'tools' => [
    'stream'       => true,
    'tool_calling' => true,
    'file_upload'  => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
],
```

---

## Provider-Level Settings

Enable or disable entire providers and configure their API endpoints.

```bash
# Enable/disable providers
OPENAI_ACTIVE=true
GWDG_ACTIVE=true
GOOGLE_ACTIVE=true
OLLAMA_ACTIVE=false
OPEN_WEB_UI_ACTIVE=false

# Provider API keys
OPENAI_API_KEY=your-key
GWDG_API_KEY=your-key
GOOGLE_API_KEY=your-key

# Custom API endpoints (optional — defaults to provider-standard if unset)
OPENAI_URL=https://api.openai.com/v1
GWDG_API_URL=https://your-gwdg-instance.de/v1
OLLAMA_API_URL=http://localhost:11434
OPEN_WEB_UI_API_URL=http://your-openwebui-instance
OPEN_WEB_UI_API_KEY=your-key
```

Disabling a provider sets `active=false` for the provider record in the database after the next sync. All its models will also be treated as inactive at runtime.

---

## Default and System Model Selection

Set which models are used by default for each task type:

```bash
# User-facing default models
DEFAULT_MODEL=gpt-4.1-nano
DEFAULT_WEBSEARCH_MODEL=gemini-3.5-flash
DEFAULT_FILEUPLOAD_MODEL=qwen3-omni-30b-a3b-instruct
DEFAULT_VISION_MODEL=qwen3-omni-30b-a3b-instruct

# Internal system tasks
TITLE_GENERATOR_MODEL=gpt-4.1-nano
PROMPT_IMPROVEMENT_MODEL=gpt-4.1-nano
SUMMARIZER_MODEL=gpt-4.1-nano
```

For deployments that use external app access you can set separate defaults that apply only to the ext-app context (falls back to the user-facing defaults when not set):

```bash
# Optional — override defaults for external app requests only
DEFAULT_EXT_APP_MODEL=gpt-4.1-nano
DEFAULT_EXT_APP_WEBSEARCH_MODEL=
DEFAULT_EXT_APP_FILEUPLOAD_MODEL=
DEFAULT_EXT_APP_VISION_MODEL=
```

The model ID used here must match the `id` field of an active model in your config. These values are read directly from `.env` at runtime (not synced to the database), so they take effect immediately after `php hawki clear-cache`.

---

## Best Practices

### 1. Only override what you need

The defaults in model list files work well for most deployments. Add env variables only when you need to change a default.

### 2. Document your overrides

```bash
# Disable GPT-5 to control API costs in development
MODELS_OPENAI_GPT5_ACTIVE=false

# Use self-hosted vision model
DEFAULT_VISION_MODEL=qwen3-omni-30b-a3b-instruct
```

### 3. Always sync after changes

```bash
php hawki clear-cache
php hawki models sync --force
php hawki models list --active    # verify result
```

### 4. Use environment-specific `.env` files

**Development** — minimize cost:

```bash
DEFAULT_MODEL=gpt-4.1-nano
MODELS_OPENAI_GPT5_ACTIVE=false
MODELS_GOOGLE_GEMINI_2_5_PRO_ACTIVE=false
```

**Production** — enable all appropriate models:

```bash
MODELS_OPENAI_GPT5_ACTIVE=true
MODELS_OPENAI_GPT4_1_ACTIVE=true
MODELS_GOOGLE_GEMINI_2_5_PRO_ACTIVE=true
```

### 5. Data sovereignty defaults

External provider file upload is disabled by default for data protection reasons. Only enable it after reviewing the provider's data usage policies:

```bash
# Only enable if you have confirmed the provider does not retain uploaded data
MODELS_OPENAI_GPT5_TOOLS_FILE_UPLOAD=true
```

Use self-hosted (GWDG, Ollama) models for sensitive file processing where possible.

### 6. Security — disable external models when needed

```bash
# Use only self-hosted / academic infrastructure
OPENAI_ACTIVE=false
GOOGLE_ACTIVE=false

GWDG_ACTIVE=true
GWDG_API_KEY=your-academic-key
OLLAMA_ACTIVE=true
OLLAMA_API_URL=http://localhost:11434
```

---

## Examples

### Example 1: Cost-Optimized Development

```bash
# Use cheap models as defaults
DEFAULT_MODEL=gpt-4.1-nano
DEFAULT_WEBSEARCH_MODEL=gemini-3.5-flash
DEFAULT_FILEUPLOAD_MODEL=meta-llama-3.1-8b-instruct
DEFAULT_VISION_MODEL=qwen3-omni-30b-a3b-instruct

# Disable expensive models
MODELS_OPENAI_GPT5_ACTIVE=false
MODELS_OPENAI_GPT4_1_ACTIVE=false
MODELS_GOOGLE_GEMINI_2_5_PRO_ACTIVE=false

# Disable file upload for external APIs
MODELS_OPENAI_GPT4_1_NANO_TOOLS_FILE_UPLOAD=false
MODELS_GOOGLE_GEMINI_3_5_FLASH_TOOLS_FILE_UPLOAD=false

# Enable for self-hosted model
MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_TOOLS_FILE_UPLOAD=true
```

### Example 2: Privacy-First Deployment

```bash
# Disable commercial cloud providers
OPENAI_ACTIVE=false
GOOGLE_ACTIVE=false

# Academic cloud
GWDG_ACTIVE=true
GWDG_API_KEY=your-key

# Self-hosted
OLLAMA_ACTIVE=true
OLLAMA_API_URL=http://localhost:11434

DEFAULT_MODEL=meta-llama-3.1-8b-instruct
DEFAULT_VISION_MODEL=qwen3-omni-30b-a3b-instruct
DEFAULT_FILEUPLOAD_MODEL=meta-llama-3.1-8b-instruct
```

### Example 3: Feature-Rich Production

```bash
OPENAI_ACTIVE=true
GWDG_ACTIVE=true
GOOGLE_ACTIVE=true

MODELS_OPENAI_GPT5_ACTIVE=true
MODELS_OPENAI_GPT4_1_ACTIVE=true
MODELS_GOOGLE_GEMINI_2_5_PRO_ACTIVE=true

MODELS_OPENAI_GPT5_TOOLS_FILE_UPLOAD=true
MODELS_OPENAI_GPT5_TOOLS_NATIVE_CAPABILITIES=true
MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_FILE_UPLOAD=true
MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_NATIVE_CAPABILITIES=true

MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD=true
MODELS_GWDG_INTERNVL3_5_30B_A3B_TOOLS_FILE_UPLOAD=true
```

### Example 4: Research & Document Analysis

```bash
# Enable reasoning models
MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_ACTIVE=true
MODELS_GWDG_QWEN35_122B_A10B_ACTIVE=true

# Enable file processing for relevant models
MODELS_GWDG_DEEPSEEK_R1_DISTILL_LLAMA_70B_TOOLS_FILE_UPLOAD=true
MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_TOOLS_FILE_UPLOAD=true
MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_TOOLS_FILE_UPLOAD=true
MODELS_OPENAI_GPT4_1_TOOLS_FILE_UPLOAD=true

DEFAULT_MODEL=qwen3.5-122b-a10b
DEFAULT_FILEUPLOAD_MODEL=mistral-large-3-675b-instruct-2512
DEFAULT_WEBSEARCH_MODEL=gemini-2.5-pro
```

### Example 5: Task-Specific Multi-Model Setup

```bash
# Fast, cheap model for general chat
DEFAULT_MODEL=gpt-4.1-nano

# Best model for web search (native capabilities enabled)
DEFAULT_WEBSEARCH_MODEL=gemini-2.5-pro
MODELS_GOOGLE_GEMINI_2_5_PRO_TOOLS_NATIVE_CAPABILITIES=true

# Cost-effective model for documents
DEFAULT_FILEUPLOAD_MODEL=meta-llama-3.1-8b-instruct
MODELS_GWDG_META_LLAMA_3_1_8B_INSTRUCT_TOOLS_FILE_UPLOAD=true

# Vision model
DEFAULT_VISION_MODEL=qwen3-omni-30b-a3b-instruct
MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD=true
```

---

## Related Documentation

- [AI Models & Tools](300-AI-Models-and-Tools.md) — complete architecture guide for models and tools, including MCP servers and function-calling tools
- [dot Env Configuration](100-dot-Env.md) — complete `.env` reference
- [HAWKI CLI](700-HAWKI-CLI.md) — CLI command reference
- [Backend → AI Service Layer](../500-Backend/500-AI-Service-Layer/index.md) — provider adapter and model enrichment implementation
