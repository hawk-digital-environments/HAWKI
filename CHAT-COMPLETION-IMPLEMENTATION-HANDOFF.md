# Chat Completions Formatter — Implementation Handoff

> **Status: IMPLEMENTED** (see N1/D10 in
> [`001-generic-llm-backend-implementation.md` §6](./001-generic-llm-backend-implementation.md#6-recommended-next-steps)).
> The handoff below is kept as the original specification record; deviations made
> during implementation (always-emitted usage chunk, `chatcmpl-` prefix, custom-frames
> switch) are noted in the implementation record.
>
> Purpose: pick-up-and-go specification for the `openaiChatCompletions` wire format
> (next step **N1** in
> [`001-generic-llm-backend-implementation.md` §6](./001-generic-llm-backend-implementation.md#6-recommended-next-steps)).
> Written so a fresh session can implement it without re-doing the research.
>
> Reading order for context:
>
> 1. [`POC-HUB-AND-SPOKE.md`](./POC-HUB-AND-SPOKE.md) — the as-built architecture (esp. §4 formatters, §9 extension guide + "add a wire format" checklist).
> 2. [`001-generic-llm-backend.md`](./001-generic-llm-backend.md) — the proposal (esp. [§4.3](./001-generic-llm-backend.md#43-the-three-launch-formatters): `openai` is a launch formatter, and [§7.2](./001-generic-llm-backend.md#72-sse-wire-format) for the wire examples).
> 3. [`001-generic-llm-backend-implementation.md`](./001-generic-llm-backend-implementation.md) — deviations record (D10 is resolved by this work).
>
> Reference implementation to port from: `research/llm-rosetta/src/llm_rosetta/converters/openai_chat/`.

---

## 1. Scope

| In scope | Out of scope |
|---|---|
| `OpenAiChatCompletionsFormatter` + stream context under `app/Services/Ai/Formatters/Implementations/OpenAiChatCompletions/` | The `legacy` NDJSON formatter (N3) |
| Registry entry (format key **`openai`**, declared in `app/Providers/AiServiceProvider.php`) | Route forwarding of old routes (proposal Phase 3, second half) |
| The D10 decision: unknown `{format}` segments start erroring | Client-side tool-loop changes (already format-agnostic — works via IR) |
| Unit + feature tests | New IR types (**none needed** — the IR already covers everything) |

No service-layer changes: the hub (`ChatService`, factory chain, normalizer) is
format-agnostic and done. This is a pure downstream spoke.

---

## 2. Wire format reference

The OpenAI Chat Completions API (`POST /v1/chat/completions`). Authoritative references:
`research/llm-rosetta/src/llm_rosetta/converters/openai_chat/converter.py` (worked
translation logic) and OpenAI's docs. The essentials:

### 2.1 Request

```json
{
  "model": "gpt-4.1-nano",
  "messages": [
    {"role": "system", "content": "Be terse."},
    {"role": "user", "content": "Hi"},
    {"role": "assistant", "content": null, "tool_calls": [
      {"id": "call_abc", "type": "function", "function": {"name": "read_file", "arguments": "{\"path\":\"/x\"}"}}
    ]},
    {"role": "tool", "tool_call_id": "call_abc", "content": "file-a"}
  ],
  "tools": [{"type": "function", "function": {"name": "read_file", "description": "…", "parameters": {}, "strict": false}}],
  "tool_choice": "auto",
  "temperature": 0.7, "top_p": 1, "max_tokens": 1024, "stop": ["END"],
  "response_format": {"type": "json_schema", "json_schema": {"name": "out", "schema": {}, "strict": true}},
  "stream": true, "stream_options": {"include_usage": true},
  "hawki": {"tools": ["capability:web_search:auto"], "attachments": ["<uuid>"], "params": {"temp": 0.1}}
}
```

User content may be a string or a parts array (`text`, `image_url` `{url, detail?}`,
`input_audio`, `file`). Assistant content is a string or `null` alongside `tool_calls`.

### 2.2 Response (non-streaming)

```json
{
  "id": "chatcmpl-…", "object": "chat.completion", "created": 1700000000,
  "model": "gpt-4.1-nano", "system_fingerprint": null,
  "choices": [{
    "index": 0,
    "message": {"role": "assistant", "content": "Hello", "tool_calls": [], "reasoning_content": null},
    "finish_reason": "stop"
  }],
  "usage": {"prompt_tokens": 3, "completion_tokens": 4, "total_tokens": 7}
}
```

`finish_reason` vocabulary: `stop`, `length`, `tool_calls`, `content_filter`.

### 2.3 Streaming — three differences from Open Responses that matter

Frames are **bare `data:` lines with no `event:` field**:

```
data: {"id":"chatcmpl-…","object":"chat.completion.chunk","created":…,"model":"…","choices":[{"index":0,"delta":{"role":"assistant","content":""},"finish_reason":null}]}

data: {"…","choices":[{"index":0,"delta":{"content":"Hel"},"finish_reason":null}]}

data: {"…","choices":[{"index":0,"delta":{"tool_calls":[{"index":0,"id":"call_abc","type":"function","function":{"name":"read_file","arguments":""}}]},"finish_reason":null}]}

data: {"…","choices":[{"index":0,"delta":{"tool_calls":[{"index":0,"function":{"arguments":"{\"path\":\"/x\"}"}}]},"finish_reason":null}]}

data: {"…","choices":[{"index":0,"delta":{},"finish_reason":"stop"}]}

data: {"…","choices":[],"usage":{"prompt_tokens":3,"completion_tokens":4,"total_tokens":7}}

data: [DONE]

```

1. **No `event:` line** — unlike the openResponses formatter, which writes
   `event: <type>\ndata: <json>`. Only `data: <json>\n\n` frames.
2. **Tool-call deltas are addressed by `index`**, with `id`/`type`/`name` present only on
   the first fragment of each call. The IR side (`ToolCallStartEvent` carries id+name,
   `ToolCallDeltaEvent` carries fragments) maps cleanly — see §4.2.
3. The terminal `finish_reason` chunk (empty `delta`) and — only when
   `stream_options.include_usage` was requested — a trailing usage chunk with an empty
   `choices` array. Then `data: [DONE]`.

Mid-stream errors are a `data: {"error": {...}}` frame followed by `[DONE]`.

---

## 3. Mapping tables (wire ⇄ IR)

The IR lives in `app/Services/Ai/Chat/Values/` (see
[`POC-HUB-AND-SPOKE.md` §3](./POC-HUB-AND-SPOKE.md#3-the-hub-the-chat-ir)).

### 3.1 `parseRequest` — wire → `AiRequest`

| Wire | IR |
|---|---|
| `model` | `AiRequest::$model` (required for this format — no default fallback; empty → 400 `missing_model`) |
| `messages[role=system\|developer]` | appended to `AiRequest::$systemInstruction` (design mirrors how `OpenResponsesFormatter` handles system/developer items — see `app/Services/Ai/Formatters/Implementations/OpenResponses/OpenResponsesFormatter.php::parseMessageItem`) |
| `messages[role=user]`, content string/parts | `UserMessage` with `TextPart` / `ImagePart` (`image_url.url`, `detail`) / `FilePart` / `AudioPart` |
| `messages[role=assistant].content` | `AssistantMessage` + `TextPart` |
| `messages[role=assistant].tool_calls[]` | `ToolCallPart(toolCallId: id, toolName: function.name, toolInput: json_decode(function.arguments))` — **arguments arrive as a JSON string** |
| `messages[role=assistant].reasoning_content` | `ReasoningPart` (non-standard field; parse when present) |
| `messages[role=tool]` (`tool_call_id`, `content`) | `ToolMessage` + `ToolResultPart(toolCallId: tool_call_id, result: content)` |
| `tools[].function` | `ToolDefinition(name, description, parameters)`; `strict` → `ToolDefinition::$metadata['strict']` |
| `tool_choice` `"none"/"auto"/"required"` / `{type:"function",function:{name}}` | `ToolChoice` with `ToolChoiceMode::NONE/AUTO/ANY/TOOL` + `toolName` |
| `temperature`, `top_p`, `max_tokens` (or `max_completion_tokens`), `frequency_penalty`, `presence_penalty`, `stop` (string→array), `seed`, `logprobs`, `top_logprobs` | `GenerationConfig` (`stopSequences`, `seed`, `logprobs`, `topLogprobs` all exist on the IR) |
| `response_format` | `ResponseFormatConfig` (type, `json_schema.schema` → `$jsonSchema`, `name`, `strict`) |
| `parallel_tool_calls` | `ToolCallConfig::$disableParallel = !value` |
| `stream`, `stream_options.include_usage` | `StreamConfig::$enabled`, `StreamConfig::$includeUsage` |
| `n`, `logit_bias`, `user` | **not parsed** — park in `providerExtensions` (deviation D8 in the implementation record says stop silently ignoring such fields) |
| `hawki` top-level object | `hawkiExtensions` — reuse the same convention; consider extracting the parsing from `OpenResponsesFormatter::parseHawkiExtensions` into a shared helper if copy-pasting feels bad |

**Trailing-item rule** (mirror `OpenResponsesFormatter::lastContinuableMessage`): the last
message must have role `user` or `tool`. A trailing bare `assistant` or
assistant-with-`tool_calls` cannot be continued → 400 (reuse
`app/Services/Ai/Formatters/Exceptions/InvalidInputItemException::forMissingTrailingUserMessage`).

### 3.2 `formatResponse` — `AiResponse` → wire

| IR | Wire |
|---|---|
| `id` | prefix with `chatcmpl_` if not already (mirror `ensureResponseIdPrefix` style from the stream context) |
| `message` `TextPart`s | `choices[0].message.content` (concatenated) |
| `message` `ToolCallPart`s | `choices[0].message.tool_calls[]` — `arguments` **json_encode'd to a string** |
| `message` `ReasoningPart` | `choices[0].message.reasoning_content` |
| `finishReason` | `finish_reason`: `stop/length/tool_calls/content_filter` direct; `refusal` → `content_filter`; `error/cancelled` → `stop` |
| `usage` | `UsageInfo` fields already use prompt/completion naming — direct map incl. `total_tokens` |
| citations (`CitationPart`) | no native slot — omit (streaming carries them as `hawki:` frames) |

### 3.3 Errors

Reuse the OpenAI error body shape and the existing exception hierarchy in
`app/Services/Ai/Formatters/Exceptions/` (`FormatterRequestException` and friends are
format-agnostic — `formatError()` renders `{"error": {message, type, param, code}}`).

---

## 4. Class plan

```
app/Services/Ai/Formatters/Implementations/OpenAiChatCompletions/
├── OpenAiChatCompletionsFormatter.php     (implements FormatterInterface, KEY = 'openai')
└── OpenAiChatCompletionsStreamContext.php (stateful IR-event → chunk translation)
```

### 4.1 Formatter responsibilities

Mirror the structure of
`app/Services/Ai/Formatters/Implementations/OpenResponses/OpenResponsesFormatter.php`:
`parseRequest` / `formatResponse` / `formatStream` / `getStreamHeaders`
(`text/event-stream` etc., same as openResponses) / `formatError`.
`getStreamHeaders` can be shared verbatim — copy or extract a trait, either is fine.

### 4.2 `OpenAiChatCompletionsStreamContext` — IR event → chunk(s)

State: chunk `id`/`model`/`created` (from `StreamStartEvent`), tool-call
index↔id map, buffered usage + finish (deferred like the openResponses context —
[`POC-HUB-AND-SPOKE.md` §4.4](./POC-HUB-AND-SPOKE.md#44-openresponsesstreamcontext)),
`includeUsage` flag (from parse — pass it into the context constructor).

| IR event | Emitted chunk(s) |
|---|---|
| `StreamStartEvent` | (buffer, don't emit yet — Rosetta merges the role chunk into the first content delta; emitting a standalone role chunk first is also spec-fine and simpler. Pick one, test it.) |
| first `TextDeltaEvent` / `ToolCallStartEvent` / `ReasoningDeltaEvent` | if the role chunk hasn't been emitted: `{delta: {role: "assistant", content: ""}}` first |
| `TextDeltaEvent` | `{delta: {content}}` |
| `ToolCallStartEvent` | `{delta: {tool_calls: [{index, id, type: "function", function: {name, arguments: ""}}]}}` — `index` = `$toolCallIndex ?? assign from map` |
| `ToolCallDeltaEvent` | `{delta: {tool_calls: [{index, function: {arguments: fragment}}]}}` |
| `ReasoningDeltaEvent` | `{delta: {reasoning_content}}` (DeepSeek-style field; OpenAI tolerates unknown delta keys) |
| `HawkiCitationEvent` | a `data:` frame `{"type": "hawki:citation", …}` (custom frame; clients ignore unknown JSON) |
| `HawkiProviderToolEvent` | optional, same pattern — safe to skip in v1 |
| `ContentBlockStart/EndEvent` | nothing (implicit boundaries in this format) |
| `FinishEvent` | buffer; on `StreamEndEvent` emit `{delta: {}, finish_reason}` |
| `UsageEvent` | buffer; after the finish chunk, if `includeUsage`: `{choices: [], usage}` |
| `StreamEndEvent` | flush finish (+usage) chunk; the formatter then writes `data: [DONE]` |

All chunks share `{id, object: "chat.completion.chunk", created, model, system_fingerprint: null}`.
**No `sequence_number`** — that's an Open Responses concept.

Mid-stream exceptions: `data: {"error": {message, type: "server_error", param: null, code: null}}`
then `[DONE]` (mirror `OpenResponsesStreamContext::error`, minus `response.failed` which
has no Chat Completions equivalent).

---

## 5. Rosetta port map

Everything below is in `research/llm-rosetta/src/llm_rosetta/`:

| Rosetta file | Port to |
|---|---|
| `converters/openai_chat/converter.py` — `request_from_provider` / `response_to_provider` | `parseRequest` / `formatResponse` |
| `converters/openai_chat/content_ops.py` | content-part mapping (§3.1 rows for user/assistant parts) |
| `converters/openai_chat/message_ops.py` | role mapping, system hoisting, tool-result threading |
| `converters/openai_chat/tool_ops.py` | tool definitions + tool_choice vocabularies |
| `converters/openai_chat/config_ops.py` | generation params (watch `stop` string↔list normalization) |
| `converters/openai_chat/converter.py` (stream sections) — esp. `_resolve_tool_call_delta` (index-based id recovery) | `OpenAiChatCompletionsStreamContext` |
| `tests/converters/openai_chat/test_stream.py` (61 tests) | the model for stream-context unit tests — port the interesting sequences: implicit text-block boundaries, index-addressed tool deltas, finish dedup, usage-only-final |
| `tests/converters/openai_chat/test_content_ops.py`, `test_tool_ops.py`, `test_message_ops.py`, `test_config_ops.py`, `test_converter.py` | fixtures for `OpenAiChatCompletionsFormatterTest` |

---

## 6. Wiring checklist

1. **Registry** — in `app/Providers/AiServiceProvider.php`, extend the existing block:
   ```php
   ->declare(OpenAiChatCompletionsFormatter::KEY, OpenAiChatCompletionsFormatter::class)
   ```
   (list const `AiServiceProvider::FORMATTER_LIST` needs no change — it is keyed by class).
2. **D10 decision — flip the unknown-format fallback.** Today
   `app/Http/Controllers/Api/V1/ChatController.php::__invoke` catches
   `FormatterNotFoundException` and silently resolves the default formatter. With two
   formats shipped this becomes wrong (`/chat/opena` would answer in Open Responses).
   Change: fall back **only when `$format === null`**; a non-null unknown format returns
   an error via `formatError()` (400, code `unknown_format`, param `format`). Update
   `ChatEndpointTest::testItFallsBackToTheDefaultFormatterForUnknownFormats` accordingly
   and record the resolution in
   [`001-generic-llm-backend-implementation.md`](./001-generic-llm-backend-implementation.md) (§3 D10).
3. **Route** — none needed; `POST /api/hawki/v1/chat/{format?}` already dispatches
   `/chat/openai` to the new formatter.
4. **Tests** —
   - Unit: `tests/Unit/Services/Ai/Formatters/Implementations/OpenAiChatCompletions/…`
     (formatter round-trips + stream-context sequences, after the Rosetta tests above).
   - Feature: extend `tests/Feature/Api/Chat/ChatEndpointTest.php` (endpoint
     `self::ENDPOINT . '/openai'`) — the fake-agent harness
     (`tests/Feature/Api/Chat/ChatEndpointTestFixtures/FakeChatAgent.php`) is
     format-agnostic: feed vendor events, assert the Chat Completions chunk sequence
     (`no event: lines`, index-addressed tool deltas, `[DONE]`).
   - Style/stan: `bin/env php vendor/bin/php-cs-fixer fix <paths>` on the new files,
     `bin/env php vendor/bin/phpstan analyse`.
   - Live: repeat the two-turn client-tool loop of the earlier verification against
     `/api/hawki/v1/chat/openai` with `gpt-4.1-nano` (declared tool → `tool_calls`
     delta handoff → `tool` role continuation → final answer).

## 7. Definition of done

- `/chat` (default) behaves exactly as before (openResponses regressions: none).
- `/chat/openai` round-trips: request → IR → response; stream → full chunk grammar →
  `[DONE]`; client tool loop works across both turns in Chat Completions shape.
- Unknown formats 400; D10 updated in the implementation record.
- Rosetta-derived test sequences green; phpstan + style + existing suites green.
