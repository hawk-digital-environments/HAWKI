<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_add_feedback_to_visible_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $response = $this->jsonApi('post', '/api/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Great assistant!'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.type', 'assistant-feedback');
        $response->assertJsonPath('data.attributes.text', 'Great assistant!');
        $this->assertNotNull($response->json('data.id'));

        $this->assertDatabaseHas('feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
            'text' => 'Great assistant!',
        ]);
    }

    public function test_creator_can_add_feedback_to_own_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'private',
        ]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'My own feedback'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertCreated();

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

        $this->jsonApi('post', '/api/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Should fail'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertForbidden();

        $this->assertEquals(0, Feedback::count());
    }

    public function test_feedback_requires_text(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->jsonApi('post', '/api/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => (object) [],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertStatus(422);

        $this->assertEquals(0, Feedback::count());
    }

    public function test_guest_cannot_add_feedback(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->jsonApi('post', '/api/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Guest feedback'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                ],
            ],
        ])->assertUnauthorized();
    }

    public function test_client_cannot_set_author(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        Sanctum::actingAs($attacker);

        // Attempt to spoof the author relationship; server must ignore it.
        $this->jsonApi('post', '/api/assistant-feedback', [
            'data' => [
                'type' => 'assistant-feedback',
                'attributes' => ['text' => 'Spoofed'],
                'relationships' => [
                    'assistant' => ['data' => ['type' => 'assistants', 'id' => (string) $assistant->id]],
                    'user' => ['data' => ['type' => 'users', 'id' => (string) $owner->id]],
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('feedback', [
            'assistant_id' => $assistant->id,
            'user_id' => $attacker->id,
        ]);
    }
}
