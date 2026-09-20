<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Chat;

use App\Http\Controllers\Api\V1\ChatController;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\User;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Chat\Factories\ChatAgentRegistry;
use App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Responses\Data\UrlCitation;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\Api\Chat\ChatEndpointTestFixtures\FakeChatAgent;
use Tests\TestCase;

/**
 * Covers the forwarded legacy routes (Phase 3): POST /req/streamAI (web session)
 * and POST api/ai-req (sanctum) now run through the generic proxy with the `legacy`
 * NDJSON formatter. The group-chat route stays on StreamController until Phase 5.
 */
#[CoversClass(ChatController::class)]
class LegacyChatProxyEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function testItStreamsTheLegacyNdJsonFramesOnThePrivateRoute(): void
    {
        $this->seedHawkiUser();
        $this->seedModel();
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent([
            new StreamStart('s1', 'openai', 'gpt-4.1-nano', 1000),
            new TextDelta('e1', 'm1', 'Hel', 1001),
            new TextDelta('e2', 'm1', 'lo', 1002),
            new Citation('e3', 'm1', new UrlCitation('https://example.com', 'Example'), 1003),
            new StreamEnd('e4', 'stop', new Usage(promptTokens: 2, completionTokens: 3), 1004),
        ]);

        $response = $this->postJson('/req/streamAI', $this->payload(stream: true));

        $response->assertOk();
        self::assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));

        $lines = array_values(array_filter(explode("\n", $this->streamedBody($response))));
        $frames = array_map(static fn (string $line): array => json_decode($line, true), $lines);

        self::assertSame('header', $frames[0]['type']);
        self::assertSame('HAWKI', $frames[0]['author']['username']);
        self::assertSame('gpt-4.1-nano', $frames[0]['model']);

        self::assertSame('message', $frames[1]['type']);
        self::assertSame('Hel', $frames[1]['content']);
        self::assertSame('lo', $frames[2]['content']);

        self::assertSame('citation', $frames[3]['type']);
        self::assertSame('https://example.com', $frames[3]['content']['url']);

        self::assertSame('completion', $frames[array_key_last($frames)]['type']);
        self::assertTrue($frames[array_key_last($frames)]['isDone']);
        self::assertSame('Hello', $frames[array_key_last($frames)]['content']);
    }

    public function testItServesNonStreamingPrivateRequests(): void
    {
        $this->seedHawkiUser();
        $this->seedModel();
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent();

        $this->postJson('/req/streamAI', $this->payload(stream: false))
            ->assertOk()
            ->assertExactJson(['success' => true, 'content' => '{"text":"ok"}']);
    }

    public function testItServesTheExternalAiReqRoute(): void
    {
        $this->seedHawkiUser();
        $this->seedModel();
        $this->actingAsUser(User::factory()->create());
        $this->mockAgent();

        $response = $this->postJson('/api/ai-req', [
            'payload' => [
                'model' => 'gpt-4.1-nano',
                'messages' => [
                    ['role' => 'user', 'content' => ['text' => 'Hi']],
                ],
            ],
        ]);

        $response->assertOk()->assertExactJson(['success' => true, 'content' => '{"text":"ok"}']);
    }

    public function testItRejectsInvalidPayloadsInTheLegacyShape(): void
    {
        $this->seedHawkiUser();
        $this->actingAsUser(User::factory()->create());

        $this->postJson('/req/streamAI', ['payload' => ['messages' => []]])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function testItRejectsUnknownModels(): void
    {
        $this->seedHawkiUser();
        $this->actingAsUser(User::factory()->create());

        $this->postJson('/api/ai-req', [
            'payload' => [
                'model' => 'does-not-exist',
                'messages' => [['role' => 'user', 'content' => ['text' => 'Hi']]],
            ],
        ])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function testGuestsCannotUseTheRoutes(): void
    {
        $this->postJson('/req/streamAI', $this->payload(stream: true))
            ->assertUnauthorized();

        $this->postJson('/api/ai-req', $this->payload(stream: false))
            ->assertUnauthorized();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(bool $stream): array
    {
        return [
            'broadcast' => false,
            'payload' => [
                'model' => 'gpt-4.1-nano',
                'stream' => $stream,
                'messages' => [
                    ['role' => 'system', 'content' => ['text' => 'Be terse.']],
                    ['role' => 'user', 'content' => ['text' => 'Hello']],
                ],
            ],
        ];
    }

    private function mockAgent(array $vendorEvents = []): void
    {
        $agent = new FakeChatAgent($vendorEvents);

        $factory = new class($agent) implements ChatAgentFactoryInterface {
            public function __construct(private readonly AgentInterface $agent)
            {
            }

            public function createAgent(AiRequest $request): ?AgentInterface
            {
                return $this->agent;
            }
        };

        $registry = new ChatAgentRegistry(new \App\Utils\Lists\LazySingletonList(
            static fn (string $factoryClass): string => 'fake_' . $factoryClass,
            static fn (string $factoryClass): ChatAgentFactoryInterface => $factory,
        ));
        $registry->declare($factory::class);

        $this->app->instance(ChatAgentRegistry::class, $registry);
    }

    /**
     * The legacy proxy needs the HAWKI system user (id 1) for the stream header.
     */
    private function seedHawkiUser(): void
    {
        if (null !== User::query()->withoutGlobalScopes()->find(1)) {
            return;
        }

        User::forceCreate([
            'id' => 1,
            'name' => 'HAWKI',
            'email' => 'HAWKI@hawk.de',
            'username' => 'HAWKI',
            'employeetype' => 'system',
            'publicKey' => str_repeat('h', 64),
            'isRemoved' => false,
        ]);
    }

    private function seedModel(): void
    {
        $provider = AiProvider::create([
            'provider_id' => 'openAi',
            'name' => 'OpenAi',
            'active' => true,
            'adapter_key' => 'openai',
            'api_key' => 'test-key',
        ]);

        $model = AiModel::create([
            'model_id' => 'gpt-4.1-nano',
            'label' => 'Legacy Test Model',
            'provider_id' => $provider->id,
            'active' => true,
            'model_type' => 'chat',
            'parameters' => AiModelParameters::fromArray([]),
            'settings' => AiModelSettings::fromArray([]),
            'flags' => AiModelFlags::fromArray([]),
            'native_capabilities' => NativeAiModelCapabilities::fromArray([]),
            'limits' => ChatAiModelLimits::fromArray([]),
            'pricing' => ChatAiModelPricing::fromArray([]),
        ]);

        DB::table('ai_model_usage_rules')->insert([
            'ai_model_id' => $model->id,
            'usage_type' => WellKnownUsageTypes::MAIN_APP,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function streamedBody(object $response): string
    {
        $captured = '';
        ob_start(static function (string $buffer) use (&$captured): string {
            $captured .= $buffer;

            return '';
        });
        $response->baseResponse->sendContent();
        ob_end_clean();

        return $captured;
    }
}
