<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AssistantKnowledge\Listeners;

use App\Models\Ai\AiModel;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Events\AgentSendingEvent;
use App\Services\Ai\Agents\Events\AgentStreamInitiatedEvent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Models\Settings\Values\WellKnownModelSettings;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\AssistantKnowledge\Listeners\ArmFinalKnowledgeCallSignalingOnAgentSending;
use App\Services\AssistantKnowledge\Listeners\SetStreamingKnowledgeToolLimitOnAgentStreamInitiated;
use App\Services\Rag\Citations\RagCitationCollector;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StreamableAgentResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ArmFinalKnowledgeCallSignalingOnAgentSending::class)]
#[CoversClass(SetStreamingKnowledgeToolLimitOnAgentStreamInitiated::class)]
class FinalKnowledgeCallSignalingListenersTest extends TestCase
{
    public function testAgentSendingArmsTheDeclaredNonStreamingDefault(): void
    {
        $collector = $this->collector();
        static::assertSame(0, $collector->toolRoundLimit());

        (new ArmFinalKnowledgeCallSignalingOnAgentSending($collector))
            ->handle($this->sendingEvent(settings: AiModelSettings::fromArray([])));

        static::assertSame(5, $collector->toolRoundLimit());
    }

    public function testStreamInitiatedOverridesWithTheStreamingDefault(): void
    {
        $collector = $this->collector();
        $collector->setToolRoundLimit(5);

        (new SetStreamingKnowledgeToolLimitOnAgentStreamInitiated($collector))
            ->handle($this->streamInitiatedEvent(settings: AiModelSettings::fromArray([])));

        static::assertSame(3, $collector->toolRoundLimit());
    }

    public function testPerModelOverridesWinInBothModes(): void
    {
        $collector = $this->collector();
        $settings = AiModelSettings::fromArray([
            WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS => 8,
            WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS_STREAMING => 2,
        ]);

        (new ArmFinalKnowledgeCallSignalingOnAgentSending($collector))
            ->handle($this->sendingEvent(settings: $settings));
        static::assertSame(8, $collector->toolRoundLimit());

        (new SetStreamingKnowledgeToolLimitOnAgentStreamInitiated($collector))
            ->handle($this->streamInitiatedEvent(settings: $settings));
        static::assertSame(2, $collector->toolRoundLimit());
    }

    private function collector(): RagCitationCollector
    {
        // Same scoped instance the MCP tool-call listener reads.
        return $this->app->make(RagCitationCollector::class);
    }

    private function sendingEvent(AiModelSettings $settings): AgentSendingEvent
    {
        return new AgentSendingEvent(
            agent: $this->createMock(AgentInterface::class),
            context: $this->context($settings),
            provider: $this->provider(),
        );
    }

    private function streamInitiatedEvent(AiModelSettings $settings): AgentStreamInitiatedEvent
    {
        return new AgentStreamInitiatedEvent(
            agent: $this->createMock(AgentInterface::class),
            context: $this->context($settings),
            provider: $this->provider(),
            response: new StreamableAgentResponse(
                invocationId: 'inv_1',
                generator: static function (): \Generator {
                    yield from [];
                },
                meta: new Meta,
            ),
        );
    }

    private function context(AiModelSettings $settings): AgentRequestContext
    {
        $model = $this->createMock(AiModel::class);
        $model->method('__get')->willReturnCallback(
            static fn (string $key): ?AiModelSettings => $key === 'settings' ? $settings : null,
        );

        return new AgentRequestContext(
            provider: $this->provider(),
            model: $model,
            modelParameters: new AiModelParameters(),
        );
    }

    private function provider(): AiProviderProxy
    {
        return new AiProviderProxy(
            provider: new \App\Models\Ai\AiProvider(),
            adapter: $this->createMock(\App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface::class),
            driver: $this->createMock(\Laravel\Ai\Providers\Provider::class),
        );
    }
}
