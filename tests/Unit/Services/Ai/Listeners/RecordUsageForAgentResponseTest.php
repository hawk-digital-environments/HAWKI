<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Listeners;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Records\UsageRecord;
use App\Models\User;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Events\AgentResponseReceivedEvent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Chat\Events\UsageRecordedEvent;
use App\Services\Ai\Listeners\RecordUsageForAgentResponse;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RecordUsageForAgentResponse::class)]
class RecordUsageForAgentResponseTest extends TestCase
{
    use RefreshDatabase;
    private RecordUsageForAgentResponse $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new RecordUsageForAgentResponse(
            usageAnalyzer: $this->app->make(\App\Services\Ai\UsageAnalyzerService::class),
            request: \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'test-agent']),
        );
    }

    public function testItConstructs(): void
    {
        self::assertInstanceOf(RecordUsageForAgentResponse::class, $this->sut);
    }

    public function testItRecordsUsageForListenerOwnedContexts(): void
    {
        Event::fake([UsageRecordedEvent::class]);
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $context = $this->buildContext(usageRecordedViaListener: true);
        $response = new AgentResponse('inv_1', 'Hi', new Usage(promptTokens: 11, completionTokens: 13), new Meta());

        $this->sut->handle(new AgentResponseReceivedEvent(
            agent: self::createStub(AgentInterface::class),
            context: $context,
            provider: $context->provider,
            response: $response,
            usage: $response->usage,
        ));

        $record = UsageRecord::query()->latest('id')->first();
        self::assertNotNull($record);
        self::assertSame(11, $record->prompt_tokens);
        self::assertSame(13, $record->completion_tokens);
        self::assertSame('private', $record->type);
        self::assertSame($user->id, $record->user_id);

        Event::assertDispatched(UsageRecordedEvent::class, static function (UsageRecordedEvent $event): bool {
            return 11 === $event->tokenUsage->promptTokens
                && 'chat' === $event->channel
                && 'gpt-4o' === $event->modelId
                && 'openResponses' === $event->formatKey;
        });
    }

    public function testItSkipsContextsNotOwnedByTheListener(): void
    {
        Event::fake([UsageRecordedEvent::class]);
        $this->actingAsUser(User::factory()->create());

        $context = $this->buildContext(usageRecordedViaListener: false);
        $response = new AgentResponse('inv_1', 'Hi', new Usage(promptTokens: 11), new Meta());

        $this->sut->handle(new AgentResponseReceivedEvent(
            agent: self::createStub(AgentInterface::class),
            context: $context,
            provider: $context->provider,
            response: $response,
            usage: $response->usage,
        ));

        self::assertSame(0, UsageRecord::query()->count());
        Event::assertNotDispatched(UsageRecordedEvent::class);
    }

    private function buildContext(bool $usageRecordedViaListener): AgentRequestContext
    {
        $model = new AiModel(['model_id' => 'gpt-4o']);
        $model->setRelation('parameters', collect());

        return new AgentRequestContext(
            provider: new AiProviderProxy(
                provider: new AiProvider(['provider_id' => 'openAi', 'name' => 'OpenAI']),
                adapter: self::createStub(ProviderAdapterInterface::class),
                driver: self::createStub(Provider::class),
            ),
            model: $model,
            modelParameters: \App\Services\Ai\Models\Parameters\Values\AiModelParameters::fromArray([]),
            usageType: 'main',
            formatKey: 'openResponses',
            usageRecordedViaListener: $usageRecordedViaListener,
        );
    }
}
