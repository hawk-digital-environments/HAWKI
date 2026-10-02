<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Ai\AiTool;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\Contracts\AgentTool;
use App\Services\Assistant\Values\ComposedAssistantRun;
use Illuminate\Container\Attributes\Singleton;

/**
 * Composes the AI run parameters for a chat exchange driven by an assistant:
 * the fully assembled system prompt, the assistant's model (and whether the
 * client may override it), the sampling parameters, and the tool-transfer
 * strings — the persisted capability selections (`capabilities`), the
 * concrete `ai_tools` attachments, and the transfer strings injected by
 * active {@see AgentTool}s (ambient capabilities granted by modules, e.g.
 * the Rag slice's knowledge-base tool).
 *
 * This is the single assembly source for every assistant-driven surface —
 * both the agent factory for legacy-payload surfaces (private chat, group
 * chat) and the OpenAI responses endpoint derive their exchange from here.
 *
 * @api consumed by core endpoints (OpenAI responses controller); part of
 *     the surface the future assistant plugin exposes to core
 */
#[Singleton]
class AssistantRunComposer
{
    public function __construct(
        private readonly AssistantPromptComposer $promptComposer,
        private readonly AgentToolRegistry $agentTools,
    ) {
    }

    public function compose(Assistant $assistant, ?User $actor = null): ComposedAssistantRun
    {
        $assistant->loadMissing(['ai_tools', 'assistantAttachments']);

        $activeAgentTools = $this->activeAgentTools($assistant, $actor);
        $activeKeys = array_map(static fn (AgentTool $tool): string => $tool->key(), $activeAgentTools);
        $usageInstructions = $this->usageInstructions($activeAgentTools, $assistant, $actor);

        return new ComposedAssistantRun(
            systemPrompt: $this->promptComposer->compose(
                $assistant,
                $actor,
                knowledgeHandledByAgentTool: in_array(WellKnownCapabilities::KNOWLEDGE_BASE, $activeKeys, true),
                agentToolInstructions: $usageInstructions,
            ),
            modelId: $assistant->model,
            allowModelSelect: $assistant->allow_model_select,
            params: array_filter([
                'temp' => $assistant->temp,
                'top_p' => $assistant->top_p,
                'max_tokens' => $assistant->max_tokens,
            ]),
            toolTransferStrings: [
                ...($assistant->capabilities ?? []),
                ...$assistant->ai_tools
                    ->reject(fn (AiTool $tool): bool => in_array((string) $tool->getEffectiveCapability(), $activeKeys, true))
                    ->map(fn (AiTool $tool): string => $tool->name)
                    ->values()
                    ->all(),
                ...$this->agentToolTransferStrings($activeAgentTools, $assistant, $actor),
            ],
        );
    }

    /**
     * The declared agent tools that can serve their capability for this
     * run. An active tool supersedes the assistant's explicit selections
     * for its capability key — ambient capability beats manual wiring.
     *
     * @return list<AgentTool>
     */
    private function activeAgentTools(Assistant $assistant, ?User $actor): array
    {
        $active = [];

        foreach ($this->agentTools as $agentTool) {
            if ($agentTool->isAvailable($assistant, $actor)) {
                $active[] = $agentTool;
            }
        }

        return $active;
    }

    /**
     * @param list<AgentTool> $activeAgentTools
     *
     * @return list<string>
     */
    private function agentToolTransferStrings(array $activeAgentTools, Assistant $assistant, ?User $actor): array
    {
        $strings = [];

        foreach ($activeAgentTools as $agentTool) {
            $strings = [...$strings, ...$agentTool->toolTransferStrings($assistant, $actor)];
        }

        return $strings;
    }

    /**
     * The usage-instruction modules contributed by the active agent tools,
     * in declaration order — appended to the system prompt so the model
     * learns the rules for the capabilities granted in the background.
     *
     * @param list<AgentTool> $activeAgentTools
     *
     * @return list<string>
     */
    private function usageInstructions(array $activeAgentTools, Assistant $assistant, ?User $actor): array
    {
        $instructions = [];

        foreach ($activeAgentTools as $agentTool) {
            $module = $agentTool->usageInstructions($assistant, $actor);

            if ($module !== null && $module !== '') {
                $instructions[] = $module;
            }
        }

        return $instructions;
    }
}
