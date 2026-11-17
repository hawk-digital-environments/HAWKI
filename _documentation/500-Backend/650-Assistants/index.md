---
sidebar_position: 1
---

# Assistants

Assistants are pre-configured chat partners: a system prompt, a model (optionally client-selectable), sampling parameters, and a tool set — composed into an agent run for every exchange. This section covers the backend slice that assembles those runs.

- [100-Assistant-Runs](100-Assistant-Runs.md) — run composition, the tool-transfer-string sources, the ambient agent-tool system, and supersede semantics.
- [200-Assistant-Review-Workflow](200-Assistant-Review-Workflow.md) — the versioned working draft, submission freezing, decisions and re-decisions, and rollback.

The knowledge feature that lets assistants search their own uploaded files is a separate composition slice, documented in [Assistant Knowledge](../675-AssistantKnowledge/100-Knowledge-Tool.md).
