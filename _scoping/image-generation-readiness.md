# Image generation readiness

Investigated on 2026-09-15 at commit `05b68e00`. This is a source-code assessment, with the installed Laravel AI SDK inspected alongside application code. No live generation request was made, and deployed provider credentials, enabled models, and external MCP servers were not checked.

## Conclusion

HAWKI can be extended to generate images using dependencies and infrastructure it already has. The current application does not implement an end-to-end image-generation flow. Adding an image model or enabling the admin image-generation capability does not supply that flow.

## What already exists

| Area | Evidence | Meaning |
| --- | --- | --- |
| Model types | [`WellKnownModelTypes.php:17`](../app/Services/Ai/Models/ModelTypes/Values/WellKnownModelTypes.php#L17) declares `image_generation`. [`LiteLlmApiEnricher.php:97`](../app/Services/Ai/ModelInformation/Enrichment/Implementations/LiteLlmApiEnricher.php#L97) recognizes that catalog mode. | Image models can be represented in model metadata. |
| Admin capability | [`capabilities.ts:24`](../resources/js/plugins/admin/capabilities.ts#L24) maps image generation to `output: ['image']`. [`ModelCard.svelte:121`](../resources/js/plugins/core/components/ModelCard.svelte#L121) derives its badge from that value. | The toggle and badge describe the model. They do not implement generation. |
| SDK | [`composer.lock:2282`](../composer.lock#L2282) locks `laravel/ai` to `v0.10.1`; installed package metadata matches. [`Image.php:18`](../vendor/laravel/ai/src/Image.php#L18) exposes `Image::of()`, and [`PendingImageGeneration.php:120`](../vendor/laravel/ai/src/PendingResponses/PendingImageGeneration.php#L120) implements generation. | A generation API is already installed. |
| Provider execution | HAWKI constructs Laravel's image-capable OpenAI and Gemini providers in [`OpenAiAdapter.php:49`](../app/Services/Ai/Providers/Adapters/Implementations/OpenAiAdapter.php#L49) and [`GeminiAdapter.php:63`](../app/Services/Ai/Providers/Adapters/Implementations/GeminiAdapter.php#L63). Their inherited SDK gateways implement generation in [`OpenAiGateway.php:59`](../vendor/laravel/ai/src/Gateway/OpenAi/OpenAiGateway.php#L59) and [`GeminiGateway.php:115`](../vendor/laravel/ai/src/Gateway/Gemini/GeminiGateway.php#L115). | These providers supply a plausible implementation route. Actual provider/model access still needs verification. |
| Image input | [`UserMessageAttachments.php:76`](../app/Services/Ai/Agents/Utils/UserMessageAttachments.php#L76) checks image input support and converts files to base64 SDK attachments. | Existing vision support handles images sent to the model. |

Laravel's [official image-generation documentation](https://laravel.com/docs/13.x/ai-sdk#images) also documents generation, reference images, storage, and queued execution. The local installed source, rather than current documentation alone, supports the SDK findings above.

## What prevents generation today

1. **Request dispatch always selects chat for the supported payload.** [`AiServiceProvider.php:227`](../app/Providers/AiServiceProvider.php#L227) registers the legacy chat factory. [`ChatAgentFromLegacyRequestFactory.php:69`](../app/Services/Ai/Agents/Implementations/Chat/ChatAgentFromLegacyRequestFactory.php#L69) builds a `ChatAgent` without branching into image generation based on model type.

2. **The agent contract returns text responses.** [`AgentInterface.php:19`](../app/Services/Ai/Agents/Contracts/AgentInterface.php#L19) requires `AgentResponse` and `StreamableAgentResponse`. [`AbstractLaravelAgent.php:82`](../app/Services/Ai/Agents/Adapters/AbstractLaravelAgent.php#L82) uses the SDK's text prompt/stream methods. The SDK's [`ImageResponse.php:20`](../vendor/laravel/ai/src/Responses/ImageResponse.php#L20) instead carries a collection of generated images, usage, and metadata.

3. **The active controller does not transport image results.** [`StreamController.php:265`](../app/Http/Controllers/StreamController.php#L265) handles text, reasoning, citations, tool events, and completion. Its completion content is `$res->text`. The external synchronous API similarly returns `$response->text` at [line 85](../app/Http/Controllers/StreamController.php#L85). There is no generated-file event or image response mapping in this path.

4. **Image metadata and accounting are incomplete.** [`LiteLlmApiEnricher.php:75`](../app/Services/Ai/ModelInformation/Enrichment/Implementations/LiteLlmApiEnricher.php#L75) only applies detailed enrichment to chat models. [`AiServiceProvider.php:100`](../app/Providers/AiServiceProvider.php#L100) registers pricing and limits only for chat. [`TokenUsage.php:35`](../app/Services/Ai/Values/TokenUsage.php#L35) exposes prompt/completion token counts, without image count, size, or quality. Image accounting needs an explicit design, especially for providers charging per image.

5. **Generated images would not be replayed as assistant attachments.** [`ChatAgentFromLegacyRequestFactory.php:180`](../app/Services/Ai/Agents/Implementations/Chat/ChatAgentFromLegacyRequestFactory.php#L180) loads attachments for user turns, while [line 198](../app/Services/Ai/Agents/Implementations/Chat/ChatAgentFromLegacyRequestFactory.php#L198) reconstructs assistant turns from text alone. Image editing across turns therefore needs additional history handling.

An external MCP tool is a separate possibility. [`LaravelMcpTool.php:113`](../app/Services/Ai/Tools/LaravelAi/LaravelMcpTool.php#L113) can call a configured tool, but JSON-encodes its result as a string. An external service could generate an image and return a URL for the model to mention in Markdown. That is a possible external integration, not evidence of built-in binary image handling or durable generated attachments.

## Frontend and storage

- The Svelte client has no image result packet. [`types.ts:47`](../resources/js/kernel/ai/types.ts#L47) defines the stream packet whitelist. [`ChatTransport.ts:343`](../resources/js/plugins/core/modules/chat/transport/ChatTransport.ts#L343) initializes assistant attachments to an empty array; [line 426](../resources/js/plugins/core/modules/chat/transport/ChatTransport.ts#L426) encrypts and saves text, citations, reasoning, and statistics without generated attachments.
- Existing file storage can support the result. [`AiConvController.php:115`](../app/Http/Controllers/Api/V1/AiConvController.php#L115) stores uploaded files temporarily and returns a UUID. [`PrivateMessageHandler.php:50`](../app/Services/Chat/Message/Handlers/PrivateMessageHandler.php#L50) makes referenced files permanent and attaches them to messages. [`AbstractAiConvMessageRequest.php:30`](../app/Http/Requests/Api/V1/AbstractAiConvMessageRequest.php#L30) allows attachment UUIDs, including on assistant messages. A generation service still needs to ingest its output and connect those references to the assistant save flow. The handler assigns assistant attachment records using the AI user, so ownership and retrieval should be verified during implementation.
- The current Svelte message view renders attachments as filename download links in [`ChatMessage.svelte:179`](../resources/js/plugins/core/modules/chat/components/ChatMessage.svelte#L179). It needs an image preview for generated attachments. Input image previews elsewhere do not supply this output UI.
- Markdown image URLs can display through [`Markdown.svelte`](../resources/js/components/util/markdown/Markdown.svelte) and its installed [`ImageNode.svelte`](../node_modules/markstream-svelte/dist/components/ImageNode.svelte) renderer. A URL embedded in message text remains dependent on that URL being available; it does not become a HAWKI attachment automatically.

## Implementation direction

These are recommendations inferred from the code, not existing behavior.

- Add a dedicated image-generation service with a JSON:API entry point for the Svelte frontend. Resolve the selected HAWKI provider and model, verify image-provider support, and call the SDK image API. Existing [`ProviderDriverPortal.php`](../app/Services/Ai/LaravelAi/Values/ProviderDriverPortal.php) supplies a bridge to resolved drivers for immediate calls. Queued work should resolve the provider inside the job because portal tokens live only in process memory.
- Keep a distinct image result contract, or deliberately generalize the agent contract. Returning an SDK `ImageResponse` from today's `AgentInterface::send()` would violate its return type.
- Persist generated bytes through HAWKI's file lifecycle and attach stable file references to assistant messages. Carry the result to the frontend and render it through the attachment UI. Preserve the applicable private-chat encryption and group access rules.
- Add generation progress/failure states and image settings, then account for usage using the selected provider's pricing. Start with one verified provider/model combination.
- For conversational editing, pass prior generated images back as references rather than relying on the current assistant-text history.

The provider call is largely available already. Application dispatch, result transport, file persistence, and chat integration are the work required to make it a user-facing feature.

## Validation

Traced the registered factory, provider adapters, installed SDK image methods, controller response mapping, frontend packet types, assistant persistence, and attachment rendering. Searched application code, frontend code, routes, and tests for image-generation calls and output payload handling. Existing image-generation references in application behavior are metadata and UI capability mappings. No application code was changed or tests run. A native GPT-5.6 Terra agent independently inspected the frontend and attachment flow; its cited findings were checked against the source.
