<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Ai\AiTool;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\Values\ComposedAssistantRun;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;

/**
 * Composes the AI run parameters for a chat exchange driven by an assistant:
 * the fully assembled system prompt, the assistant's model (and whether the
 * client may override it), the sampling parameters, and the tool-transfer
 * strings for the assistant's attached tools.
 *
 * This is the single assembly source for every assistant-driven surface —
 * both the agent factory for legacy-payload surfaces (private chat, group
 * chat) and the OpenAI responses endpoint derive their exchange from here.
 */
#[Singleton]
class AssistantRunComposer
{
    public function __construct(
        private readonly AssistantPromptComposer $promptComposer,
        #[Config('rag.enabled')]
        private readonly bool $ragEnabled,
        #[Config('rag.dataset_prefix')]
        private readonly string $ragDatasetPrefix,
    ) {
    }

    public function compose(Assistant $assistant, ?User $actor = null): ComposedAssistantRun
    {
        $assistant->loadMissing(['ai_tools', 'assistantAttachments']);

        return new ComposedAssistantRun(
            systemPrompt: $this->promptComposer->compose($assistant, $actor),
            modelId: $assistant->model,
            allowModelSelect: $assistant->allow_model_select,
            params: array_filter([
                'temp' => $assistant->temp,
                'top_p' => $assistant->top_p,
                'max_tokens' => $assistant->max_tokens,
            ]),
            toolTransferStrings: $assistant->ai_tools
                ->map(fn (AiTool $tool): string => $this->transferStringFor($tool, $assistant))
                ->values()
                ->all(),
        );
    }

    /**
     * The tool-transfer string for one attached tool. Knowledge-base tools
     * carry the assistant's RAG dataset id as a setting so the MCP layer
     * scopes the query server-side — the model never sees or chooses the
     * dataset (mirroring the id derivation of the ingestion pipeline).
     */
    private function transferStringFor(AiTool $tool, Assistant $assistant): string
    {
        $settings = $this->ragEnabled && $tool->getEffectiveCapability() === WellKnownCapabilities::KNOWLEDGE_BASE
            ? ['dataset_id' => $this->ragDatasetPrefix . $assistant->id]
            : null;

        return $settings === null
            ? $tool->name
            : $tool->name . ':' . json_encode($settings, JSON_THROW_ON_ERROR);
    }
}
