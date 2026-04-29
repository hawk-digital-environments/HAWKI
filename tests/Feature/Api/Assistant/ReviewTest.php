<?php

namespace Tests\Feature\Api\Assistant;

use App\Events\AssistantTriggerReleaseStatus;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\Review;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use App\Services\Assistant\Values\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $org = Organization::first();
        $user->organizations()->attach($org, ['role' => 'admin']);

        return $user;
    }

    private function createMember(): User
    {
        $user = User::factory()->create();
        $org = Organization::first();
        $user->organizations()->attach($org, ['role' => 'member']);

        return $user;
    }

    public function test_release_creates_pending_review(): void
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
                    'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseHas('reviews', [
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);
    }

    public function test_release_reuses_existing_review(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::APPROVED->value,
        ]);

        Sanctum::actingAs($user);

        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::FEDERATED->value,
                ],
            ],
        ])
            ->assertOk();

        $this->assertEquals(1, Review::where('assistant_id', $assistant->id)->count());
        $this->assertDatabaseHas('reviews', [
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);
    }

    public function test_release_to_private_does_not_create_review(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::PRIVATE->value,
        ]);

        Sanctum::actingAs($user);
        Event::fake(AssistantTriggerReleaseStatus::class);

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

        $this->assertDatabaseMissing('reviews', [
            'assistant_id' => $assistant->id,
        ]);
    }

    public function test_release_to_draft_does_not_create_review(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);

        Sanctum::actingAs($user);

        // No Event::fake: the AssistantReleaseStatus listener runs for real
        // so this genuinely verifies it skips review creation when the target
        // stage is draft (matching the existing behaviour for private).
        $this->jsonApi('patch', "/api/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => ReleaseStage::DRAFT->value,
                ],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseMissing('reviews', [
            'assistant_id' => $assistant->id,
        ]);
    }

    public function test_admin_can_approve_review(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->jsonApi('patch', "/api/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => ReviewStatus::APPROVED->value,
                ],
            ],
        ])
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $review->id,
                'type' => 'assistant-reviews',
                'attributes' => [
                    'status' => ReviewStatus::APPROVED->value,
                ],
            ],
        ]);

        $assistant->refresh();
        $this->assertEquals(ReleaseStage::ORGANIZATIONAL->value, $assistant->release_stage);
    }

    public function test_admin_can_deny_review(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->jsonApi('patch', "/api/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => ReviewStatus::DENIED->value,
                    'reason' => 'Not ready for release',
                ],
            ],
        ])
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $review->id,
                'type' => 'assistant-reviews',
                'attributes' => [
                    'status' => ReviewStatus::DENIED->value,
                    'reason' => 'Not ready for release',
                ],
            ],
        ]);

        $assistant->refresh();
        $this->assertEquals(ReleaseStage::PRIVATE->value, $assistant->release_stage);
    }

    public function test_deny_without_reason_returns_validation_error(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);

        Sanctum::actingAs($admin);

        $this->jsonApi('patch', "/api/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => ReviewStatus::DENIED->value,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/reason');
    }

    public function test_non_admin_cannot_update_review(): void
    {
        $member = $this->createMember();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);

        Sanctum::actingAs($member);

        $this->jsonApi('patch', "/api/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => ReviewStatus::APPROVED->value,
                ],
            ],
        ])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_reviews(): void
    {
        $this->jsonApi('patch', '/api/assistant-reviews/1', [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => '1',
                'attributes' => [
                    'status' => ReviewStatus::APPROVED->value,
                ],
            ],
        ])
            ->assertUnauthorized();
    }
}
