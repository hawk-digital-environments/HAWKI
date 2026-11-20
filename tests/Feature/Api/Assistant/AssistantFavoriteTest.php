<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantFavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function testCanFavoriteVisibleAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        $this->actingAsUser($viewer);

        $response = $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $response->assertJsonPath('data.attributes.is_favorite', true);

        $this->assertDatabaseHas('assistant_favorite_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
        ]);
    }

    public function testCanUnfavoriteAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        $viewer->favoriteAssistants()->attach($assistant->id);

        $this->actingAsUser($viewer);

        $response = $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $response->assertJsonPath('data.attributes.is_favorite', false);

        $this->assertDatabaseMissing('assistant_favorite_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
        ]);
    }

    public function testFavoriteIsIdempotent(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        $this->actingAsUser($viewer);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        self::assertEquals(1, $viewer->favoriteAssistants()->count());
    }

    public function testUnfavoriteIsIdempotent(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        $this->actingAsUser($viewer);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        self::assertEquals(0, $viewer->favoriteAssistants()->count());
    }

    public function testCreatorCanFavoriteOwnAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $this->assertDatabaseHas('assistant_favorite_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
        ]);
    }

    public function testCannotFavoritePrivateAssistantOfOtherUser(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $otherUser = User::factory()->create();
        $this->actingAsUser($otherUser);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertForbidden();
    }

    public function testCannotUnfavoritePrivateAssistantOfOtherUser(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $otherUser = User::factory()->create();
        $this->actingAsUser($otherUser);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertForbidden();
    }

    public function testGuestCannotFavorite(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/favorite")
            ->assertStatus(403);
    }
}
