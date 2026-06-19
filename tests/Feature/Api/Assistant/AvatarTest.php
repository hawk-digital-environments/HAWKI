<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use App\Services\Storage\AvatarStorageService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Assistant\Fixtures\Assistant as AssistantFixture;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use AssistantFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createStoredAvatar(string $name = 'indigo'): AssistantAvatar
    {
        $uuid = Str::uuid()->toString();
        app(AvatarStorageService::class)->store(
            'png-contents',
            "{$uuid}.png",
            $uuid,
            AssistantAvatar::STORAGE_CATEGORY,
        );

        return AssistantAvatar::create(['uuid' => $uuid, 'name' => $name]);
    }

    private function updatePayload(Assistant $assistant, array $attributes): array
    {
        return [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => $attributes,
            ],
        ];
    }

    public function test_assistant_avatar_url_is_null_when_empty(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.avatar_url', null);
    }

    public function test_assistant_avatar_url_resolves_when_set(): void
    {
        $user = User::factory()->create();
        $avatar = $this->createStoredAvatar('indigo');
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'avatar_id' => $avatar->uuid,
        ]);
        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk();

        $this->assertNotNull($response->json('data.attributes.avatar_url'));
        $this->assertStringContainsString($avatar->uuid, $response->json('data.attributes.avatar_url'));
    }

    public function test_assistant_avatar_url_exposed_in_index(): void
    {
        $user = User::factory()->create();
        $avatar = $this->createStoredAvatar('sky');
        Assistant::factory()->create([
            'creator_id' => $user->id,
            'avatar_id' => $avatar->uuid,
        ]);
        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistants')
            ->assertOk()
            ->assertJsonPath(
                'data.0.attributes.avatar_url',
                app(AvatarStorageService::class)->getUrl($avatar->uuid, AssistantAvatar::STORAGE_CATEGORY),
            );
    }

    public function test_can_update_assistant_avatar_id_with_valid_uuid(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $avatar = $this->createStoredAvatar('amber');
        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'avatar_id' => $avatar->uuid,
        ]))
            ->assertOk();

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'avatar_id' => $avatar->uuid,
        ]);
    }

    public function test_cannot_update_assistant_avatar_id_with_invalid_uuid(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'avatar_id' => Str::uuid()->toString(),
        ]))
            ->assertStatus(422);
    }

    public function test_can_create_assistant_with_avatar_id(): void
    {
        $user = User::factory()->create();
        $avatar = $this->createStoredAvatar('rose');
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistants', $this->createJsonApiPayload([
            'avatar_id' => $avatar->uuid,
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('assistants', [
            'creator_id' => $user->id,
            'avatar_id' => $avatar->uuid,
        ]);
    }

    public function test_can_clear_assistant_avatar_id(): void
    {
        $user = User::factory()->create();
        $avatar = $this->createStoredAvatar('emerald');
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'avatar_id' => $avatar->uuid,
        ]);
        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", $this->updatePayload($assistant, [
            'avatar_id' => null,
        ]))
            ->assertOk();

        $this->assertDatabaseHas('assistants', [
            'id' => $assistant->id,
            'avatar_id' => null,
        ]);
    }
}
