<?php

declare(strict_types=1);

namespace App\Services\Rag\AssistantKnowledge\AgentTools;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\Contracts\AgentTool;
use App\Services\Assistant\Values\AssistantPromptTemplate;
use App\Services\Rag\Config\RagConfig;
use Illuminate\Container\Attributes\Singleton;

/**
 * The knowledge-base agent tool: grants every assistant carrying knowledge
 * files the capability to search its own RAG dataset.
 *
 * Availability follows the module, not per-assistant wiring: when RAG
 * ingestion is enabled and the assistant has at least one attachment, the
 * capability is granted in the background — the ambient counterpart of the
 * ingestion pipeline, which likewise runs without any per-assistant setup.
 * Grant and linkage of the concrete knowledge-base tool are deliberately
 * NOT pre-checked here: a misconfigured instance fails loudly at tool
 * resolution instead of silently stripping the assistant's knowledge.
 *
 * The transfer string addresses HAWKI's file-knowledge tool directly by
 * name, carrying the dataset id as a server-side setting — the assistant's
 * documents live in HAWKI's RAG datasets, never in a provider-native
 * store, and the model never sees or chooses the dataset. The dataset id
 * is derived exactly like the ingestion pipeline's dataset naming.
 */
#[Singleton]
final class RagKnowledgeAgentTool implements AgentTool
{
    /** HAWKI's internal file-knowledge search tool (seeder/MCP-sync-defined name). */
    public const string TOOL_NAME = 'hawki-rag-query-search';

    public function __construct(
        private readonly RagConfig $config,
    ) {
    }

    public function key(): string
    {
        return WellKnownCapabilities::KNOWLEDGE_BASE;
    }

    public function isAvailable(Assistant $assistant, ?User $actor = null): bool
    {
        return $this->config->enabled && $assistant->assistantAttachments->isNotEmpty();
    }

    public function toolTransferStrings(Assistant $assistant, ?User $actor = null): array
    {
        $settings = json_encode(
            ['dataset_id' => $this->config->datasetPrefix . $assistant->id],
            JSON_THROW_ON_ERROR,
        );

        return [self::TOOL_NAME . ':' . $settings];
    }

    /**
     * The knowledge-tool usage rules appended to the system prompt while
     * this tool is active, referencing {@see TOOL_NAME} — the name the
     * model sees in its tool list.
     */
    public function usageInstructions(Assistant $assistant, ?User $actor = null): string
    {
        return strtr(AssistantPromptTemplate::KNOWLEDGE_TOOL, [
            '{{tool_name}}' => self::TOOL_NAME,
        ]);
    }
}
