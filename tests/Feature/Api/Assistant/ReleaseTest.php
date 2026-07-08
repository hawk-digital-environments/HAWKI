<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_release_assistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($user);

        $response = $this->jsonApi('patch', "/api/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ]);

        $response->assertOk();

        $assistant->refresh();
        $this->assertEquals(ReleaseStage::ORGANIZATIONAL->value, $assistant->release_stage);

        // Release to organizational/federated creates a pending review.
        $this->assertDatabaseHas('reviews', [
            'assistant_id' => $assistant->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_release_others_assistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($other);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertForbidden()
            ->assertJson(['errors' => [['detail' => 'This action is unauthorized.']]]);
    }

    public function test_guest_cannot_release_assistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertUnauthorized();
    }

    public function test_release_with_same_stage_does_not_dispatch_event(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::PRIVATE->value,
                ],
            ],
        ])
            ->assertOk();

        // No review created since the stage didn't change.
        $this->assertDatabaseMissing('reviews', [
            'assistant_id' => $assistant->id,
            'status' => 'pending',
        ]);
    }

    public function test_release_with_invalid_stage_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => 'invalid',
                ],
            ],
        ])
            ->assertUnprocessable();
    }
}
