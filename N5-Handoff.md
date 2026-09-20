# N5 Round-Trip Corpus — Implementation Handoff

> **Status: OPEN** — pick-up-and-go specification for next step **N5** in
> [`001-generic-llm-backend-implementation.md` §0/§6](./001-generic-llm-backend-implementation.md#0-current-state-living-section--update-with-every-delivery).
> Executing this closes deviation **D4** and unblocks (not closes) **D9**.
>
> Reading order for context:
>
> 1. [`001-generic-llm-backend-implementation.md`](./001-generic-llm-backend-implementation.md) — §0 current state, §3 D4 (the corpus gap) and D9 (MessageMetadata), §6 N5.
> 2. [`001-generic-llm-backend.md` §9.1](./001-generic-llm-backend.md) — the proposal's corpus spec (and why it needs reinterpretation, §2 below).
> 3. [`POC-HUB-AND-SPOKE.md`](./POC-HUB-AND-SPOKE.md) — §4 the formatter layer as built.
>
> Reference implementation: `research/llm-rosetta/tests/converters/` (esp.
> `openai_responses/test_converter.py` `test_request_round_trip` /
> `test_response_round_trip` and `test_cross_provider_metadata_roundtrip.py`).

---

## 1. Scope

| In scope | Out of scope |
|---|---|
| Both chat formatters: `openResponses` + `openai` (Chat Completions) | A `formatRequest` method (does not exist, has no product value — see §2) |
| Four corpus tracks (§3) driven by JSON wire fixtures | Stream/SSE sequence tests (proposal §9.2 — already covered by `OpenResponsesStreamContextTest`, `OpenAiChatCompletionsStreamContextTest`, and the endpoint feature tests) |
| A reflective harness (fixture loader + IR normalizer + data providers) | The embeddings formatter (its parse is trivially covered; a corpus there is an optional 30-minute add-on if desired) |
| Record updates: D4 closed, N5 done, §0/roadmap refreshed | Populating `MessageMetadata` (D9) — report what the corpus surfaces, do not build it speculatively |
| php-cs-fixer (targeted paths) + phpstan + suites green | New app code — this is test-layer work; if a corpus case exposes a real formatter bug, fix the bug minimally and record it as a finding |

---

## 2. The §9.1 interpretation decision (pre-made — do not re-litigate)

Proposal §9.1 specifies `fixture → parseRequest → assert → formatResponse → parseRequest →
assert equality`. Step 4 is **impossible as written**: `formatResponse` produces a
*response*, `parseRequest` consumes a *request*, and the formatter layer has no
`formatRequest` (the proxy never re-renders requests; Rosetta's converters can round-trip
because they are bidirectional, ours deliberately are not).

The implementable replacement — four tracks that together prove everything §9.1 wanted:

1. **Parse fidelity** — wire fixture → `parseRequest` → the IR matches an expected snapshot.
2. **Response fidelity** — IR fixture → `formatResponse` → the wire response matches an expected snapshot.
3. **Emit-replay cycle** (the real loop guarantee) — openResponses `formatResponse` output
   items are valid `input` items: format, append a trailing user turn, re-parse, assert the
   loop-relevant state survived. This is the exact bug class N6 fixed (reasoning item ids
   were dropped at parse and replay silently degraded).
4. **Cross-format IR equivalence** (the hub-and-spoke promise) — the same logical
   conversation expressed in both wire dialects parses to structurally equal `AiRequest`s.

---

## 3. Track specifications

### 3.1 Track 1 — parse fidelity (per formatter)

Fixture shape: `{ "wireRequest": {…}, "expectedIr": {…} }`.

`expectedIr` is not the typed object — it is the **normalized array snapshot** the harness
produces (§4). Coverage checklist (from §9.1 plus everything the formatters actually
support today; each case one fixture file):

- [ ] plain string input / user message
- [ ] multimodal user content: text + image_url (with detail) + file + input_audio (CC only)
- [ ] system/developer messages (openResponses items *and* CC `messages[]` role)
- [ ] tool definitions incl. `strict` (both dialects' nesting)
- [ ] `tool_choice` vocabulary: none/auto/required + named function (+ openResponses `allowed_tools` parking)
- [ ] tool-loop history: assistant tool_calls (JSON-string args) + tool results, trailing-continuable
- [ ] reasoning input: openResponses reasoning item (id + encrypted_content + summary); CC `reasoning_content`
- [ ] generation params: temperature/top_p/max_tokens(+`max_completion_tokens`)/penalties/stop(string+list)/seed/logprobs/top_logprobs
- [ ] `parallel_tool_calls`, `max_tool_calls` (openResponses)
- [ ] reasoning params: openResponses `reasoning:{effort,summary}`; CC `reasoning_effort` + `reasoning` object
- [ ] `response_format`/`text.format` incl. the 400-rejection cases as explicit fixtures with `expectedError`
- [ ] stateful rejections: `store:true`, `previous_response_id` (openResponses) — expectedError fixtures
- [ ] `hawki` extension object (tools/attachments/params/broadcast)
- [ ] providerExtensions parking (CC `n`/`logit_bias`/`user`; openResponses `include`/`metadata`/`service_tier`/…)
- [ ] empty content / whitespace edge cases
- [ ] trailing-item rule violations — expectedError fixtures (`missing_user_turn`)

### 3.2 Track 2 — response fidelity (per formatter)

Fixture shape: `{ "irResponse": {…}, "expectedWireResponse": {…} }` where `irResponse`
is a harness-hydratable description of the `AiResponse` IR (parts list, finish reason,
usage, id/model/created fixed values — no generated ids; the harness allows per-field
"normalized" markers for uuids if a case needs them).

Coverage must include the N6 output surface:

- [ ] text-only response (full resource skeleton assertions)
- [ ] tool calls (args JSON-stringified, `chatcmpl-`/`resp_` ids, `finish_reason: tool_calls`)
- [ ] tool call + replayable reasoning state → reasoning item ahead of the function_call (openResponses), `reasoning_content` (CC)
- [ ] reasoning-only parts
- [ ] citations → native `url_citation` annotations on the message output_text part (openResponses)
- [ ] refusal parts → refusal content part (openResponses) + `message.refusal` (CC)
- [ ] finish-reason matrix: stop/length/tool_calls/refusal→content_filter/error→stop
- [ ] usage mapping incl. totals; LENGTH → `incomplete` + `incomplete_details` (openResponses)

### 3.3 Track 3 — emit-replay cycle (openResponses)

For each Track-2 fixture that produces loop-relevant output items (text message,
function_call, reasoning), the harness:

1. takes the formatted response's `output` items,
2. appends a trailing user turn (`{"type":"message","role":"user","content":"continue"}`),
3. runs `parseRequest` on `{ "model": …, "input": […output items, user turn] }`,
4. asserts survival: assistant text, tool-call id/name/arguments, reasoning item id +
   `encryptedContent` (+ providerMetadata item_id), and that the trailing rule accepts it.

Cases: text+reasoning, function_call+reasoning (the client-tool loop), text-only,
citations-present (annotations must not break re-parse).

### 3.4 Track 4 — cross-format IR equivalence

Paired fixtures: `CorpusFixtures/cross_format/<case>.json` =
`{ "openResponses": {wire…}, "openai": {wire…} }` describing the same logical
conversation (same model, same history, same params where both dialects express them).
Harness parses both and asserts the normalized IR snapshots are equal **modulo**:

- `formatKey`
- `providerExtensions` (per-dialect parking by design)
- `stream.includeUsage` (different dialect defaults)
- generation fields one dialect cannot express (fixture pairs should avoid those)

Cases: plain conversation, tool-loop history (turn-2 shape), reasoning replay input,
generation params, tool definitions + choice.

---

## 4. Harness mechanics

- **Location**: `tests/Unit/Services/Ai/Formatters/Corpus/` for test classes +
  `tests/Unit/Services/Ai/Formatters/CorpusFixtures/` for JSON.
- **Loader**: a shared trait/harness class scanning the fixture directory per test class
  (`dataProvider` returning `[name, fixturePath]`), failing with the fixture name in the
  assertion messages so failures point at the file.
- **IR normalizer** (`AiRequest`/`AiResponse` → comparable array): one method per object
  family in the harness; rules —
  - omit `null` fields (snapshot stays minimal);
  - keep `formatKey` but the equivalence track strips it (plus the §3.4 modulo list);
  - `providerMetadata`/`hawkiExtensions` compared as-is;
  - any field the formatters generate with `Str::uuid()` must either be excluded from
    snapshots or normalized to `'<uuid>'` — pick exclusion unless a case asserts the
    prefix (Track 2 asserts id *prefixes*, not values).
- **Hydration**: small builders in the harness (`irResponse: [...]` fixture → typed
  `AiResponse`); request side needs no hydration (parse produces IR directly).
- **Error fixtures**: `expectedError: { code, param?, httpStatus? }` — the harness
  catches `FormatterRequestException` and compares instead of asserting IR.
- **File layout**:

```
tests/Unit/Services/Ai/Formatters/
├── Corpus/
│   ├── CorpusFixtures/openai_responses/{01-plain.json, 02-multimodal.json, …}
│   ├── CorpusFixtures/openai_chat_completions/{…}
│   ├── CorpusFixtures/cross_format/{01-conversation.json, …}
│   ├── OpenResponsesCorpusTest.php      (T1+T2+T3 for openResponses)
│   ├── OpenAiChatCompletionsCorpusTest.php (T1+T2 for openai)
│   ├── CrossFormatCorpusTest.php        (T4)
│   └── Concerns/ (harness: loader, normalizer, hydration, error expectations)
```

- PHPUnit: files under `tests/Unit` are auto-discovered; keep `#[CoversClass]` on the
  formatter under test (phpunit.xml has `requireCoverageMetadata="true"` +
  `failOnRisky="true"`).
- Style: `bin/env php vendor/bin/php-cs-fixer fix <new paths>` (never the bare `style`
  command — it reformats the whole repo). Static analysis: `bin/env php vendor/bin/phpstan analyse`.
- All commands run inside Docker via `bin/env …` (see CLAUDE.md); no live token needed —
  this corpus is pure unit-level work.

---

## 5. Seed material

The payloads already exist inline and should be lifted into fixtures (deduplicated,
sharpened) rather than rewritten from scratch:

- `tests/Unit/Services/Ai/Formatters/Implementations/OpenResponses/OpenResponsesFormatterTest.php` — parse cases, rejections, response formatting incl. reasoning/annotations
- `tests/Unit/Services/Ai/Formatters/Implementations/OpenAiChatCompletions/OpenAiChatCompletionsFormatterTest.php` — the CC equivalents
- `tests/Unit/Services/Ai/Chat/Factories/Implementations/ChatAgentFactoryTest.php` — the wire-layout shapes the factory consumes (T3/T4 pair well with these)

Target size: ~15–20 request fixtures per format, ~10 response fixtures, 4–5 cross-format
pairs, 4–5 emit-replay cases. More is fine; drive by the §3.1 checklist, not by number.

---

## 6. Definition of done

- All corpus tracks green; existing formatter tests untouched or only trivially deduplicated.
- `bin/env php vendor/bin/phpstan analyse` clean; targeted php-cs-fixer applied; full unit
  suite green; feature suites unaffected.
- **Record updated** (`001-generic-llm-backend-implementation.md`):
  - D4 → closed: describe the corpus as built and record the §9.1 reinterpretation (§2 above) plus any formatter bugs the corpus surfaced (with their fixes);
  - N5 → DONE (cross-reference this handoff);
  - §0 snapshot: add the corpus row, move the roadmap head to **N2** (`/models/{format?}`);
  - D9: annotate with whatever the corpus found about id survival (do not close it).
- One commit, message sketch: `test: wire->IR round-trip corpus for both chat formatters (N5 - closes D4)`.
