<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_feedback_to_visible_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/feedback", [
            'data' => [
                'type' => 'assistants',
                'attributes' => [
                    'text' => 'Great assistant!',
                ],
            ],
        ])
            ->assertSuccessful();

        $this->assertDatabaseHas('feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
            'text' => 'Great assistant!',
        ]);

        $this->assertEquals(1, $assistant->feedback()->count());
    }

    public function test_creator_can_add_feedback_to_own_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/feedback", [
            'data' => [
                'type' => 'assistants',
                'attributes' => [
                    'text' => 'My own feedback',
                ],
            ],
        ])
            ->assertSuccessful();

        $this->assertDatabaseHas('feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $owner->id,
            'text' => 'My own feedback',
        ]);
    }

    public function test_cannot_add_feedback_to_private_assistant_of_other_user(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/feedback", [
            'data' => [
                'type' => 'assistants',
                'attributes' => [
                    'text' => 'Should fail',
                ],
            ],
        ])
            ->assertForbidden();

        $this->assertEquals(0, Feedback::count());
    }

    public function test_feedback_requires_text(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/feedback", [
            'data' => [
                'type' => 'assistants',
                'attributes' => [],
            ],
        ])
            ->assertStatus(422);

        $this->assertEquals(0, Feedback::count());
    }

    public function test_guest_cannot_add_feedback(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'public',
        ]);

        $this->jsonApi('post', "/api/assistants/{$assistant->id}/actions/feedback", [
            'data' => [
                'type' => 'assistants',
                'attributes' => [
                    'text' => 'Guest feedback',
                ],
            ],
        ])
            ->assertUnauthorized();
    }
}
