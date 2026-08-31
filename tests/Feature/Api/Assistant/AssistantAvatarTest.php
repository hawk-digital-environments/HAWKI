<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function testAssistantAvatarRelationshipIsNullWhenNone(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $this->actingAsUser($user);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonPath('data.relationships.assistant_avatar.data', null);
    }

    public function testIncludeAssistantAvatarReturnsTheAvatar(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $avatar = AssistantAvatar::factory()->create([
            'assistant_id' => $assistant->id,
            'name' => '🧠',
        ]);
        $this->actingAsUser($user);

        $response = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_avatar")
            ->assertOk();

        $response->assertJsonPath('data.relationships.assistant_avatar.data', [
            'type' => 'assistant-avatars',
            'id' => (string) $avatar->id,
        ]);

        $included = collect($response->json('included'));
        $avatarResource = $included->firstWhere('type', 'assistant-avatars');
        self::assertNotNull($avatarResource);
        self::assertSame('🧠', $avatarResource['attributes']['name']);
    }

    public function testAssistantAvatarRelatedUrlReturnsTheAvatar(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);
        $avatar = AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);
        $this->actingAsUser($user);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/assistant-avatar")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $avatar->id)
            ->assertJsonPath('data.type', 'assistant-avatars');
    }

    public function testAssistantAvatarIsVisibleToAnyViewer(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantAvatar::factory()->create(['assistant_id' => $assistant->id]);

        $viewer = User::factory()->create();
        $this->actingAsUser($viewer);

        // An unrelated viewer of a public assistant can include its avatar.
        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_avatar")
            ->assertOk()
            ->assertJsonPath('data.relationships.assistant_avatar.data.type', 'assistant-avatars');

        // …and fetch it via the related URL.
        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/assistant-avatar")
            ->assertOk();
    }
}
