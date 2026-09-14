# Admin form field research

The installed HAWKI source and its installed dependencies define the values accepted by this deployment. Public vendor APIs can accept more values than HAWKI forwards. The controls below follow HAWKI's storage and adapter contracts. Client validation supplements the existing server validation, including database existence, permissions, uniqueness and policy overlap checks.

## Field inventory

| Section / field | Stored value and supported entries | Input |
| --- | --- | --- |
| Providers / `additional_config` | Encrypted map. The built-in Azure adapter reads `version`, default `2024-10-21`. Other built-in adapters do not read this map. Extensions may add entries. | Azure API version field; typed property rows for extensions. Blank means keep the stored map. |
| Providers / `settings` | Map containing `model_parameters` and `adapter`; additional entries are allowed. Adapter settings are merged into the Laravel driver configuration. | Parameter controls and adapter-specific fields, with typed additional properties. |
| Models / `input`, `output` | Arrays of `text`, `image`, `audio`, `video`. | Checkboxes. |
| Models / `parameters` and providers / `settings.model_parameters` | Map. Built-in keys: `temperature`, `top_p`, `max_tokens`, `max_thinking_tokens`. Values resolve from request overrides, then model overrides, then provider defaults. Additional parameters remain extensible. | Optional numeric fields and typed additional parameter rows. |
| Models / `native_capabilities` | String tags: `web_search`, `web_fetch`, `knowledge_base`, `code_execution`, `tool_calling`; extensions can add tags. | Checkboxes and an additional tag input. |
| Models / `settings` | `max_tool_calling_rounds` defaults to 5, streaming equivalent to 3; `file_upload` and `tool_calling` default false, `native_capabilities` defaults true. Registry extensions can add settings. | Optional integer fields and default/yes/no selectors. Additional settings retain their types. |
| Models / `limits` | `max_input_tokens` and `max_output_tokens`, nullable integers. The registered chat implementation ignores other keys. | Optional positive integer fields. |
| Models / `pricing` | `ranges` and `priority_ranges`, each nullable or a list of tiers. Each tier has `currency`, four nullable per-token costs and `range: [inclusiveStart, exclusiveEnd]`. Null end means unlimited. | Independent unknown/free/tiered selectors with repeatable tier forms. |
| Models / `flags` | String tags from `WellKnownModelFlags`; extensions may add tags. | Checkboxes with additional tag input. |
| MCP / `additional_config` | Encrypted map. STDIO consumes `args` as a string list and `env` as a string map. HTTP/SSE consumes `headers` as a string map and `http_options` as a typed option map. | Argument rows, environment/header name and secret-value rows, HTTP option controls. |
| MCP / `timeouts` | `read`, `connect`, `sse_idle`, nullable numbers. Admin validation permits 0.1–120 seconds. | Three optional decimal number inputs. |
| Announcements / `target_users` | User database IDs as integers. Server validates existence. | Searchable user checklist with names supplied by the admin section response. |
| Settings / `ALLOWED_FILE_MIME_TYPES`, `ALLOWED_AVATAR_MIME_TYPES` | String arrays; pattern `^[a-z0-9.+-]+/[a-z0-9.+*-]+$`, at most 100 characters per value. Empty allows the application's supported types. | Common MIME choices and custom MIME entry, preserving wildcard values. |

Field declarations and server constraints: [ResourceCatalog](../app/Services/Admin/ResourceCatalog.php), [ConfigurationManager](../app/Services/Admin/ConfigurationManager.php), [SystemSettings](../app/Services/Admin/SystemSettings.php), [SectionReader](../app/Services/Admin/SectionReader.php).

## Provider and model details

[ProviderSettings](../app/Services/Ai/Providers/Values/ProviderSettings.php) keeps arbitrary entries and hydrates `model_parameters`. [DriverFactory](../app/Services/Ai/Providers/Adapters/DriverFactory.php) merges `settings.adapter` into the driver configuration before explicit adapter configuration. The [Azure adapter](../app/Services/Ai/Providers/Adapters/Implementations/AzureOpenAiAdapter.php) reads the encrypted API version. The [Bedrock adapter](../app/Services/Ai/Providers/Adapters/Implementations/AwsBedrockAdapter.php) reads adapter `region` and `version`, with `eu-central-1` and `latest` defaults. Its API key accepts either `KEY SECRET` or `token:TOKEN`; `additional_config` is not its credential source. The installed [Bedrock driver](../vendor/laravel/ai/src/Providers/BedrockProvider.php) also supports session credentials and assume-role configuration. Other driver settings and plugin settings have no single closed enum; preserve existing properties and allow typed additions.

[AiModelParameters](../app/Services/Ai/Models/Parameters/Values/AiModelParameters.php) has fallback values 0.95, 1.0, 4096 and 2048 for the four built-in parameters. [WellKnownModelParams](../app/Services/Ai/Models/Parameters/Values/WellKnownModelParams.php) documents typical temperature and top-p ranges of 0–2 and 0–1. The form uses those ranges, positive integral maximum output tokens and nonnegative integral thinking tokens. A provider or model may impose tighter restrictions. [AiServiceProvider](../app/Providers/AiServiceProvider.php) declares model setting defaults and the chat pricing/limit implementations. [ChatAiModelLimits](../app/Services/Ai/Models/Limits/Values/ChatAiModelLimits.php) serializes nullable input/output limits.

[WellKnownCapabilities](../app/Services/Ai/Models/Capabilities/Values/WellKnownCapabilities.php) defines the built-in capability choices. [WellKnownModelFlags](../app/Services/Ai/Models/Flags/Values/WellKnownModelFlags.php) defines all 20 flag choices, including `eco-friendly`, `self-hosted`, `strength-creative-writing` and `strength-code-generation`. [AbstractTagList](../app/Services/Ai/Utils/AbstractTagList.php) stores lists of strings and normalizes tag case/whitespace. These are lists, not boolean maps.

## Pricing semantics

[ChatAiModelPricing](../app/Services/Ai/Models/Pricing/Values/Chat/ChatAiModelPricing.php) distinguishes unknown pricing from free pricing: both lists null means unknown, both empty means free. One list may be populated while the other is unknown. [ChatPricingRange](../app/Services/Ai/Models/Pricing/Values/Chat/ChatPricingRange.php) defines USD/EUR constants and these optional cost fields: `input_cost_per_token`, `input_cost_per_cached_token`, `output_cost_per_token`, `output_cost_per_reasoning_token`. Costs are per token, not per million tokens. The end of a range is exclusive; null end maps to `PHP_INT_MAX`. Use null in the browser rather than converting that integer through JavaScript floating point. The UI validates increasing, non-overlapping ranges and nonnegative costs, matching the intended invariants of `addRange` even though `fromArray` does not check overlap.

## MCP transport options

[McpClientFactory](../app/Services/Ai/Tools/Mcp/McpClientFactory.php) merges extra configuration over defaults. `api_key` supplies a Bearer Authorization header unless overridden by custom headers. [McpServerTimeouts](../app/Services/Ai/Tools/Values/McpServerTimeouts.php) maps `read`, `connect`, `sse_idle` to transport options. The factory uses a 10-second read timeout when unset.

The installed [MCP Client](../vendor/logiscape/mcp-sdk-php/src/Client/Client.php) reads these `http_options` keys: `connectionTimeout`, `readTimeout`, `sseIdleTimeout`, `enableSse`, `maxRetries`, `retryDelay`, `verifyTls`, `caFile`, `curlOptions`, `sseDefaultRetryDelay`, `sseReconnectBudget`, and `autoSse`. [HttpConfiguration](../vendor/logiscape/mcp-sdk-php/src/Client/Transport/HttpConfiguration.php) defines numeric, boolean, string and map types and defaults. `curlOptions` keys are numeric cURL identifiers and values depend on the selected option. Its OAuth option requires an `OAuthConfiguration` PHP object, so a JSON map cannot configure OAuth through this admin endpoint. Do not offer a misleading OAuth form or guessed options such as `verifyPeer` and `verifyHost`.

## Other dialog validation

Each writable section has a Zod object schema, selected when its create/update dialog mounts. Settings select a schema by setting key. Actor-filtered user forms validate only fields included by the server. [PeopleManager](../app/Services/Admin/PeopleManager.php) owns role, mapping and account rules. [ConfigurationManager](../app/Services/Admin/ConfigurationManager.php) owns field lengths, IDs, URL schemes, immutable keys, announcement dates and policy restrictions. Database-dependent checks remain on the server and their errors are shown in the form.

[SystemSettings](../app/Services/Admin/SystemSettings.php) provides the number ranges: connection timeout 60–86400 seconds, maximum file size 1–1073741824 bytes, avatar size 1–52428800 bytes, attachment count 0–100, retention 1–120 months. MIME defaults and empty-list meaning come from [filesystems configuration](../config/filesystems.php).

## TanStack Form integration

TanStack Form supports Zod directly through Standard Schema. Use `createForm`, form-level validators and `form.Field` for field changes and blur events; schema errors are associated with field paths. See the [official Svelte Standard Schema example](https://tanstack.com/form/latest/docs/framework/svelte/examples/standard-schema) and [dynamic validation guide](https://tanstack.com/form/latest/docs/framework/svelte/guides/dynamic-validation). The installed package source confirms `form.useSelector` and the error map shape. Submit the parsed values explicitly; schema validation alone does not replace form values with transformed output.

Structured inputs retain objects and arrays throughout editing. PHP empty-map arrays need normalization only where a map is expected. Blank encrypted configuration must be omitted from the request; it must not overwrite stored secrets. Changing an encrypted map replaces the entire map, which the dialog explains. Existing extension values must survive unrelated edits.

Composite controls register their root field with TanStack Form. A Zod wrapper maps nested issues to that registered field while retaining the nested label in the message, so correcting a nested value clears its error and permits resubmission.
