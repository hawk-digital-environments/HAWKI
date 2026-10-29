<?php

declare(strict_types=1);

namespace App\Services\AssistantKnowledge\Listeners;

use App\Services\Ai\Agents\Events\AgentStreamInitiatedEvent;
use App\Services\Ai\Models\Settings\AiModelSettingRegistry;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Rag\Citations\RagCitationCollector;

/**
 * Switches the knowledge tool's final-call signal to the streaming limit.
 *
 * {@see AgentStreamInitiatedEvent} fires when the streaming response object
 * has been created but before the stream is consumed — and tools only
 * execute while the stream is consumed, downstream of the agent call. The
 * override therefore always lands before the first knowledge-tool call of a
 * streaming run. Synchronous runs never dispatch this event and keep the
 * non-streaming limit armed by
 * {@see ArmFinalKnowledgeCallSignalingOnAgentSending}.
 */
class SetStreamingKnowledgeToolLimitOnAgentStreamInitiated
{
    public function __construct(
        private readonly RagCitationCollector $citations,
    ) {
    }

    public function handle(AgentStreamInitiatedEvent $event): void
    {
        $settings = $event->context->model->settings;
        // Detached settings instances cannot resolve declared defaults on
        // their own — attach the app's registry so the read always resolves.
        $settings->setService(AiModelSettingRegistry::class, app(AiModelSettingRegistry::class));

        $this->citations->setToolRoundLimit($settings->getMaxToolCallingRounds(true));
    }
}
