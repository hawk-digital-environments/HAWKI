# Generic AI Proxy Backend — Implementation Record

> Status: **As built** — traceability companion to [`001-generic-llm-backend.md`](./001-generic-llm-backend.md)
> (the proposal). Covers the implemented state at commits `47644632` (Phase 1 backend),
> `8094cfe0` (frontend cutover), `aeed3561` (client tool handoff), and the follow-up
> deliveries listed in §0.
>
> Three documents together describe the system:
>
> | Document | Role |
> |---|---|
> | [`001-generic-llm-backend.md`](./001-generic-llm-backend.md) | The proposal — what was planned and why, including the phase plan (§11). |
> | [`POC-HUB-AND-SPOKE.md`](./POC-HUB-AND-SPOKE.md) | The as-built architecture — how the code is structured today and how to extend it. |
> | **This document** | The delta — where the implementation deviates from, adds to, or defers parts of the proposal, and what to build next. |

---

## 0. Current state (living section — update with every delivery)

Snapshot: **2026-09-20**, after N5 (round-trip corpus; D4 closed). Read this first;
§3/§5/§6 keep their original per-item detail.

### Shipped

| Capability | Where |
|---|---|
| Phase 1 chat proxy (`openResponses` format) + Svelte frontend cutover | `POST /api/hawki/v1/chat`, live-verified |
| Client-side tool execution (handoff loop, call-id conventions) | `ClientTool` + both formatters |
| **Spec-native citations in openResponses** — `response.output_text.annotation.added` + `url_citation` annotations on the message output_text part; `hawki:citation` removed (frontend consumes the native event) | `OpenResponsesStreamContext` / `OpenResponsesFormatter` |
| `openai` Chat Completions format (N1) — full parse/format, chunk grammar, tool calling incl. two-turn client-tool loop live-verified | `POST /api/hawki/v1/chat/openai` |
| Embeddings endpoint (N7, Phase 8) — `openai` format, usage from day one, live-verified | `POST /api/hawki/v1/embeddings` |
| Open Responses compliance suite (D5/N4) — 8/8 green live, skip-gated by `OPENRESPONSES_COMPLIANCE_TOKEN`, runs in CI | `tests/Feature/Api/Compliance/` + `.github/workflows/ai-compliance.yml` |
| Chat Completions live suite — chunk grammar, client-tool loop, params wiring, same gate + CI | `tests/Feature/Api/Chat/ChatCompletionsLiveTest.php` |
| Strict unknown-format errors (D10 closed): explicit `{format}` 400s on chat + embeddings | `UnknownFormatException` |
| Custom-events switch `AI_PROXY_EMIT_CUSTOM_EVENTS` (default true) — suppresses every non-standard `hawki:` emission in every format | `config/hawki.php` `aiProxy.emit_custom_events` |
| Spec-alignment bundle: refusal delta/done events + refusal content parts (openResponses); `tools[].strict`, `top_logprobs`, `allowed_tools` parked (openResponses parse); CC `reasoning_effort` / `reasoning:{effort,summary}` → `ReasoningConfig`; CC `store:true` → 400 | both formatters |
| **N6 reasoning replay** (D6 closed): reasoning item ids captured, merged into tool-call turns both wire layouts, replayable reasoning items returned non-streaming — live-verified with o4-mini | `ChatAgentFactory`, `StructuredChatAgent`, normalizer, both formatters |
| **N6 structured output** (D7 closed): `json_schema` via `StructuredChatAgent` (HasStructuredOutput, cross-driver), `json_object` via instruction suffix, `stream`+`json_schema` → 400, non-object roots → 400 — live-verified with gpt-4.1-nano | `StructuredChatAgent`, both formatters |
| **N5 round-trip corpus** (D4 closed): 63 fixture-driven cases — parse/response fidelity per formatter, emit-replay cycle, cross-format IR equivalence; caught and fixed two CC parse bugs (file-part filename nesting, phantom empty TextPart on null content) | `tests/Unit/Services/Ai/Formatters/Corpus/` |

### Gap analysis verdicts (researched against the published Open Responses OpenAPI +
LLM-Rosetta; full report in the session that produced it)

- **Reasoning stream mapping is Rosetta-parity**: IR reasoning deltas render as
  `response.reasoning_summary_text.*` (summary) events; the spec's raw
  `response.reasoning.delta/done` are unused by the reference converter too — no change.
- **Logprobs output is SDK-blocked**: request params parse/park, but `laravel/ai` has no
  logprobs surface on responses; revisit if the SDK grows one.
- **`AiResponse::$logprobs`, `ProviderPassthroughEvent`** (proposal §3.3/3.4) not built —
  no consumer; acceptable deviations, recorded here.

### `hawki` extension verdicts (what may eventually drop in favor of spec-native features)

| Extension | Verdict |
|---|---|
| `hawki:citation` | **Dropped** — native annotations shipped |
| `hawki.attachments` | Drop candidate at Phase 5: spec `input_file.file_id` can carry storage UUIDs natively (needs ownership-checked resolution in `ChatAgentFactory` + composer change) |
| `hawki.params` | Drop candidate at Phase 5: legacy composer overrides; standard sampling params cover the API surface |
| `hawki.broadcast` | Drop candidate at Phase 5: group-storage semantics belong behind `/ui-chat` |
| `hawki.tools` (transfer strings) | **Keep** — the spec tool union is function-only; no native way to request HAWKI hosted capabilities |
| `hawki:provider_tool_event` | **Keep** (env-switched) — approval/handoff UX has no spec equivalent |

### Known behavioral deviations (accepted)

- The `openai` streaming usage chunk is always emitted (the format-agnostic stream path
  cannot see per-request `stream_options`; matches the IR include-usage default).
- `n` and neighbours are parked in `providerExtensions` (single-choice by design).

### Recommended next steps (ordered)

1. **N2 — `/models/{format?}`** (trivial, pure transform).
2. **N3 — `legacy` NDJSON formatter + private/ai-req route forwarding** (Phase 3 remainder;
   budget Phase-2 design time for route forwarding).
3. **N9 — Phase 5 orchestration**: `/ui-chat` + `StreamController` refactor (closes
   D1/D11/D13) — unlocks dropping `hawki.params`/`broadcast`/`attachments`.
4. **N8 — `anthropicMessages` formatter** after the formatter conventions have hardened.
5. Phases 9/10 (images/audio) whenever prioritized; the embeddings domain is the template.

---

## 1. Verdict in one paragraph

Phase 1 (§11) is implemented and live-verified against a real provider: IR hub, formatter
layer, `openResponses` reference formatter, typed agent factory chain, `ChatService`,
`POST /api/hawki/v1/chat/{format?}`, and event-driven usage recording. The frontend uses
the new endpoint exclusively. The implementation follows the proposal's architecture
faithfully at the layer level, deviates in a number of contained details (documented in
§3), and adds three capabilities the proposal does not cover (§4) — most notably the
client-side execution model for tool calls. Phases 2–10 are untouched (§5); §6 orders the
recommended next steps, including the lowest-effort ports from the LLM-Rosetta reference
codebase.

---

## 2. Phase-1 scorecard

The proposal's Phase-1 bullet list (§11), item by item:

| Proposal item | Status | Notes |
|---|---|---|
| IR type set (§3.3–3.6) | ✅ done | All part/message/config/tool/passthrough/stream-event types as readonly value objects under `app/Services/Ai/Chat/Values/`. |
| `FormatterInterface` + `FormatterRegistry` | ✅ done | Deviations D2/D3 (§3 below) are mechanical, not architectural. |
| `OpenResponsesFormatter` + `OpenResponsesStreamContext` | ✅ done | Spec-complete `ResponseResource`, full SSE catalog, `[DONE]` terminator, `hawki:` extension events, stateless 4xx rejections. |
| `AiStreamNormalizer` with `CitationUrlCleaner` | ✅ done | Including the proposal's non-streaming citation-cleaning fix (§6.3): both paths clean now. |
| `ChatAgentFactoryInterface` + `ChatAgentRegistry` | ✅ done | Typed `createAgent(AiRequest)`, topological ordering with `before:`/`after:`. |
| `ChatAgentFactory` (replaces legacy factory) | ✅ done | Coexists with `ChatAgentFromLegacyRequestFactory` per the phase plan (removal is Phase 5). |
| `ChatController` + `POST /api/hawki/v1/chat/{format?}` | ✅ done | Route, middleware stack, and thin controller exactly as §6.1–6.2. |
| Round-trip tests for the formatter | 🟡 partial | Structural parse/format and stream-event tests exist; the full wire→IR→wire structural-equality corpus (§9.1) does not (D4). |
| Open Responses compliance tests | ✅ done | Ported to PHPUnit as `tests/Feature/Api/Compliance/OpenResponsesComplianceTest.php` — all 8 stateless-compatible tests green against the live provider, skip-gated by `OPENRESPONSES_COMPLIANCE_TOKEN` (D5, closed). |
| **Deliverable**: full tool calling and reasoning output | ✅ | Output side complete (tool calls, reasoning deltas stream through); reasoning input round-trip closed with N6/D6 (live-verified with o4-mini). |

---

## 3. Deviations from the proposal

Each entry states what the proposal says, what the code does, why, and how the gap
closes eventually. None of these change the layer boundaries — they are contained
decisions inside them.

### D1 — `AiService::getAgent()` is not deprecated yet

- **Proposal (§2.3):** Phase 1 deprecates `AiService::getAgent()`/`tryToGetAgent()` and
  makes them delegate to `ChatService` internally.
- **Built:** `AiService` is untouched. The legacy chain (`AgentRegistry` →
  `ChatAgentFromLegacyRequestFactory`) and the new chain (`ChatAgentRegistry` →
  `ChatAgentFactory`) coexist, exactly as the proposal's own Phase 3–5 coexistence
  requires.
- **Why:** `StreamController` was to remain byte-identical in this iteration; touching
  `AiService` would have pulled every internal caller (title/summary/prompt generation)
  into the change for no functional gain.
- **Reconciliation:** Phase 5 — deprecate, migrate internal callers, remove.

### D2 — `AbstractChatAgentFactory` duplicates instead of extends

- **Proposal (§5.2):** the helper is "reused" from the existing `AbstractAgentFactory`.
- **Built:** a standalone `AbstractChatAgentFactory` in
  `app/Services/Ai/Chat/Factories/` that re-implements the lazy-service injection pattern
  and `createRequestContext()`.
- **Why:** PHP forbids the sharing base — the legacy interface declares
  `createAgent(mixed $request)`, the typed one `createAgent(AiRequest $request)`, and a
  class cannot satisfy both signatures.
- **Reconciliation:** the duplicate dies with `AbstractAgentFactory` in Phase 5.

### D3 — default format key is a constant, not a method

- **Proposal (§4.2):** `FormatterRegistry::getDefaultKey(): string`.
- **Built:** `FormatterRegistry::DEFAULT_KEY` class constant (`'openResponses'`).
- **Why:** less API surface, same behaviour. Cosmetic; can be aligned whenever the
  registry grows format metadata.

### D4 — ~~the round-trip corpus is not a wire→IR→wire equality suite yet~~ (CLOSED)

- **Built (N5):** the four-track corpus under
  `tests/Unit/Services/Ai/Formatters/Corpus/` (63 cases, JSON fixtures in
  `CorpusFixtures/`): parse-fidelity and response-fidelity snapshots per formatter,
  the emit-replay cycle (openResponses formatted output re-parsed as input — the
  client-tool-loop guarantee), and cross-format IR equivalence (same logical
  conversation in both dialects → equal IR, modulo documented dialect differences).
  Proposal §9.1 as written was unimplementable (it assumes a `formatRequest` that
  deliberately does not exist) — the reinterpretation is specified in
  [`N5-Handoff.md`](./N5-Handoff.md) §2.
- **Findings (bugs the corpus caught immediately):** CC `file` content parts read the
  filename from the wrong nesting level (`part.filename` instead of
  `part.file.filename`); CC assistant messages with `content: null` produced a
  phantom empty `TextPart` (`?? ''` fallback), diverging the IR between dialects.
  Both fixed.
- **Documented dialect modulos (cross-format comparator):** `formatKey`,
  `providerExtensions`, `stream.includeUsage`, message partitioning (openResponses
  separates a reasoning item from its function_call; CC merges them into one message —
  the comparator coalesces, mirroring the factory), and reasoning replay state
  (encryptedContent/item ids are expressible only in openResponses).

### D5 — ~~the official compliance suite is not wired in~~ (CLOSED)

- **Proposal (§9.4):** implement the stateless-compatible subset of the Open Responses
  acceptance tests.
- **Built (now):** the suite is ported to PHPUnit as
  `tests/Feature/Api/Compliance/OpenResponsesComplianceTest.php` instead of driving the
  TypeScript runner — in-process HTTP against the real client-facing route, spec-schema
  validation against `research/openresponses/public/openapi/openapi.json` via
  `opis/json-schema` (the PHP counterpart of the runner's Zod schemas), plus the
  streaming ordering rules. Skip-gated by `OPENRESPONSES_COMPLIANCE_TOKEN` (empty ⇒
  skipped ⇒ green); wired into CI via `.github/workflows/ai-compliance.yml`.
- **Findings the suite produced:** `text.verbosity` and the sampling fields
  (`temperature`, `top_p`, `presence_penalty`, `frequency_penalty`, `top_logprobs`) and
  `service_tier` are *not nullable* in the published spec (the formatter now emits
  defaults); `reasoning_summary_part.added/done` require a `part` field (added); the
  official image fixture (32×32 PNG) is rejected by OpenAI's current image validation
  for every model, so the port sends a 128×128 PNG with identical intent.
- **Verified along the way:** the official `multi-turn` test sends full history with no
  `previous_response_id` — the proposal §9.4 open question about test #8 is resolved:
  it is in scope and passes.

### D6 — ~~reasoning items do not round-trip into the provider request~~ (CLOSED)

- **Built (now):** reasoning replay works end-to-end. Parse captures the reasoning
  item `id` (into `ReasoningPart->providerMetadata['item_id']`); `ChatAgentFactory`
  merges reasoning state into the tool-call turn (both wire layouts: a preceding
  reasoning-only message in openResponses, or `reasoning_content` on the same
  message in Chat Completions) and attaches it to the vendor `ToolCall`
  (`reasoningId`/`reasoningSummary`/`reasoningEncryptedContent`), which is exactly
  the replay state the SDK's OpenAI and Anthropic drivers forward. Non-streaming
  responses surface replayable reasoning items ahead of their function calls
  (normalizer copies the SDK-parsed state into `ToolCallPart->providerMetadata`,
  the openResponses formatter emits the reasoning item, CC derives
  `message.reasoning_content`).
- **Verified live (o4-mini):** tool-call turn returns a reasoning item with
  `encrypted_content`; resending the exact items (reasoning + function_call +
  output) completes the loop and the final answer reflects the tool result.
- **Findings:** the SDK adds `include: ["reasoning.encrypted_content"]` itself for
  stateless reasoning models (no proxy action needed); reasoning-only turns without
  a following tool call are **not replayed** — the SDK has no channel for them and
  OpenAI only requires replay around tool calls (previously they degraded to an
  `&nbsp;` placeholder message; now they are dropped); streaming responses carry
  reasoning as summary events only (the spec has no encrypted field there), so
  streaming clients obtain replay state only when a tool call follows.

### D7 — ~~structured output (`text.format`) is parsed but not wired~~ (CLOSED)

- **Built (now):** `json_schema` requests instantiate `StructuredChatAgent`
  (`implements HasStructuredOutput`), forwarding each root property through the SDK's
  raw-schema pipeline (`JsonSchema::fromArray(SchemaNormalizer::normalize(...))` — the
  same one MCP tool inputs use). Every driver family maps it natively: OpenAI Responses
  `text.format`, OpenAI-compatible `response_format`, Anthropic `output_config`.
  `json_object` (Chat Completions dialect only — the Open Responses wire format has no
  such type) is emulated with an instruction suffix, because the SDK channel always
  wraps an object schema and an empty one would constrain the output to a literal
  empty object. Verified live against gpt-4.1-nano (schema-constrained JSON,
  non-streaming and per-dialect).
- **Findings / accepted transformations:** the SDK **cannot stream structured output**
  (`StreamsText` throws) and rejects streaming for *any* `HasStructuredOutput` agent —
  hence the dedicated agent subclass and a parse-time 400 (`unsupported_response_format`)
  for `stream: true` + `json_schema` in both formatters. Top-level `required` is not
  forwarded (the contract passes a property map), `additionalProperties: false` is
  forced recursively by `ObjectSchema`, and `strict` stays false (the SDK reads it from
  a compile-time attribute). Non-object-rooted schemas are rejected with 400 at parse
  time. If raw-schema fidelity matters later, the per-request providerOptions channel
  is the escape hatch.

### D8 — ~~`n` and neighbours are not "accepted for format alignment"~~ (CLOSED)

- **Closed:** both formatters now parse or park every standard parameter — Chat
  Completions parks `n`/`logit_bias`/`user` in `providerExtensions` and parses
  `seed`/`logprobs`/`top_logprobs` into `GenerationConfig`; openResponses parses
  `top_logprobs` and parks `include`/`metadata`/`service_tier`/`background`/
  `stream_options`/`prompt_cache_key`/`safety_identifier` and structured
  non-function `tool_choice` forms. `n` stays single-choice by design (the SDK is
  single-response); executing it would require an upstream passthrough that does not
  exist.

### D9 — `MessageMetadata` is never populated

- **Built:** the IR carries `MessageMetadata` (message id, timestamp, custom) but no
  formatter fills it; item ids from client input are dropped.
- **Why:** no consumer. The custom slot becomes interesting with the round-trip corpus
  (D4), which needs ids to survive parse→format cycles.
- **Post-N5 note:** the corpus (D4, closed) shows loop-relevant ids already survive
  where they matter — reasoning item ids via `providerMetadata` (N6), tool call ids
  natively — so `MessageMetadata` remains without a consumer; stays open on merit,
  not necessity.

### D10 — ~~unknown `{format}` segments fall back to the default formatter~~ (CLOSED)

- **Proposal:** unspecified — §6.1 only defines the omitted-segment default.
- **Built (was):** `POST /chat/garbage` resolved the default formatter instead of
  erroring (`ChatController` caught `FormatterNotFoundException` and retried with
  `null`).
- **Closed with the second formatter:** an explicit-but-unknown `{format}` now returns
  400 `unknown_format` (param `format`) via `UnknownFormatException`; only an omitted
  segment falls back to the default — the same rule the embeddings endpoint applied
  from day one.

### D11 — the usage-record DB rework is deferred

- **Proposal (§12.4):** rework `usage_records` with channel, user agent, room, assistant
  handle, format key; `UsageRecordedEvent` carries all of it.
- **Built:** the event carries `usageType`, `channel`, `modelId`, `formatKey`,
  `userAgent` (no `roomId`/`assistantHandle` — those originate from `hawkiExtensions`
  that do not exist yet). The database record is unchanged (user, room, tokens, model,
  legacy type).
- **Reconciliation:** N10 in §6 — do the rework together with `/ui-chat` (Phase 5),
  which is the first producer of room-scoped usage through the new path.

### D12 — citations fire no domain event

- **Proposal (§12.2):** a Laravel-dispatched `HawkiCitationEvent` domain event.
- **Built:** citations exist only as the IR stream event (`hawki:citation`) after URL
  cleaning. No Laravel event is dispatched.
- **Why:** no listener would exist today; the IR event already reaches every client.
- **Reconciliation:** add the dispatch inside `AiStreamNormalizer` when a consumer
  (source tracking, analytics) appears.

### D13 — usage listeners live one domain above the proposal's tree

- **Proposal (§10):** the file tree implies chat-domain listeners.
- **Built:** `RecordUsageForAgentResponse` / `RecordUsageForAgentStreamCompleted` live in
  `App\Services\Ai\Listeners\`, not `App\Services\Ai\Chat\Listeners\`.
- **Why:** Laravel event auto-discovery is configured for `app/Services/*/Listeners`
  (`bootstrap/app.php`) — a nested path would need manual registration.

---

## 4. Additions beyond the proposal

Three capabilities the proposal does not specify, added during implementation. Details
live in [`POC-HUB-AND-SPOKE.md`](./POC-HUB-AND-SPOKE.md); this section records them as
proposal deltas.

### A1 — the `hawki` request object

The proposal defines `hawkiExtensions` on the IR (§3.3.4) but never specifies how
HAWKI-specific metadata travels on the wire. Implementation convention: a top-level
`hawki` object on the request (`tools` transfer strings, `attachments` UUIDs, `params`,
`broadcast`), spec-legal as an Open Responses superset, parsed into
`AiRequest::$hawkiExtensions`. This convention should be written back into the proposal
when it is next revised.

### A2 — client-side tool execution (the handoff)

The proposal's tool model (§8.1) is server-execution only: IR tool definitions map to
HAWKI-resolvable tools. The implementation adds the second execution model required by
agent harnesses: tools the server cannot map are wrapped in `ClientTool`, which uses the
SDK's approval-pause so the model's `function_call` is streamed to the client untouched;
the client executes locally and sends `function_call_output` back as a continuation turn.
This brought with it the call-id conventions (`resultId` = wire `call_id`, `fc_`-prefixed
replayed item ids), the trailing-`function_call_output` input rule, the synthetic ack
turn, and the hallucinated-tool error mapping — none of which the proposal covers.
**Proposal action:** §8 should be extended with the two-model routing rule.

### A3 — the `MAX_TOOL_CALLING_STEPS` seam

Not in the proposal. `AbstractTextGeneratingAgent` exposes the SDK's tool-loop step
budget as a named, non-configurable constant, read by the SDK through the agent's
`maxSteps()` method. The `@todo` on the constant marks the planned connection to the
per-model `WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS`. The proposal's model-setting
registry (§2.3) is the natural home once per-model configuration is wanted.

### A4 — the frontend cutover happened in Phase 1, not Phase 7

The proposal gates frontend migration on Phase 7. The Svelte frontend (private chat,
title generation, prompt improvement) was moved to the new endpoint immediately, since
none of the deferred phases are prerequisites for stateless private chat. Group chat
remains on `StreamController` exactly as the proposal requires (it needs the Phase 5
`/ui-chat` orchestration). Consequence: the Phase-7 gate is already half-met.

### A6 — the custom-events switch (`AI_PROXY_EMIT_CUSTOM_EVENTS`)

Not in the proposal. HAWKI augments the standard wire formats with non-standard
`hawki:` frames wherever the format has no native slot. `config('hawki.aiProxy.emit_custom_events')`
(env `AI_PROXY_EMIT_CUSTOM_EVENTS`, default **true**) turns this emission off globally
for **all** formats so strict-spec clients get spec-shaped output only. The switch acts
at the formatter boundary; the IR keeps the data either way. Added while shipping the
Chat Completions formatter; to be re-evaluated after real-client testing.

Superseded in part for citations: openResponses now carries them **spec-natively**
(`response.output_text.annotation.added` + `url_citation` annotations — see §0), so the
switch governs only the remaining custom emissions (the `openai` citation frame and
`hawki:provider_tool_event`).

### A5 — the controller maps infrastructure exceptions

The proposal's controller (§6.2) lets exceptions bubble to the global JSON:API renderer.
The implementation catches formatter-level errors (→ wire-format error bodies), unknown
models (→ 404 `model_not_found`), and hallucinated tool calls (→ 400 `unknown_tool`) so
wire-format clients receive spec-shaped errors. Streaming failures degrade to SSE
`error` + `response.failed` inside the formatter.

---

## 5. Deferred phases — status

| Phase (proposal §11) | Status |
|---|---|
| Phase 2 — legacy compatibility *design* (route forwarding, `usageType` propagation) | Not started |
| Phase 3 — `openai` + `legacy` formatters, private/ai-req route forwarding | 🟡 `openai` done (N1: formatter + stream context + live suite, D10 closed); `legacy` NDJSON formatter and route forwarding remain (N3) |
| Phase 4 — `AssistantAgentFactory` (assistant routing) | Not started; gated on the Assistants feature branch; the `hawkiExtensions` seam is ready for it |
| Phase 5 — `StreamController` refactor + `/ui-chat` endpoint | Not started; D1 and D11 resolve here |
| Phase 6 — `/models/{format?}` endpoint | Not started; smallest of all endpoints (no agent, pure transform) |
| Phase 7 — remove `StreamController` | Frontend private-chat half of the gate already met (A4); group chat still requires Phase 5 |
| Phase 8 — `/embeddings/{format?}` | ✅ done — [`EMBEDDING-API-IMPLEMENTATION-HANDOFF.md`](./EMBEDDING-API-IMPLEMENTATION-HANDOFF.md) executed: `EmbeddingService` + vectorizer registry, `openai` formatter, `POST /api/hawki/v1/embeddings/{format?}`; usage recorded from day one (`UsageRecordedEvent` reused with `channel: 'embeddings'`, not relocated); unknown explicit `{format}` → 400 from the start (D10 lesson); live-verified against OpenAI (`text-embedding-3-small`, input-ordered vectors, dimensions passthrough, usage row) |
| Phase 9 / 10 — images, audio, transcription | Not started |

---

## 6. Recommended next steps

Ordered by value-to-effort. "Rosetta port" items map the reference implementation
(`research/llm-rosetta/src/llm_rosetta/`) onto the HAWKI spokes — the IR was derived
from Rosetta's, so porting is mostly translation-table work.

### N1 — ~~`openaiChatCompletions` formatter~~ *(DONE — shipped as format key `openai`)*

Delivered together with the D10 flip and a live verification suite
(`tests/Feature/Api/Chat/ChatCompletionsLiveTest.php`, gated on
`OPENRESPONSES_COMPLIANCE_TOKEN`, wired into `.github/workflows/ai-compliance.yml`):
basic response, streaming chunk grammar with usage, the two-turn client-tool loop, and
generation-param wiring, all green against live gpt-4.1-nano. Known deviations: the
streaming usage chunk is always emitted (the format-agnostic stream interface cannot
see per-request `stream_options`; matches the IR's include-usage default), and `n`
remains parked in `providerExtensions` (single-choice by design).

### N1 (original) — `openaiChatCompletions` formatter *(proposal Phase 3; best first spoke)*

The most-requested external format (everything LiteLLM-compatible speaks it). Rosetta's
`converters/openai_chat/` is the blueprint:

| Rosetta piece | HAWKI counterpart |
|---|---|
| `converter.py` request/response ops | `OpenAiChatCompletionsFormatter::parseRequest/formatResponse` |
| `content_ops.py`, `message_ops.py`, `tool_ops.py`, `config_ops.py` | private mapper methods (roles ↔ IR messages, `tool_calls[]` ↔ `ToolCallPart`, `choices[0].finish_reason` ↔ `FinishReasonType`) |
| stream handling in `converter.py` + `StreamContext` | `OpenAiChatCompletionsStreamContext`: `choices[].delta` chunks, implicit block boundaries (block opens on first delta of a kind), tool-call accumulation by index, `data: [DONE]` |
| `tests/converters/openai_chat/` | fixtures for the round-trip corpus (also closes part of D4) |

Effort: days, not weeks. The IR needs no changes; Rosetta's `openai_chat` tests can be
ported nearly one-to-one. Ship together with the D10 decision (unknown format should
start erroring once a second format exists).

### N2 — `/models/{format?}` endpoint *(proposal Phase 6; trivial)*

`GET` route + a `ModelsController` that reads `AiModelRepository` and formats an OpenAI
`/v1/models` list. No agent, no factory, no stream context — the smallest possible proof
that the formatter registry generalizes beyond chat.

### N3 — `legacy` NDJSON formatter *(proposal Phase 3)*

The wire format already exists in `StreamController::handleStreamingRequest` — extract
the frame shapes (`header`/`message`/`status`/`completion`/`citation`) into a formatter.
Straightforward, but carries the proposal's Phase-2 design questions (route forwarding,
`usageType` derivation), so budget design time alongside the code.

### N4 — ~~compliance runner integration~~ *(DONE — see D5)*

Delivered as the PHPUnit port described in D5 rather than the TypeScript runner: same
test definitions, same published schema, no served app or bun dependency. The original
runner (`research/openresponses/bin/compliance-test.ts`) remains usable against a
manually served instance.

### N5 — ~~round-trip corpus~~ *(DONE — closes D4)*

A reflective harness: fixture → `parseRequest` → assert IR → `formatResponse` →
`parseRequest` again → structural equality. Start from the payloads already inline in
the formatter tests; grow per formatter. `MessageMetadata` (D9) becomes worth populating
once equality checks need stable ids.

**DONE** — executed per [`N5-Handoff.md`](./N5-Handoff.md) (status there: IMPLEMENTED):
four tracks, 63 cases, two formatter bugs caught and fixed on first contact
(see D4 above).

### N6 — ~~reasoning replay + structured output~~ *(DONE — D6/D7/D8 closed)*

Delivered with live verification
(`tests/Feature/Api/Chat/StructuredOutputAndReasoningLiveTest.php`, gated on
`OPENRESPONSES_COMPLIANCE_TOKEN`, wired into the CI workflow): json_schema responses
(gpt-4.1-nano, both dialects + streaming-rejection), json_object emulation, and the
o4-mini reasoning-replay loop. Findings recorded under D6/D7 above; the notable
SDK constraints surfaced live: no streaming for structured output, auto-`include`
of encrypted reasoning for reasoning models, replay state rides on tool calls only.

### N7 — embeddings *(proposal Phase 8; second domain)*

Follows proposal §6.4 exactly: `EmbeddingRequest`/`EmbeddingResponse` values,
`VectorizerFactoryInterface` + `VectorizerRegistry`, `DefaultVectorizerFactory`,
`OpenAiEmbeddingsFormatter`, `EmbeddingsController`, route. The upstream spoke already
exists — `laravel/ai` ships `Embeddings::for()` with drivers for OpenAI, Voyage, Jina,
and Cohere, and provider adapters extend naturally. Rosetta's `converters/embedding/`
covers the wire shapes (OpenAI/Voyage/Jina/Cohere input conventions). This is the
template for every further domain (images, audio): one IR pair, one registry, one
formatter, one controller.

### N8 — `anthropicMessages` formatter *(extensibility proof)*

The proposal's extensibility claim ("future integration of e.g. Anthropic should be
easy") is really about *serving* an Anthropic-Messages-shaped endpoint. The IR already
models everything needed (top-level system param, content blocks, tool_use/tool_result,
`ReasoningPart.signature`). Port from Rosetta's `converters/anthropic/`. Hardest of the
formatters listed here — do it after N1 has hardened the formatter conventions.

### N9 — Phase 2 design + Phase 5 refactor + `/ui-chat`

The orchestration track: design the `StreamController` refactor, introduce `/ui-chat`
with room-scoped usage (resolves D1, D11, D13), then remove the legacy chain. Sequence
and constraints are fully specified in proposal §11 and §4.5.

### N10 — `AssistantAgentFactory` *(proposal Phase 4, gated)*

Claims requests via `hawkiExtensions.assistant_handle`; everything else about the seam
is already in place (`ChatAgentRegistry::declare(before: …)`, `AssistantPromptComposer`
on the assistants branch). Blocked on the Assistants feature merge, not on architecture.

---

## 7. Decisions to revisit

| Decision | Context | Trigger to revisit |
|---|---|---|
| ~~Unknown `{format}` falls back to default (D10)~~ | Resolved with N1: explicit unknown formats 400 | — |
| Synthetic ack turn for continuations (A2) | One small extra user turn per tool-loop iteration | If a provider driver gains native no-prompt continuation, or fidelity complaints from harness clients |
| `MAX_TOOL_CALLING_STEPS` non-configurable (A3) | SDK dynamic default (1.5 × tools) behind a visible constant | Long mixed tool loops hit the budget; then wire `MAX_TOOL_CALLING_ROUNDS` per model |
| Always emit the streaming usage chunk in `openai` | Per-request `stream_options` is invisible to the format-agnostic stream path | Formatter interface gains request context (e.g. with N6) |
| `AI_PROXY_EMIT_CUSTOM_EVENTS` default true (A6) | HAWKI feature parity vs strict-spec output | After real-client testing of custom frames |
| Client tool schema degradation to string params (A2) | Properties outside the `illuminate/json-schema` subset lose precision | Upstream raw-schema support, or real-world reports of degraded coding-agent tools |

---

## 8. Reference index

- Proposal: [`001-generic-llm-backend.md`](./001-generic-llm-backend.md)
- As-built architecture: [`POC-HUB-AND-SPOKE.md`](./POC-HUB-AND-SPOKE.md)
- Rosetta reference code: `research/llm-rosetta/src/llm_rosetta/` (converters under
  `converters/<format>/`, stream contexts under `converters/base/context.py`, tests
  under `tests/converters/`)
- Open Responses spec + compliance runner: `research/openresponses/` (spec
  `src/specifications/2026-04-24.mdx`, runner `bin/compliance-test.ts`)
- Tests as usage examples: `tests/Feature/Api/Chat/ChatEndpointTest.php`,
  `tests/Unit/Services/Ai/Chat/`, `tests/Unit/Services/Ai/Formatters/`
