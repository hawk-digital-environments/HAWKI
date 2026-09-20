<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Chat;

use App\Events\RoomMessageEvent;
use App\Jobs\GenerateRoomAiResponse;
use App\Jobs\SendMessage;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Member;
use App\Models\Room;
use App\Models\User;
use App\Services\Ai\Chat\Events\UsageRecordedEvent;
use App\Services\Ai\Chat\Factories\ChatAgentRegistry;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Chat\Events\RoomAiWritingStartedEvent;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Http\Controllers\Api\V1\UiChatController::class)]
class UiChatEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedModel();
        $this->room = Room::forceCreate([
            'room_name' => 'UiChat room',
            'slug' => 'uichat-room',
        ]);
        $this->editor = User::factory()->create();
        Member::forceCreate([
            'room_id' => $this->room->id,
            'user_id' => $this->editor->id,
            'role' => Member::ROLE_EDITOR,
        ]);
    }

    public function testItAnswersPrivateRequestsInOpenResponsesShape(): void
    {
        $this->actingAs($this->editor);
        $this->mockAgent(new AgentResponse('resp_1', 'Hello there', new Usage(promptTokens: 3, completionTokens: 4), new Meta()));

        $response = $this->postJson('/api/hawki/v1/ui-chat', [
            'model' => 'gpt-4.1-nano',
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('output.0.content.0.text', 'Hello there');
    }

    public function testItRejectsPrivateRequestsFromGuests(): void
    {
        $this->postJson('/api/hawki/v1/ui-chat', [
            'model' => 'gpt-4.1-nano',
            'input' => [['type' => 'message', 'role' => 'user', 'content' => 'Hello']],
        ])->assertUnauthorized();
    }

    public function testItDispatchesGroupRequestsAndRespondsImmediately(): void
    {
        Queue::fake([GenerateRoomAiResponse::class, SendMessage::class]);
        Event::fake([RoomMessageEvent::class, RoomAiWritingStartedEvent::class, UsageRecordedEvent::class]);
        $this->actingAs($this->editor);

        $response = $this->postJson('/api/hawki/v1/ui-chat/' . $this->room->slug, $this->groupBody());

        $response->assertOk()->assertExactJson(['success' => true]);

        $model = AiModel::query()->where('model_id', 'gpt-4.1-nano')->first();
        Queue::assertPushed(
            GenerateRoomAiResponse::class,
            fn (GenerateRoomAiResponse $job): bool => $job->roomId === $this->room->id
                && $job->modelId === $model->id
                && true === $job->aiRequest->hawkiExtension(AiRequest::HAWKI_EXTENSION_BROADCAST)
                && 'ui-chat' === $job->aiRequest->hawkiExtension(AiRequest::HAWKI_EXTENSION_CHANNEL)
                && 'regenerate-me' === $job->groupContext['messageId'],
        );

        Event::assertDispatched(RoomAiWritingStartedEvent::class);
        Event::assertDispatched(RoomMessageEvent::class, fn (RoomMessageEvent $event) => true === $event->data['data']['isGenerating']);
    }

    public function testItRejectsGroupRequestsWithoutRoomKey(): void
    {
        Queue::fake([GenerateRoomAiResponse::class]);
        $this->actingAs($this->editor);

        $body = $this->groupBody();
        unset($body['hawki']['key']);

        $this->postJson('/api/hawki/v1/ui-chat/' . $this->room->slug, $body)->assertStatus(422);

        Queue::assertNotPushed(GenerateRoomAiResponse::class);
    }

    public function testItRejectsGroupRequestsWithoutModel(): void
    {
        Queue::fake([GenerateRoomAiResponse::class]);
        $this->actingAs($this->editor);

        $body = $this->groupBody();
        unset($body['model']);

        $this->postJson('/api/hawki/v1/ui-chat/' . $this->room->slug, $body)
            ->assertStatus(400)
            ->assertJsonPath('error', 'A model is required for group chat requests.');

        Queue::assertNotPushed(GenerateRoomAiResponse::class);
    }

    public function testItRejectsUnknownModels(): void
    {
        Queue::fake([GenerateRoomAiResponse::class]);
        $this->actingAs($this->editor);

        $body = $this->groupBody();
        $body['model'] = 'does-not-exist';

        $this->postJson('/api/hawki/v1/ui-chat/' . $this->room->slug, $body)
            ->assertStatus(400)
            ->assertJsonPath('error', 'The requested model is not available.');

        Queue::assertNotPushed(GenerateRoomAiResponse::class);
    }

    public function testItRejectsNonEditors(): void
    {
        Queue::fake([GenerateRoomAiResponse::class]);
        $viewer = User::factory()->create();
        Member::forceCreate([
            'room_id' => $this->room->id,
            'user_id' => $viewer->id,
            'role' => Member::ROLE_VIEWER,
        ]);
        $this->actingAs($viewer);

        $this->postJson('/api/hawki/v1/ui-chat/' . $this->room->slug, $this->groupBody())->assertForbidden();

        Queue::assertNotPushed(GenerateRoomAiResponse::class);
    }

    public function testItRejectsGroupGuests(): void
    {
        Queue::fake([GenerateRoomAiResponse::class]);

        $this->postJson('/api/hawki/v1/ui-chat/' . $this->room->slug, $this->groupBody())->assertUnauthorized();
    }

    private function groupBody(): array
    {
        return [
            'model' => 'gpt-4.1-nano',
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Hello room'],
            ],
            'hawki' => [
                'threadIndex' => 0,
                'messageId' => 'regenerate-me',
                'isUpdate' => false,
                'key' => base64_encode(random_bytes(32)),
            ],
        ];
    }

    private function mockAgent(AgentResponse $response): void
    {
        $agent = new \Tests\Feature\Api\Chat\ChatEndpointTestFixtures\FakeChatAgent([], $response);

        $factory = new class($agent) implements \App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface {
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
            static fn (string $factoryClass): \App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface => $factory,
        ));
        $registry->declare($factory::class);

        $this->app->instance(ChatAgentRegistry::class, $registry);
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
            'label' => 'UiChat Test Model',
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
}
