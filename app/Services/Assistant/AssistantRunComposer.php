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
use Psr\Log\LoggerInterface;

/**
 * Composes the AI run parameters for a chat exchange driven by an assistant:
 * the fully assembled system prompt, the assistant's model (and whether the
 * client may override it), the sampling parameters, and the tool-transfer
 * strings — the persisted capability selections (`capabilities`), the
 * concrete `ai_tools` attachments, and the transfer strings injected by
 * active {@see AgentTool}s (ambient capabilities granted by modules, e.g.
 * the knowledge-base tool of the AssistantKnowledge slice).
 *
 * An active agent tool supersedes attached tools under the tool names it
 * grants (the `name:` prefix of its transfer strings): the grant is the
 * authoritative instance of that tool, carrying the server-side settings
 * the model must not choose. Other attached tools — including others
 * serving the same capability — coexist with the grant; their usage is up
 * to the model and their own descriptions.
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
        private readonly LoggerInterface $logger,
    ) {
    }

    public function compose(Assistant $assistant, ?User $actor = null): ComposedAssistantRun
    {
        $assistant->loadMissing(['ai_tools', 'assistantAttachments']);

        $activeAgentTools = $this->activeAgentTools($assistant, $actor);
        $activeKeys = array_map(static fn (AgentTool $tool): string => $tool->key(), $activeAgentTools);
        $usageInstructions = $this->usageInstructions($activeAgentTools, $assistant, $actor);
        $agentToolStrings = $this->agentToolTransferStrings($activeAgentTools, $assistant, $actor);

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
                    ->reject(fn (AiTool $tool): bool => $this->isSupersededByGrant($tool, $assistant, $agentToolStrings))
                    ->map(fn (AiTool $tool): string => $tool->name)
                    ->values()
                    ->all(),
                ...$agentToolStrings,
            ],
        );
    }

    /**
     * The declared agent tools that can serve their capability for this
     * run. An active tool supersedes same-named attached tools — the
     * ambient grant beats manual wiring.
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
     * Whether the attached tool collides with a name an active agent tool
     * grants — the grant wins (it carries the server-side settings), and
     * the collision is logged: an attachable tool shadowed by an ambient
     * grant is an admin-side configuration overlap, not something the
     * assistant creator can fix from the builder.
     *
     * @param list<string> $agentToolStrings
     */
    private function isSupersededByGrant(AiTool $tool, Assistant $assistant, array $agentToolStrings): bool
    {
        if (!in_array($tool->name, $this->grantedToolNames($agentToolStrings), true)) {
            return false;
        }

        $this->logger->warning(
            'Attached ai_tool is superseded by an active agent tool with the same name — the ambient grant wins; rename the attached tool or remove the attachment',
            ['tool_name' => $tool->name, 'assistant_id' => $assistant->id],
        );

        return true;
    }

    /**
     * The tool names the active agent tools grant, derived from their
     * transfer strings (`name:{"…settings…"}`): everything before the
     * first colon of each non-capability string. Capability-form strings
     * grant no concrete tool name.
     *
     * @param list<string> $agentToolStrings
     *
     * @return list<string>
     */
    private function grantedToolNames(array $agentToolStrings): array
    {
        $names = [];

        foreach ($agentToolStrings as $string) {
            if (str_starts_with($string, 'capability:')) {
                continue;
            }

            $separator = strpos($string, ':');
            $names[] = false === $separator ? $string : substr($string, 0, $separator);
        }

        return $names;
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
