<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPromptsTest extends TestCase
{
    public function test_creator_can_add_prompts(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'add' => ['First prompt', 'Second prompt'],
                ],
            ],
        ])->assertSuccessful();

        $this->assertEquals(2, $assistant->user_prompts()->count());
        $this->assertDatabaseHas('user_prompts', [
            'assistant_id' => $assistant->id,
            'text' => 'First prompt',
        ]);
        $this->assertDatabaseHas('user_prompts', [
            'assistant_id' => $assistant->id,
            'text' => 'Second prompt',
        ]);
    }

    public function test_creator_can_remove_prompts(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->user_prompts()->createMany([
            ['text' => 'Keep this'],
            ['text' => 'Delete this'],
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'remove' => ['Delete this'],
                ],
            ],
        ])->assertSuccessful();

        $this->assertEquals(1, $assistant->user_prompts()->count());
        $this->assertDatabaseMissing('user_prompts', [
            'assistant_id' => $assistant->id,
            'text' => 'Delete this',
        ]);
        $this->assertDatabaseHas('user_prompts', [
            'assistant_id' => $assistant->id,
            'text' => 'Keep this',
        ]);
    }

    public function test_can_add_and_remove_simultaneously(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->user_prompts()->createMany([
            ['text' => 'Old prompt'],
            ['text' => 'Another old prompt'],
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'add' => ['New prompt'],
                    'remove' => ['Old prompt'],
                ],
            ],
        ])->assertSuccessful();

        $texts = $assistant->user_prompts()->pluck('text')->toArray();
        $this->assertCount(2, $texts);
        $this->assertContains('New prompt', $texts);
        $this->assertContains('Another old prompt', $texts);
        $this->assertNotContains('Old prompt', $texts);
    }

    public function test_remove_fails_if_text_not_found(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'remove' => ['Non-existent prompt'],
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/remove/0');
    }

    public function test_add_allows_duplicate_texts(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'add' => ['Duplicate', 'Duplicate'],
                ],
            ],
        ])->assertSuccessful();

        $this->assertEquals(2, $assistant->user_prompts()->count());
        $this->assertEquals(2, $assistant->user_prompts()->where('text', 'Duplicate')->count());
    }

    public function test_non_creator_cannot_update_prompts(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'add' => ['Hacked prompt'],
                ],
            ],
        ])->assertStatus(403);
    }

    public function test_empty_payload_is_noop(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->user_prompts()->create(['text' => 'Existing prompt']);
        $count = $assistant->user_prompts()->count();

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'add' => [],
                    'remove' => [],
                ],
            ],
        ])->assertSuccessful();

        $this->assertEquals($count, $assistant->user_prompts()->count());
    }

    public function test_response_includes_prompts_with_include_param(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $response = $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts?include=user_prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'add' => ['Included prompt'],
                ],
            ],
        ])->assertSuccessful();

        $response->assertJson([
            'included' => [
                [
                    'type' => 'user-prompts',
                    'attributes' => [
                        'text' => 'Included prompt',
                    ],
                ],
            ],
        ]);
    }

    public function test_remove_deletes_all_matching_prompts(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $assistant->user_prompts()->createMany([
            ['text' => 'Same text'],
            ['text' => 'Same text'],
            ['text' => 'Unique text'],
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/user-prompts", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'remove' => ['Same text'],
                ],
            ],
        ])->assertSuccessful();

        $texts = $assistant->user_prompts()->pluck('text')->toArray();
        $this->assertEquals(['Unique text'], $texts);
    }
}
