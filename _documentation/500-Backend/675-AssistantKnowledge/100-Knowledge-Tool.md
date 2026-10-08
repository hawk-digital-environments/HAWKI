---
sidebar_position: 1
---

# The Knowledge Tool

The knowledge feature lets an assistant search its own uploaded files: every attachment is ingested into a RAG dataset in the background, and once files exist the assistant's run automatically carries a query tool scoped to that dataset. The model cites what it finds; the UI renders the citations as chips linked to the source tiles.

This feature is a **composition slice** (`App\Services\AssistantKnowledge`): it extends Assistant (the agent-tool contract, attachment events, RAG state columns), RAG (config, the ingester contract), and Ai core (MCP tool events, text extraction). Neither base slice knows about it — the mechanics of that pattern are documented in the [Plugin System Preview](../1000-Infrastructure/100-Plugin-System-Preview.md); the ambient-grant semantics in [Assistant Runs](../650-Assistants/100-Assistant-Runs.md).

## The ambient grant

When the RAG module is enabled and the assistant carries at least one attachment, `RagKnowledgeAgentTool` grants the query tool for the assistant's own dataset:

- **Tool**: `hawki-rag-query-search` (the configured `rag.query_tool`), attached to every tool-calling model by the `RagToolSeeder`.
- **Scope**: the dataset `assistant_<id>` — derived exactly like the ingestion pipeline's dataset naming, injected as a server-side setting the model never sees or chooses.
- **Prompt**: the `[KNOWLEDGE TOOL MODULE]` is appended to the system prompt (search-first rule, query rule with decomposition into distinct atomic queries and a 5-calls-per-answer budget, re-search rule for new conversational directions and evidence-directed refinement, no-evidence rule, citation rule, language rule). The module scopes itself to the granted tool; any other tools the creator attached ride along un-prompted.

Availability is deliberately minimal (module enabled + files exist). Grant and linkage are **not** pre-checked: a model without tool calling, a missing model↔tool attachment, or an offline RAG MCP server fails the request loudly with a typed `TOOL_*` error instead of silently answering without knowledge. See [Operations](#operations--troubleshooting).

## Ingestion

Uploads flow through an event-driven pipeline owned by the composition slice's `RagIngestionService`:

1. **Upload** → `AssistantAttachmentStoredEvent` → pre-flight dataset provisioning (best-effort) → `PENDING` → the `IngestAttachmentToRag` job travels in a batch so a still-queued ingestion can be revoked.
2. **Job**: retrieves the file, extracts text locally (`attachment_ingestion: text`) or uploads the original (`file`), pushes it to the RAG server with an idempotency key and a canonical `title` (the attachment name — so retrieval hits and citation titles match what the user uploaded), then polls the pipeline task until it completes.
3. **Deletion** → the batch is cancelled (if still queued) and the document is removed from the dataset; de-ingestion is best-effort and never blocks the attachment deletion.

The attachment tracks its state in `rag_status`: `PENDING` → `INGESTING` → `INGESTED` / `SKIPPED` / `FAILED`.

## Citations

The prompt's citation rule has the model mark claims inline with the document's exact name from the tool result's `documents` list, wrapped in double brackets: `[[report.pdf]]`. The frontend resolves these markers against the message's document citations and renders them as the same numbered chips the provider-citation path uses; unmatched markers degrade to plain `[report.pdf]`, duplicate document names never link to a guessed tile, markers inside code fences or inline code are never touched, and messages without document citations keep `[[…]]` verbatim (bash `[[ -f … ]]` and TOML `[[section]]` stay intact). During streaming the raw markers are visible until the stream completes and the citation frames arrive.

## Configuration

`config/rag.php` — install-time system setup: the `enabled` mode is decided when the instance is installed. Switching it mid-operation is not supported: ingested attachments carry RAG state and the server holds their datasets, so moving between RAG operation and plain file-upload behaviour requires a data migration.

| Key | Env | Default | Meaning |
|---|---|---|---|
| `enabled` | `HAWKI_RAG_ENABLED` | `false` | Master switch gating both ingestion and retrieval |
| `driver` | `HAWKI_RAG_DRIVER` | `hawki_rag` | Ingestion backend; anything else is a no-op ingester |
| `api_url` | `HAWKI_RAG_API_URL` | `http://localhost:8080/api` | RAG server REST base |
| `api_key` | `HAWKI_RAG_API_KEY` | | Bearer token with the `rag:text-ingest` ability |
| `timeout` | `HAWKI_RAG_API_TIMEOUT` | `30` | Per-request timeout for ingestion calls (raise for `file` mode) |
| `dataset_prefix` | — | `assistant_` | Dataset id prefix (`assistant_<id>`) |
| `attachment_ingestion` | `HAWKI_RAG_ATTACHMENT_INGESTION` | `text` | `text` = local extraction, `file` = server-side conversion |
| `query_tool` | — | `hawki-rag-query-search` | Local identity of the MCP query tool (wire contract) |
| `web_search_tool` | — | `hawki-rag-web-search-tool` | Local identity of the MCP web-search tool |

The MCP query server itself is configured in `config/tools.php` (`hawki-rag` entry: URL, key, timeouts) — see [Tools and MCP](../500-AI-Service-Layer/300-Tools-and-MCP.md) and the [env reference](../../200-Configuration/100-Dot-Env.md).

## Operations & troubleshooting

Model↔tool assignments come from **two seeders**, each with its own pass:

| Seeder | Seeds | Assigns to |
|---|---|---|
| `AiToolSeeder` | Mock servers/tools for the pickers, `test_tool` | First 3 tool-calling models (demo spread) |
| `RagToolSeeder` (`Database\Seeders\Rag`) | `hawki-rag` server + both RAG tools | **Every** tool-calling model (production coverage) |

`php artisan db:seed` runs both. Hazards:

| Action | Effect |
|---|---|
| `db:seed --class=AiToolSeeder` alone | RAG tools get **zero** assignments — every knowledge request fails |
| `ai:tools:sync --mcp-only` | Replaces seeder rows with server-id-suffixed slugs **and drops their assignments** |
| Fresh database | Both seeders warn and skip assignments until `ai:config:sync` has populated the models — run `ai:config:sync` **then** `db:seed` |

Symptom → cause map:

| Symptom | Cause |
|---|---|
| Builder: "The currently selected model is not configured for the knowledge base." | No `ai_model_tools` row linking the model to the KB tool |
| Stream error `TOOL_UNAVAILABLE` on a knowledge assistant | Model lacks tool calling, or the tool is not attached to the model |
| Stream error `TOOL_OFFLINE` | The `hawki-rag` MCP server did not answer the handshake (status check: `ai:check-status`) |

Recovery (idempotent, re-attaches both RAG tools to every tool-calling model):

```bash
php artisan db:seed --class='Database\Seeders\Rag\RagToolSeeder' --force
```
