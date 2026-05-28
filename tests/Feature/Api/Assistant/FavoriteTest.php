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
            'release_stage' => 'public',
        ]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => true,
                ],
            ],
        ])
            ->assertSuccessful();

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
            'release_stage' => 'public',
        ]);

        $viewer = User::factory()->create();
        $viewer->favoriteAssistants()->attach($assistant->id);

        Sanctum::actingAs($viewer);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => false,
                ],
            ],
        ])
            ->assertSuccessful();

        $this->assertDatabaseMissing('assistant_favorite_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
        ]);
    }

    public function test_mark_favorite_is_unique_for_specific_user(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => true,
                ],
            ],
        ])
            ->assertSuccessful();

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => true,
                ],
            ],
        ])
            ->assertSuccessful();

        $this->assertEquals(1, $viewer->favoriteAssistants()->count());
    }

    public function test_creator_can_favorite_own_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => true,
                ],
            ],
        ])
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

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => true,
                ],
            ],
        ])
            ->assertForbidden();

        $this->assertEquals(0, $otherUser->favoriteAssistants()->count());
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

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => false,
                ],
            ],
        ])
            ->assertForbidden();
    }

    public function test_guest_cannot_favorite(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => true,
                ],
            ],
        ])
            ->assertUnauthorized();
    }

    public function test_favorite_requires_is_favorite_attribute(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [],
            ],
        ])
            ->assertStatus(422);
    }

    public function test_favorite_validates_is_favorite_is_boolean(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => 'not-a-boolean',
                ],
            ],
        ])
            ->assertStatus(422);
    }

    public function test_favorite_response_includes_is_favorite_true(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => true,
                ],
            ],
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.attributes.is_favorite', true);
    }

    public function test_unfavorite_response_includes_is_favorite_false(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        $owner->favoriteAssistants()->attach($assistant->id);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/favorite", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'is_favorite' => false,
                ],
            ],
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.attributes.is_favorite', false);
    }
}
