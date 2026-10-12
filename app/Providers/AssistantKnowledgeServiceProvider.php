<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\AgentToolRegistry;
use App\Services\AssistantKnowledge\AgentTools\RagKnowledgeAgentTool;
use Illuminate\Support\ServiceProvider;

/**
 * The wiring point of the AssistantKnowledge composition slice — the
 * feature that extends multiple modules at once: it adopts the RAG module
 * as one of the Assistant host's ambient capabilities by declaring the
 * knowledge-base agent tool (which grants every assistant carrying
 * knowledge files the query tool for its own dataset) into the
 * assistant-owned agent-tool registry.
 *
 * The slice composes three publish sides:
 * - Assistant: the {@see AgentTool} contract, attachment events, and the
 *   RAG state columns on the attachment table;
 * - RAG: {@see RagConfig} (module gate, dataset prefix, tool identities)
 *   and the ingester contract;
 * - Ai core: MCP tool filter events and text extraction.
 *
 * Neither base slice knows about the composition: Assistant stays
 * knowledge-agnostic (context-injection fallback when RAG is off), RAG
 * stays assistant-free (a pure knowledge service). At plugin-extraction
 * time the two base slices ship as plugins and this slice becomes the
 * bridge package requiring both — a future shared-dataset chat tool, by
 * contrast, belongs to core, not to another bridge.
 *
 * Eager rather than deferrable: the declaration must run on every boot so
 * the agent tool is declared before any assistant run is composed.
 */
class AssistantKnowledgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->extend(AgentToolRegistry::class, static function (AgentToolRegistry $registry): AgentToolRegistry {
            return $registry->declare(
                WellKnownCapabilities::KNOWLEDGE_BASE,
                RagKnowledgeAgentTool::class,
            );
        });
    }
}
