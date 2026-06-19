<?php

namespace Tests\Feature\Api;

use App\Models\Assistants\AssistantAvatar;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Tests\TestCase;

class AssistantAvatarTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_guest_cannot_list_assistant_avatars(): void
    {
        $this->jsonApi('get', '/api/assistant-avatars')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_list_assistant_avatars(): void
    {
        $user = User::factory()->create();
        $avatar = $this->createStoredAvatar('indigo');
        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistant-avatars')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJson([
            'data' => [
                [
                    'id' => (string) $avatar->id,
                    'type' => 'assistant-avatars',
                    'attributes' => [
                        'name' => 'indigo',
                    ],
                ],
            ],
        ]);
    }

    public function test_url_attribute_is_resolved(): void
    {
        $user = User::factory()->create();
        $avatar = $this->createStoredAvatar('sky');
        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistant-avatars')
            ->assertOk();

        $this->assertNotNull($response->json('data.0.attributes.url'));
        $this->assertStringContainsString($avatar->uuid, $response->json('data.0.attributes.url'));
    }

    public function test_url_attribute_is_null_when_no_file_exists(): void
    {
        $user = User::factory()->create();
        $avatar = AssistantAvatar::create(['uuid' => Str::uuid()->toString(), 'name' => 'orphan']);
        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistant-avatars')
            ->assertOk()
            ->assertJsonPath('data.0.attributes.url', null);
    }

    public function test_can_show_assistant_avatar(): void
    {
        $user = User::factory()->create();
        $avatar = $this->createStoredAvatar('emerald');
        Sanctum::actingAs($user);

        $this->jsonApi('get', "/api/assistant-avatars/{$avatar->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => (string) $avatar->id,
                    'type' => 'assistant-avatars',
                    'attributes' => [
                        'name' => 'emerald',
                    ],
                ],
            ]);
    }

    public function test_empty_list_returns_empty_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('get', '/api/assistant-avatars')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_list_supports_pagination(): void
    {
        $user = User::factory()->create();
        AssistantAvatar::factory()->count(20)->create();
        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', '/api/assistant-avatars?' . http_build_query(['page' => ['size' => 5]]))
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $response->assertJsonStructure([
            'meta' => ['page' => ['currentPage', 'from', 'to', 'perPage', 'lastPage', 'total']],
            'links' => ['first', 'last', 'next'],
        ]);
    }
}
