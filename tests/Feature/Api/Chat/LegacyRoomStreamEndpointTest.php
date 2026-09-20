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
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Chat\Events\RoomAiWritingStartedEvent;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Http\Controllers\StreamController::class)]
class LegacyRoomStreamEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/req/room/streamAI/';

    private User $editor;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedModel();
        $this->room = Room::forceCreate([
            'room_name' => 'Feature room',
            'slug' => 'feature-room',
        ]);
        $this->editor = User::factory()->create();
        Member::forceCreate([
            'room_id' => $this->room->id,
            'user_id' => $this->editor->id,
            'role' => Member::ROLE_EDITOR,
        ]);
    }

    public function testItDispatchesTheQueuedGenerationAndRespondsImmediately(): void
    {
        Queue::fake([GenerateRoomAiResponse::class, SendMessage::class]);
        Event::fake([RoomMessageEvent::class, RoomAiWritingStartedEvent::class, UsageRecordedEvent::class]);
        $this->actingAs($this->editor);

        $response = $this->postJson(self::ENDPOINT . $this->room->slug, $this->body());

        $response->assertOk()->assertExactJson(['success' => true]);

        $model = AiModel::query()->where('model_id', 'gpt-4.1-nano')->first();
        Queue::assertPushed(
            GenerateRoomAiResponse::class,
            fn (GenerateRoomAiResponse $job): bool => $job->roomId === $this->room->id
                && $job->modelId === $model->id
                && 'gpt-4.1-nano' === $job->aiRequest->model
                && $job->aiRequest->hawkiExtension(\App\Services\Ai\Chat\Values\AiRequest::HAWKI_EXTENSION_BROADCAST) === true,
        );

        Event::assertDispatched(RoomAiWritingStartedEvent::class);
        Event::assertDispatched(RoomMessageEvent::class, fn (RoomMessageEvent $event) => true === $event->data['data']['isGenerating']);
        Queue::assertNotPushed(SendMessage::class);
    }

    public function testItRejectsUnknownModels(): void
    {
        Queue::fake([GenerateRoomAiResponse::class]);
        $this->actingAs($this->editor);

        $body = $this->body();
        $body['payload']['model'] = 'does-not-exist';

        $this->postJson(self::ENDPOINT . $this->room->slug, $body)
            ->assertStatus(400)
            ->assertExactJson(['error' => 'The requested model is not available.']);

        Queue::assertNotPushed(GenerateRoomAiResponse::class);
    }

    public function testItRejectsValidationErrors(): void
    {
        Queue::fake([GenerateRoomAiResponse::class]);
        $this->actingAs($this->editor);

        $body = $this->body();
        unset($body['payload']['messages']);

        $this->postJson(self::ENDPOINT . $this->room->slug, $body)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

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

        $this->postJson(self::ENDPOINT . $this->room->slug, $this->body())->assertForbidden();

        Queue::assertNotPushed(GenerateRoomAiResponse::class);
    }

    public function testItRejectsGuests(): void
    {
        Queue::fake([GenerateRoomAiResponse::class]);

        $this->postJson(self::ENDPOINT . $this->room->slug, $this->body())->assertUnauthorized();

        Queue::assertNotPushed(GenerateRoomAiResponse::class);
    }

    private function body(): array
    {
        return [
            'broadcast' => true,
            'threadIndex' => 0,
            'slug' => $this->room->slug,
            'isUpdate' => false,
            'messageId' => null,
            'key' => base64_encode(random_bytes(32)),
            'payload' => [
                'model' => 'gpt-4.1-nano',
                'broadcast' => true,
                'stream' => false,
                'messages' => [
                    ['role' => 'user', 'content' => ['text' => 'Hello room']],
                ],
                'tools' => null,
                'params' => null,
            ],
        ];
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
            'label' => 'Room Test Model',
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
