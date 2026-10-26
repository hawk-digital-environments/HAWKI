<?php

declare(strict_types=1);

namespace App\Services\AssistantKnowledge\Listeners;

use App\Services\Ai\Agents\Events\AgentSendingEvent;
use App\Services\Ai\Models\Settings\AiModelSettingRegistry;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Rag\Citations\RagCitationCollector;

/**
 * Arms the knowledge tool's final-call signal before an agent run starts.
 *
 * {@see AgentSendingEvent} fires ahead of both the synchronous and the
 * streaming request — before any tool can execute — which makes it the
 * earliest point where the run's model settings are known to the
 * AssistantKnowledge slice without touching the generic agent layer.
 *
 * The listener publishes the model's non-streaming tool-round limit
 * ({@see \App\Services\Ai\Models\Settings\Values\AiModelSettings::getMaxToolCallingRounds()}
 * — declared default 5, per-model overridable) into the request-scoped
 * {@see RagCitationCollector}; the MCP tool-call listener reads it to flag
 * the final permitted knowledge-tool call with a terminal notice. Streaming
 * runs override the value right after the stream is initiated via
 * {@see SetStreamingKnowledgeToolLimitOnAgentStreamInitiated}.
 */
class ArmFinalKnowledgeCallSignalingOnAgentSending
{
    public function __construct(
        private readonly RagCitationCollector $citations,
    ) {
    }

    public function handle(AgentSendingEvent $event): void
    {
        $this->citations->setToolRoundLimit(
            $this->resolveRoundLimit($event->context->model->settings, streaming: false),
        );
    }

    /**
     * Detached settings instances (hand-built models, e.g. in tests) cannot
     * resolve declared defaults on their own — attach the app's registry so
     * the read always resolves.
     */
    private function resolveRoundLimit(AiModelSettings $settings, bool $streaming): int {
        $settings->setService(AiModelSettingRegistry::class, app(AiModelSettingRegistry::class));

        return $settings->getMaxToolCallingRounds($streaming);
    }
}
