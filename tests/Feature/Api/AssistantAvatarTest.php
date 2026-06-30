<?php

namespace Tests\Feature\Api;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantAvatarTest extends TestCase
{
    use RefreshDatabase;

    private const DOTS_PATTERN = 'background-color: #E5E5F7; opacity: 0.8; background: radial-gradient(#444CF7 15%, transparent 16%) 0 0, radial-gradient(#444CF7 15%, transparent 16%) 5px 5px, radial-gradient(#444CF733 15%, transparent 20%) 0 1px, radial-gradient(#444CF733 15%, transparent 20%) 5px 6px; background-size: 10px 10px;';

    public function test_owner_can_create_avatar_for_their_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])
            ->assertCreated();

        $this->assertDatabaseHas('assistant_avatars', [
            'assistant_id' => $assistant->id,
            'name' => '🧠',
        ]);
    }

    public function test_non_owner_cannot_create_avatar(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->jsonApi('post', '/api/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();
    }

    public function test_create_requires_assistant_relationship(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->jsonApi('post', '/api/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
            ],
        ])->assertStatus(422);
    }

    public function test_assistant_can_have_only_one_avatar(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);
        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-avatars', [
            'data' => [
                'type' => 'assistant-avatars',
                'attributes' => ['name' => '🧠', 'icon_css' => self::DOTS_PATTERN],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_index_is_scoped_to_visible_assistants(): void
    {
        $ownerA = User::factory()->create();
        $publicAssistant = Assistant::factory()->create([
            'creator_id' => $ownerA->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $publicAvatar = AssistantAvatar::factory()->create(['assistant_id' => $publicAssistant->id]);

        $ownerB = User::factory()->create();
        $privateAssistant = Assistant::factory()->create([
            'creator_id' => $ownerB->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);
        $privateAvatar = AssistantAvatar::factory()->create(['assistant_id' => $privateAssistant->id]);

        Sanctum::actingAs(User::factory()->create());

        $ids = collect($this->jsonApi('get', '/api/assistant-avatars')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertContains((string) $publicAvatar->id, $ids);
        $this->assertNotContains((string) $privateAvatar->id, $ids);
    }

    public function test_show_is_gated_by_assistant_visibility(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        Sanctum::actingAs($owner);
        $this->jsonApi('get', "/api/assistant-avatars/{$avatar->id}")->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->jsonApi('get', "/api/assistant-avatars/{$avatar->id}")->assertForbidden();
    }

    public function test_update_and_delete_are_owner_only(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        Sanctum::actingAs(User::factory()->create());
        $this->jsonApi('patch', "/api/assistant-avatars/{$avatar->id}", [
            'data' => [
                'type' => 'assistant-avatars',
                'id' => (string) $avatar->id,
                'attributes' => ['name' => '💡'],
            ],
        ])->assertForbidden();

        $this->jsonApi('delete', "/api/assistant-avatars/{$avatar->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->jsonApi('patch', "/api/assistant-avatars/{$avatar->id}", [
            'data' => [
                'type' => 'assistant-avatars',
                'id' => (string) $avatar->id,
                'attributes' => ['name' => '💡'],
            ],
        ])->assertOk();

        $this->jsonApi('delete', "/api/assistant-avatars/{$avatar->id}")->assertNoContent();
    }

    public function test_avatar_assistant_link_does_not_leak_private_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        Sanctum::actingAs($owner);
        $this->jsonApi('get', "/api/assistant-avatars/{$avatar->id}/assistant")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);

        Sanctum::actingAs(User::factory()->create());
        $this->jsonApi('get', "/api/assistant-avatars/{$avatar->id}/assistant")
            ->assertForbidden();
    }
}
