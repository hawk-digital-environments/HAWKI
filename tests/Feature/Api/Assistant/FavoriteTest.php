<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_favorite_visible_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $response->assertJsonPath('data.attributes.is_favorite', true);

        $this->assertDatabaseHas('assistant_favorite_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
        ]);
    }

    public function test_can_unfavorite_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        $viewer->favoriteAssistants()->attach($assistant->id);

        Sanctum::actingAs($viewer);

        $response = $this->jsonApi('delete', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $response->assertJsonPath('data.attributes.is_favorite', false);

        $this->assertDatabaseMissing('assistant_favorite_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
        ]);
    }

    public function test_favorite_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $this->assertEquals(1, $viewer->favoriteAssistants()->count());
    }

    public function test_unfavorite_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->jsonApi('delete', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $this->assertEquals(0, $viewer->favoriteAssistants()->count());
    }

    public function test_creator_can_favorite_own_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertSuccessful();

        $this->assertDatabaseHas('assistant_favorite_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_cannot_favorite_private_assistant_of_other_user(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertForbidden();
    }

    public function test_cannot_unfavorite_private_assistant_of_other_user(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->jsonApi('delete', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertForbidden();
    }

    public function test_guest_cannot_favorite(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite")
            ->assertUnauthorized();
    }
}
