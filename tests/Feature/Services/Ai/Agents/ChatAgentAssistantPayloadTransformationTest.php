<?php
declare(strict_types=1);

namespace Tests\Feature\Services\Ai\Agents;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Agents\AssistantChatAgentFactory;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Ai\AiService;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Agents\Middleware\LoggingMiddleware;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\OpenAiProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\Services\Ai\Agents\ChatAgentAssistantPayloadTransformationTestFixtures\CapturingTextGateway;
use Tests\TestCase;

/**
 * Follows a real frontend chat payload (the `/req/streamAI` shape the Svelte
 * chat transport sends) all the way through the agent pipeline: the
 * {@see AssistantChatAgentFactory} rewrites the payload from the addressed
 * assistant, the {@see ChatAgentFromLegacyRequestFactory} assembles the message
 * history, and the {@see \App\Services\Ai\Agents\Implementations\Chat\ChatAgent}
 * wraps the instructions with the HKI_META preamble.
 *
 * The provider boundary is faked with a {@see CapturingTextGateway} installed
 * on the provider driver, so the tests assert on the exact model, system
 * instructions, and message list the SDK would hand to the gateway — including
 * the ANSWER_SOURCE attribution blocks for past assistant turns.
 */
#[CoversClass(AssistantChatAgentFactory::class)]
#[CoversClass(ChatAgentFromLegacyRequestFactory::class)]
class ChatAgentAssistantPayloadTransformationTest extends TestCase
{
    use RefreshDatabase;

    private const string INVENTORE_HANDLE = 'inventore-aut-earum';
    private const string ELIGENDI_HANDLE = 'eligendi-magni-veritatis';

    public function testItTransformsAFrontendAssistantPayloadForTheProviderBoundary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->createAssistant($user, self::INVENTORE_HANDLE, 'You are the inventore assistant. Always calculate precisely.', 'inventore-model');
        $this->createAssistant($user, self::ELIGENDI_HANDLE, 'You are the eligendi assistant.', 'eligendi-model');

        $gateway = new CapturingTextGateway(['Fake assistant reply.']);
        $this->mockProviderInfrastructure($gateway);

        $agent = $this->app->get(AiService::class)->getAgent($this->frontendPayload());
        $response = $agent->send();

        static::assertSame('Fake assistant reply.', $response->text);
        static::assertCount(1, $gateway->capturedSteps);
        $step = $gateway->capturedSteps[0];

        // The assistant's own model wins (model selection not allowed).
        static::assertSame('inventore-model', $step['model']);

        // System instructions: HKI_META preamble + composed assistant prompt,
        // replacing the frontend's plain conversation prompt.
        static::assertStringContainsString('# System metadata blocks (HKI_META)', $step['instructions']);
        static::assertStringContainsString('You are the inventore assistant.', $step['instructions']);
        static::assertStringNotContainsString('HAWK Hildesheim', $step['instructions']);

        // The history plus the current prompt, in order.
        $messages = $step['messages'];
        static::assertCount(7, $messages);

        static::assertInstanceOf(UserMessage::class, $messages[0]);
        static::assertSame('hello', $messages[0]->content);

        // Default-chat answer: no attribution block.
        static::assertSame('Hello! How can I support you today?', $messages[1]->content);

        // The @handle token is stripped from every user turn.
        static::assertSame('hello', $messages[2]->content);

        // Attributed assistant turn from the first assistant — the assistant
        // this run belongs to: its attribution is elided, no answer-source
        // block is injected for its own earlier answer.
        static::assertSame('assistant', $messages[3]->role->value);
        static::assertSame('1 + 5 = 6', $messages[3]->content);

        static::assertSame('hello', $messages[4]->content);

        // Attributed assistant turn from the second assistant — foreign here:
        // the disambiguation block survives.
        static::assertSame('assistant', $messages[5]->role->value);
        static::assertStringContainsString('[HKI_META_ANSWER_SOURCE]', $messages[5]->content);
        static::assertStringContainsString('@' . self::ELIGENDI_HANDLE, $messages[5]->content);
        static::assertStringContainsString('Hello! How can I help you today?', $messages[5]->content);

        // The current prompt arrives as the final user message, handle stripped.
        static::assertInstanceOf(UserMessage::class, $messages[6]);
        static::assertSame('hello you', $messages[6]->content);
    }

    public function testItKeepsAttributionBlocksForPlainRunsOfMixedConversations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->createAssistant($user, self::INVENTORE_HANDLE, 'You are the inventore assistant.', 'inventore-model');

        $gateway = new CapturingTextGateway(['Fake plain reply.']);
        $this->mockProviderInfrastructure($gateway);

        $payload = $this->frontendPayload();
        // No payload-level handle: a plain HAWKI run over the same mixed history.
        unset($payload['payload']['hawkiExtensions']);
        // Only the inventore assistant exists in this scenario, so the second
        // attributed turn also belongs to it.
        $payload['payload']['messages'][6]['hawkiExtensions'] = ['assistant_handle' => self::INVENTORE_HANDLE];

        $agent = $this->app->get(AiService::class)->getAgent($payload);
        $agent->send();

        static::assertCount(1, $gateway->capturedSteps);
        $step = $gateway->capturedSteps[0];

        // Plain runs keep the frontend's own system prompt (plus the preamble).
        static::assertStringContainsString('# System metadata blocks (HKI_META)', $step['instructions']);
        static::assertStringContainsString('HAWK Hildesheim', $step['instructions']);
        static::assertStringNotContainsString('You are the inventore assistant.', $step['instructions']);

        // Past assistant attribution survives; @handle tokens are not stripped
        // on plain runs (no addressed assistant to strip).
        $messages = $step['messages'];
        static::assertStringContainsString('[HKI_META_ANSWER_SOURCE]', $messages[5]->content);
        static::assertStringContainsString('@' . self::INVENTORE_HANDLE, $messages[5]->content);
        static::assertStringContainsString('Hello! How can I help you today?', $messages[5]->content);
        static::assertSame('@' . self::INVENTORE_HANDLE . ' hello you', $messages[6]->content);
    }

    // =========================================================================
    // Payload / fixtures
    // =========================================================================

    /**
     * Reduced copy of a real frontend `/req/streamAI` payload: a conversation
     * that mixed plain turns with two different assistants and now addresses
     * the first assistant again.
     */
    private function frontendPayload(): array
    {
        return [
            'broadcast' => false,
            'threadIndex' => 0,
            'slug' => '',
            'isUpdate' => false,
            'messageId' => null,
            'payload' => [
                'model' => 'gpt-4.1',
                'broadcast' => false,
                'stream' => true,
                'messages' => [
                    ['role' => 'system', 'content' => ['text' => 'You are an intelligent and supportive AI assistance system for all university members of HAWK Hildesheim/Holzminden/Göttingen.']],
                    ['role' => 'user', 'content' => ['text' => 'hello']],
                    ['role' => 'assistant', 'content' => ['text' => 'Hello! How can I support you today?']],
                    ['role' => 'user', 'content' => ['text' => '@' . self::INVENTORE_HANDLE . ' hello']],
                    ['role' => 'assistant', 'content' => ['text' => '1 + 5 = 6'], 'hawkiExtensions' => ['assistant_handle' => self::INVENTORE_HANDLE]],
                    ['role' => 'user', 'content' => ['text' => 'hello']],
                    ['role' => 'assistant', 'content' => ['text' => 'Hello! How can I help you today?'], 'hawkiExtensions' => ['assistant_handle' => self::ELIGENDI_HANDLE]],
                    ['role' => 'user', 'content' => ['text' => '@' . self::INVENTORE_HANDLE . ' hello you']],
                ],
                'tools' => [],
                'params' => ['temp' => 0.16, 'top_p' => 0.82, 'max_tokens' => 2805],
                'hawkiExtensions' => ['assistant_handle' => self::INVENTORE_HANDLE],
            ],
        ];
    }

    private function createAssistant(User $creator, string $handle, string $systemPrompt, string $model): Assistant
    {
        return Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => 'private',
            'handle' => $handle,
            'model' => $model,
            'system_prompt' => $systemPrompt,
            'allow_model_select' => false,
            'max_tokens' => 0,
        ]);
    }

    /**
     * Replaces the model lookup and provider resolution with in-memory stubs
     * and installs the capturing gateway on a real provider driver, so the
     * full agent pipeline runs without touching the database provider config
     * or the network.
     */
    private function mockProviderInfrastructure(CapturingTextGateway $gateway): void
    {
        // The real logging middleware runs in the pipeline; container fallback is
        // enabled because PHPUnit's ServiceLocator guard would otherwise reject
        // the logger/auth resolution the middleware performs via the locator.
        $this->app->instance(
            LoggingMiddleware::class,
            (new LoggingMiddleware())->useServiceContainerFallback(true)
        );

        $events = $this->app->make(Dispatcher::class);

        $driver = new OpenAiProvider(
            new OpenAiGateway($events),
            ['name' => 'fake-openai', 'driver' => 'openai', 'key' => 'test-key'],
            $events,
        );
        $driver->useTextGateway($gateway);

        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('getAdditionalDriverOptions')->willReturn([]);

        $proxy = new AiProviderProxy(
            provider: $this->createMock(AiProvider::class),
            adapter: $adapter,
            driver: $driver,
        );

        $this->mock(AiProviderProxyResolver::class, static function ($mock) use ($proxy): void {
            $mock->shouldReceive('resolveForModel')->andReturn($proxy);
        });

        $model = $this->makeModel();
        $this->mock(AiModelRepository::class, static function ($mock) use ($model): void {
            $mock->shouldReceive('findOneOrFail')->andReturn($model);
        });
    }

    private function makeModel(): AiModel
    {
        $flags = $this->createMock(AiModelFlags::class);
        $flags->method('hasFeatureSamplingParameters')->willReturn(true);

        $model = $this->createMock(AiModel::class);
        // AiModel exposes cast properties via Eloquent's magic __get → getAttribute.
        $model->method('__get')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'flags' => $flags,
                'model_id' => 'inventore-model',
                default => null,
            }
        );

        return $model;
    }
}
