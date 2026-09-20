# Embeddings API — Implementation Handoff

> **Status: IMPLEMENTED** (see Phase 8 in
> [`001-generic-llm-backend-implementation.md` §5](./001-generic-llm-backend-implementation.md#5-deferred-phases--status)).
> The handoff below is kept as the original specification record; deviations from it
> during implementation are noted in the implementation record.
>
> Purpose: pick-up-and-go specification for `POST /api/hawki/v1/embeddings/{format?}`
> (next step **N7** in
> [`001-generic-llm-backend-implementation.md` §6](./001-generic-llm-backend-implementation.md#6-recommended-next-steps)).
> Written so a fresh session can implement it without re-doing the research.
>
> Reading order for context:
>
> 1. [`POC-HUB-AND-SPOKE.md`](./POC-HUB-AND-SPOKE.md) — the as-built chat proxy; this endpoint mirrors its layering (formatter ⇄ service ⇄ upstream spoke) with no streaming.
> 2. [`001-generic-llm-backend.md`](./001-generic-llm-backend.md) — the proposal:
>    [§6.4 "Non-chat endpoints"](./001-generic-llm-backend.md#64-non-chat-endpoints)
>    specifies the embeddings shapes and the vectorizer pattern verbatim,
>    [§2.3](./001-generic-llm-backend.md#23-service-decomposition) the per-domain
>    service decomposition, [§10](./001-generic-llm-backend.md#10-namespace--file-structure)
>    the namespace tree, [§11 Phase 8](./001-generic-llm-backend.md#phase-8-embeddings-endpoint)
>    the delivery phase.
> 3. [`001-generic-llm-backend-implementation.md`](./001-generic-llm-backend-implementation.md) — deviations record (nothing here changes D1–D13).
>
> Reference for future non-OpenAI embedding formats:
> `research/llm-rosetta/src/llm_rosetta/converters/embedding/` (OpenAI / Jina / Voyage /
> Cohere wire dialects).

---

## 1. Scope

The first non-chat domain. It doubles as the **template for every later domain** (images,
audio, transcription — proposal §6.4/§11 Phases 9–10): one typed request/response pair,
one factory registry, one formatter, one thin controller, one service. Simpler than chat
in one decisive way: **embeddings do not stream** — no stream context, no SSE, no
normalizer.

Decisions already taken (do not re-litigate):
- Token usage is recorded **from day one** (explicitly in the service — see §6).
- `model` is **required** on the wire; no system-default fallback in v1.
- `encoding_format: "base64"` is rejected with 400 in v1 (float only).

---

## 2. Wire format (OpenAI `/v1/embeddings`, format key `openai`)

### Request

```json
{
  "model": "text-embedding-3-small",
  "input": ["first text", "second text"],
  "encoding_format": "float",
  "dimensions": 256,
  "user": "ignored"
}
```

- `input`: string **or** array of strings (normalize both to `string[]`; a bare string
  becomes a one-element list). OpenAI also accepts token-int arrays — out of scope,
  reject non-strings.
- `encoding_format`: `"float"` (v1) — `"base64"` → 400 (`unsupported_encoding_format`).
- `dimensions`, `user`: optional.

### Response

```json
{
  "object": "list",
  "data": [
    {"object": "embedding", "embedding": [0.1, 0.2, 0.3], "index": 0},
    {"object": "embedding", "embedding": [0.4, 0.5, 0.6], "index": 1}
  ],
  "model": "text-embedding-3-small",
  "usage": {"prompt_tokens": 12, "total_tokens": 12}
}
```

`data[]` is **index-ordered to match `input[]` order** — that ordering guarantee is the
core of the format. Errors use the shared OpenAI error body
(`app/Services/Ai/Formatters/Exceptions/FormatterRequestException` shape).

---

## 3. Domain skeleton

Follows the Chat domain layout exactly (`app/Services/Ai/Chat/…` is the reference;
also proposal [§10 tree](./001-generic-llm-backend.md#10-namespace--file-structure)):

```
app/Services/Ai/Embeddings/
├── EmbeddingService.php                          @api, #[Singleton], readonly
├── Contracts/
│   └── VectorizerInterface.php                   vectorize(EmbeddingRequest): EmbeddingsResponse (vendor type)
├── Factories/
│   ├── Contracts/VectorizerFactoryInterface.php  createVectorizer(EmbeddingRequest): ?VectorizerInterface
│   ├── VectorizerRegistry.php                    LazySingletonList + TopSortStringList (copy ChatAgentRegistry)
│   └── Implementations/DefaultVectorizerFactory.php
├── Exceptions/
│   ├── EmbeddingExceptionInterface.php
│   └── EmbeddingNotSupportedException.php        ::forModel(AiModel) — model's provider driver lacks embeddings
└── Values/
    ├── EmbeddingRequest.php                      model, input: string[], ?dimensions, ?encodingFormat, ?user, ?formatKey
    ├── EmbeddingResponse.php                     model, data: EmbeddingItem[], usage: EmbeddingUsageInfo
    ├── EmbeddingItem.php                         embedding: float[], index: int
    └── EmbeddingUsageInfo.php                    promptTokens, totalTokens
```

Formatter layer (kept with the other formatters, mirroring chat):

```
app/Services/Ai/Formatters/Embeddings/
├── Contracts/EmbeddingFormatterInterface.php     getKey / parseRequest / formatResponse / formatError  (no stream methods)
├── EmbeddingFormatterRegistry.php                declare/get/resolve, DEFAULT_KEY = 'openai'
└── Implementations/OpenAi/
    └── OpenAiEmbeddingsFormatter.php
```

**Why a separate contract/registry:** the chat `FormatterInterface`
(`app/Services/Ai/Formatters/Contracts/FormatterInterface.php`) is typed on
`AiRequest`/`AiResponse`; embeddings has its own request/response pair. Proposal §6.4's
rule — "every endpoint gets the same treatment" — means the same *pattern* per domain,
not one shared interface. Give the registry its own lazy-list const
(`AiServiceProvider::EMBEDDING_FORMATTER_LIST = 'ai.embeddingFormatter.list'`).

Controller + route:

```
app/Http/Controllers/Api/V1/EmbeddingsController.php
routes/api.php — inside the existing auth:sanctum + BlockExtApps + AppTokenForbidden group:
    Route::post('/embeddings/{format?}', EmbeddingsController::class)->name('api.hawki.embeddings');
```

---

## 4. Upstream execution (verified vendor facts)

The upstream spoke already exists. Verified against `vendor/laravel/ai/src/`:

- The provider drivers HAWKI already resolves via
  `app/Services/Ai/Providers/AiProviderProxyResolver.php` **implement embeddings on the
  same instance** — e.g. `vendor/laravel/ai/src/Providers/OpenAiProvider.php` is
  `implements … EmbeddingProvider … TextProvider …`. Embedding-capable drivers: OpenAI,
  OpenAiCompatible, Gemini, Ollama, Mistral, Cohere, Jina, Bedrock, Azure, OpenRouter
  (see `vendor/laravel/ai/src/Contracts/Providers/EmbeddingProvider.php`).
- The low-level call (use this — it goes through HAWKI's per-provider driver config):

  ```php
  $proxy = $this->providerProxyResolver->resolveForModel($model);
  $driver = $proxy->driver;                       // Laravel\Ai\Providers\Provider
  $response = $driver->embeddings(                // from EmbeddingProvider
      inputs: $request->input,
      dimensions: $request->dimensions,
      model: $model->model_id,
      timeout: 30,
      providerOptions: []
  );                                               // Laravel\Ai\Responses\EmbeddingsResponse
  ```

- The response shape: `EmbeddingsResponse{embeddings: array<array<float>>, tokens: int, Meta}`
  (`vendor/laravel/ai/src/Responses/EmbeddingsResponse.php`) — vectors arrive
  input-ordered, which is exactly the wire guarantee.
- **Do not use the fluent API** (`Laravel\Ai\Embeddings::for(...)->generate($providerName)`)
  — its provider-by-name resolution bypasses HAWKI's per-provider credentials/driver
  config (the text path needed `ProviderDriverPortal` for exactly this reason; the
  direct `$proxy->driver` call avoids the problem entirely).

`DefaultVectorizerFactory::createVectorizer` therefore:
1. `$model = $this->modelRepository->findOneOrFail($request->model)` (unknown →
   `ModelIdNotAvailableException`, mapped to 404 by the controller — reuse
   `app/Services/Ai/Formatters/Exceptions/UnknownModelException::fromModelException`).
2. `$proxy = $this->providerProxyResolver->resolveForModel($model)`.
3. Guard `$proxy->driver instanceof EmbeddingProvider` — else throw
   `EmbeddingNotSupportedException::forModel($model)` (controller maps → 422
   `model_not_supported_for_embeddings`, param `model`).
4. Return a small vectorizer that performs the call above.

Optional, cheap guard at parse time: if the model's `model_type` is set and ≠
`WellKnownModelTypes::EMBEDDING`, still let it through (drivers decide capability, not
catalog labels) — keep the catalog check advisory only.

---

## 5. Model catalog

- Add `WellKnownModelTypes::EMBEDDING = 'embedding'` to
  `app/Services/Ai/Models/ModelTypes/Values/WellKnownModelTypes.php`.
- **No migration**: `model_type` is a nullable string column
  (`database/migrations/2026_06_29_095334_extend_ai_models_with_info_fields.php`).
- The dev database currently has no embedding-typed models. To type one for live
  verification (or create one):

  ```php
  // bin/env php artisan tinker
  $m = App\Models\Ai\AiModel::firstOrNew(['model_id' => 'text-embedding-3-small', 'provider_id' => 1]);
  $m->model_type = 'embedding'; $m->active = true; $m->save();
  ```

---

## 6. `EmbeddingService` + usage recording (decided: from day one)

```php
#[Singleton]
readonly class EmbeddingService
{
    public function __construct(
        private VectorizerRegistry $vectorizers,
        private UsageAnalyzerService $usageAnalyzer,   // app/Services/Ai/UsageAnalyzerService.php
        private UsageContext $usageContext,            // app/Services/System/UsageTypes/UsageContext.php
        private Request $request,                      // user agent, like the chat listeners
    ) {}

    public function send(EmbeddingRequest $request): EmbeddingResponse
    // 1. $vectorizer = $this->vectorizers->getVectorizer($request);   (throws when none claims)
    // 2. $vendorResponse = $vectorizer->vectorize($request);
    // 3. record usage + dispatch, then map to IR EmbeddingResponse
}
```

Usage recording mirrors the chat semantics minus the agent events (embeddings bypass
agents, so the listeners in `app/Services/Ai/Listeners/` never fire — that is expected,
not a gap):

- `TokenUsage::fromLaravelUsage()` is text-shaped (`prompt+completion`); construct
  directly instead: `new TokenUsage(model: $model, promptTokens: $response->tokens, completionTokens: 0)`
  (`app/Services/Ai/Values/TokenUsage.php`).
- Type: `UsageContext` → `WellKnownUsageTypes::EXTERNAL_APP ? 'api' : 'private'`
  (same mapping as `app/Services/Ai/Listeners/RecordChatUsageListener.php`).
- Then dispatch `App\Services\Ai\Chat\Events\UsageRecordedEvent` — **it is chat-named
  but payload-generic** (`tokenUsage, usageType, channel, modelId, formatKey, userAgent`);
  either reuse it with `channel: 'embeddings'` (recommended, zero new code) or relocate
  it to a neutral namespace in the same PR. Note the choice in the implementation record.

---

## 7. Controller sketch

```php
// app/Http/Controllers/Api/V1/EmbeddingsController.php — mirror ChatController's thinness
public function __invoke(Request $request, ?string $format = null): Response
{
    $formatter = $this->formatters->resolve($format);          // EmbeddingFormatterRegistry

    try {
        $embeddingRequest = $formatter->parseRequest($request);
    } catch (FormatterRequestException $e) {
        return $formatter->formatError($e);
    }

    try {
        return $formatter->formatResponse($this->embeddingService->send($embeddingRequest));
    } catch (FormatterRequestException $e) {
        return $formatter->formatError($e);
    } catch (ModelIdNotAvailableException $e) {
        return $formatter->formatError(UnknownModelException::fromModelException($e));
    } catch (EmbeddingNotSupportedException $e) {
        return $formatter->formatError(/* 422 code model_not_supported_for_embeddings */);
    }
}
```

Unknown non-null `{format}`: error immediately (400 `unknown_format`) — the D10 lesson
from the chat endpoint, applied correctly from the start here.

---

## 8. Test plan

- **Unit — formatter** (`tests/Unit/Services/Ai/Formatters/Embeddings/…`):
  string-input normalization, base64 rejection, index/order fidelity of `data[]`, usage
  mapping, error rendering.
- **Unit — factory**: stub `AiProviderProxyResolver` (pattern:
  `tests/Unit/Services/Ai/Chat/Factories/Implementations/ChatAgentFactoryTest.php`),
  driver stub `EmbeddingProvider` vs plain `Provider` (guard path).
- **Feature — endpoint** (`tests/Feature/Api/Embeddings/EmbeddingsEndpointTest.php`):
  swap `VectorizerRegistry` with a fake registry returning a fixed vectorizer (pattern:
  the `mockAgent` registry swap in `tests/Feature/Api/Chat/ChatEndpointTest.php`);
  assert guest 401, happy path, unknown model 404, non-embedding driver 422, unknown
  format 400, and a `UsageRecord` row + `UsageRecordedEvent` after a successful call.
- **Live**: seed a model (§5) and run one real call against the OpenAI provider
  (`text-embedding-3-small`) — assert ordered vectors and plausible usage.

## 9. Definition of done

- `POST /api/hawki/v1/embeddings` and `/embeddings/openai` return spec-shaped,
  input-ordered responses; errors in the OpenAI error body.
- Usage row per request; `UsageRecordedEvent` with `channel: 'embeddings'`.
- phpstan + php-cs-fixer + full suites green; live check documented.
- Update [`001-generic-llm-backend-implementation.md` §5](./001-generic-llm-backend-implementation.md#5-deferred-phases--status)
  (Phase 8 → done) and note the `UsageRecordedEvent` relocation choice.
