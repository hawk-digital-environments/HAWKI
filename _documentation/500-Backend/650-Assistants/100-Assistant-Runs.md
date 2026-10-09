---
sidebar_position: 1
---

# Assistant Runs

Every assistant-driven exchange — private chat, group chat, and the OpenAI-compatible responses endpoint — derives its parameters from a single assembly source: `App\Services\Assistant\AssistantRunComposer::compose()`. This page describes what a run consists of and how the tool set is assembled, including the ambient agent-tool system and its supersede semantics.

## The composed run

`compose()` returns a `ComposedAssistantRun`:

| Part | Content |
|---|---|
| `systemPrompt` | The assistant's base prompt plus composed modules (see below) |
| `modelId` / `allowModelSelect` | The assistant's model; whether the client may override it per request |
| `params` | `temp`, `top_p`, `max_tokens` |
| `toolTransferStrings` | The tool set (see below) |

Because all surfaces derive from the same composition, a tool granted in the background is present in private chat, group chat, and the external API alike — there is no per-surface tool wiring.

## Tool transfer strings

The run's tool set is an ordered list of transfer strings from three sources:

1. **Persisted capabilities** (`assistant.capabilities`) — strings of the form `capability:<key>:<mode>` chosen earlier. They pass through **verbatim and unfiltered**: a stale entry (e.g. for a capability the instance no longer serves) fails loudly at tool resolution with a `TOOL_*` error rather than being silently stripped. That is deliberate — silent stripping would hide configuration drift.
2. **Attached tools** (`ai_tools`) — concrete tool rows the creator picked in the builder, transferred by bare name (`hawki-rag-web-search-tool`). Settings for these come from the model's own arguments.
3. **Ambient grants** — transfer strings injected by active agent tools (below), of the form `name:{"…settings…"}`. The settings are merged server-side over the model's arguments and win — the model never sees or chooses them.

## The agent-tool system (ambient capabilities)

An *agent tool* (`App\Services\Assistant\Contracts\AgentTool`) is a capability a module grants to an assistant's run automatically — the assistant simply *has* it whenever the serving module can deliver it, with no per-assistant wiring. Implementations live in composition slices (see the [Plugin System Preview](../1000-Infrastructure/100-Plugin-System-Preview.md)) and are declared into the `AgentToolRegistry` from the composition's service provider, keyed by a `WellKnownCapabilities` constant. One implementation serves each capability key.

The contract has four members:

- `key()` — the capability key the tool serves.
- `isAvailable(Assistant, ?User)` — whether the module can serve the capability *for this run*. Implementations keep this deliberately minimal (module enabled + the assistant has the material, e.g. knowledge files) — grant and linkage of the concrete tool are **not** pre-checked: a misconfigured instance (offline server, non-tool-calling model, missing model attachment) fails loudly at tool resolution instead of silently stripping the capability.
- `toolTransferStrings(Assistant, ?User)` — the granted tools; typically one HAWKI tool addressed by name with server-side settings.
- `usageInstructions(Assistant, ?User)` — an optional prompt module appended to the system prompt so the model learns the rules for the granted capability.

### Supersede semantics

An active agent tool supersedes attached tools **under the tool names it grants** — the `name:` prefix of its transfer strings. The grant is the authoritative instance of that tool: it carries the server-side settings the model must not choose, so a same-named attachment would resolve without them and break. When the composer drops such an attachment it logs a warning — an attachable tool shadowed by an ambient grant is an admin-side configuration overlap (rename the tool or remove the attachment), not something the assistant creator can fix from the builder.

Everything else **coexists** with the grant:

- Attached tools serving the *same capability* under a different name stay in the run. Their usage is up to the model and their own descriptions; the grant's prompt module scopes itself to the granted tool.
- Attached tools for other capabilities are unaffected.
- Persisted capability strings are never filtered (see above).
- While no agent tool is active for a capability, attached tools for it pass through untouched.

### Prompt interaction

The composer tells the prompt composer which capabilities an active agent tool serves (`knowledgeHandledByAgentTool` for the knowledge base key): the inline extract module for assistant files is then suppressed — the capability delivers the content instead — and the agents' usage-instruction modules are appended after the base prompt in declaration order.

## Resolution failures surface to the user

Tool resolution runs before the stream opens. A failing resolution produces a typed error the transports map to user-facing messages — `TOOL_ACCESS_DENIED` (403, missing permission), `TOOL_UNAVAILABLE` (422, model/linkage problem), `TOOL_OFFLINE` (422, MCP server down). There is no graceful degradation to a knowledge-less answer by design: a broken configuration should be fixed, not papered over. See [Assistant Knowledge → Operations](../675-AssistantKnowledge/100-Knowledge-Tool.md#operations--troubleshooting) for the common causes and their fixes.
