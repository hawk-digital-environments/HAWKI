<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_avatar_relationship_is_null_when_none(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.relationships.assistant_avatar.data', null);
    }

    public function test_include_assistant_avatar_returns_the_avatar(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $avatar = AssistantAvatar::factory()->create([
            'assistant_id' => $assistant->id,
            'name' => '🧠',
        ]);
        Sanctum::actingAs($user);

        $response = $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=assistant_avatar")
            ->assertOk();

        $response->assertJsonPath('data.relationships.assistant_avatar.data', [
            'type' => 'assistant-avatars',
            'id' => (string) $avatar->id,
        ]);

        $included = collect($response->json('included'));
        $avatarResource = $included->firstWhere('type', 'assistant-avatars');
        $this->assertNotNull($avatarResource);
        $this->assertSame('🧠', $avatarResource['attributes']['name']);
    }

    public function test_assistant_avatar_related_url_returns_the_avatar(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);
        Sanctum::actingAs($user);

        $this->jsonApi('get', "/api/assistants/{$assistant->id}/assistant-avatar")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $avatar->id)
            ->assertJsonPath('data.type', 'assistant-avatars');
    }

    public function test_assistant_avatar_is_visible_to_any_viewer(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        // An unrelated viewer of a public assistant can include its avatar.
        $this->jsonApi('get', "/api/assistants/{$assistant->id}?include=assistant_avatar")
            ->assertOk()
            ->assertJsonPath('data.relationships.assistant_avatar.data.type', 'assistant-avatars');

        // …and fetch it via the related URL.
        $this->jsonApi('get', "/api/assistants/{$assistant->id}/assistant-avatar")
            ->assertOk();
    }
}
