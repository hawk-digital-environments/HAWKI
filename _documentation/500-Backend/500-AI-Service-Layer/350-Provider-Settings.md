---
sidebar_position: 6
---

# Provider Settings

This article covers provider- and model-level inference settings that shape every text-generation run. For now it documents a single setting — max tokens — and can grow into the home for other inference parameters later.

## Max Tokens

> **Unless a value is explicitly set, HAWKI sends `max_tokens: 4096` for sampling-capable models and no cap at all for the rest — never the model's actual maximum.**

### The resolution chain

Max tokens is resolved through three tiers, from coarse to specific. The first tier that provides a value wins; `AiModelParameters` (`App\Services\Ai\Models\Parameters\Values\AiModelParameters`) implements the merge semantics:

1. **Provider config** — `model_providers.providers.*.config.model_parameters` (see `WellKnownProviderSettings::MODEL_PARAMETERS`), synced onto the `AiProvider` record.
2. **Model defaults** — the `ai_models.parameters` JSON column. The config-file sync (`ai:config:sync`) currently only ever writes `temp` / `top_p` here, so in practice no `max_tokens` arrives from configuration.
3. **Per-request** — the chat request payload's `params.max_tokens`, set by whichever frontend surface initiated the run.

When **no** tier supplies a value, `AiModelParameters::getMaxTokens()` falls back to a hard-coded **4096**.

De-facto, the top two tiers are dormant for max tokens: the provider-level `model_parameters` is stored on the provider record but has no consumer in the request path, and the model-level sync only maps `temp` / `top_p`. Unless a client sends `params.max_tokens`, the fallback always applies.

### Configuration examples

Minimal excerpts of how each tier is configured. Remember that today only the per-request tier reliably reaches the provider (see above).

**Provider level** — `config/model_providers.php`:

```php
'gwdg' => [
    'active'    => env('GWDG_ACTIVE', true),
    'api_key'   => env('GWDG_API_KEY'),
    'api_url'   => env('GWDG_API_URL'),
    'config'    => [
        // Well-known keys: temperature, top_p, max_tokens, max_thinking_tokens
        'model_parameters' => ['max_tokens' => 8192],
    ],
    'models'    => require __DIR__ . '/model_lists/gwdg_models.php',
],
```

**Model level** — an entry in the `models` list of `config/model_lists/gwdg_models.php`:

```php
return [
    // …
    [
        'active' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_ACTIVE', true),
        'id'     => 'qwen3-30b-a3b-instruct-2507',
        'label'  => 'Qwen 3 30B A3B Instruct 2507',
        // Only temp / top_p are mapped onto the model's stored defaults;
        // a max_tokens key here is currently ignored by the sync.
        'default_params' => [
            'temp'  => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_30B_A3B_INSTRUCT_2507_PARAMS_TOP_P', 0.95),
        ],
    ],
    // …
];
```

**Per request** — the chat request payload (what the frontend sends):

```json
{
    "model": "qwen3-30b-a3b-instruct-2507",
    "params": { "temp": 0.7, "top_p": 0.9, "max_tokens": 2048 },
    "messages": [ /* … */ ]
}
```

### The sampling gate

Whether the resolved value is sent at all depends on the model's flags. `AbstractTextGeneratingAgent::maxTokens()` returns:

- the resolved limit (i.e. the explicit value or the 4096 fallback) when the model declares the `feature-sampling-parameters` flag, or
- `null` when it does not — the request then carries **no cap**, and the provider applies its own default (on OpenAI-compatible endpoints typically "generate until the context limit").

The flag comes from model enrichment. On GWDG, roughly half the lineup carries it (Apertus, DeepSeek, GLM, Qwen 3 / 3.5 / 3.6 / 3.8 …) while the other half (Devstral, Gemma, Llama, Mistral Medium, gpt-oss, Qwen 3 Coder Next, Qwen 3 Omni) does not — those models are never capped by the 4096 fallback.

### Behaviour per surface

- **Core chat composer** — never sends `max_tokens`; sampling-capable models run capped at the 4096 fallback.
- **Assistants** — send their stored `max_tokens`, where `0` means "nothing set"; the assistant-specific semantics are documented in [Assistant Runs](../650-Assistants/100-Assistant-Runs.md).
- **Bare model runs** — the OpenAI responses endpoint without an assistant handle, and internal agent surfaces (prompt improvement, title generation, …) send no params and always land on the fallback.

### Behaviour per provider

- **OpenAI-compatible gateways** (GWDG, OpenAI, Mistral, Groq, …) — `max_tokens` is only included in the request when the resolved value is non-null; a null means the endpoint default applies.
- **Anthropic** — the field is required. When the agent supplies none, `AnthropicAdapter` substitutes `64_000`. With extended thinking enabled, the thinking budget is capped at **half** of max tokens (minimum 1,024) — against the 4096 fallback that leaves at most 2,048 thinking tokens.
- **Gemini** — `maxOutputTokens` is only sent when non-null; the thinking budget is likewise capped at half of max tokens.
- **AWS Bedrock** — `inferenceConfig.maxTokens` is nullable and omitted when unset; the Claude thinking configuration falls back to the resolved parameter value.

### Default development setup

With the shipped configuration (`openAi`, `gwdg`, `google` active; `ollama` and `openWebUi` off) no tier ever sets `max_tokens`, so every run lands on the fallback — and the sampling flag decides what actually goes out:

| Provider | Sampling flag source | Effective cap |
|---|---|---|
| OpenAI | ✗ — the LiteLLM static data carries no `supports_sampling_params` | none sent → OpenAI default (effectively the model's max output) |
| GWDG | mixed — the static enricher flags part of the lineup | flagged: **`max_tokens: 4096`**; unflagged: none sent → the endpoint generates into the remaining context |
| Google | ✓ — attached when the live `/models` response carries `temperature` / `top_p` (normally the case) | **`maxOutputTokens: 4096`**, thinking budget halved to 2,048 |

Consequences worth knowing:

- The **default chat model** and all **system models** (title generator, prompt improver, summarizer) resolve to an OpenAI model — uncapped.
- The default **vision/file-upload model** resolves to an unflagged GWDG model — also uncapped.
- Anthropic and Bedrock are not part of the default provider set, so their `64_000` substitution never applies out of the box.

Net: **in a default development environment, only the flagged GWDG models are capped at 4096 — every other model runs uncapped.**

### Model limits are metadata only

`ai_models.limits` (`max_input_tokens` / `max_output_tokens`) — populated by the GWDG static enrichment from the documented context windows and by the LiteLLM catalog — is **informational**. It describes what a model supports and is exposed via the `ai-models` API, but it never caps or validates an outgoing request. The effective cap is always what the resolution chain above produced.
