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

        $this->postJson("/api/assistants/{$assistant->id}/release", [
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
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

        $this->postJson("/api/assistants/{$assistant->id}/release", [
            'release_stage' => ReleaseStage::FEDERATED->value,
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

        $this->postJson("/api/assistants/{$assistant->id}/release", [
            'release_stage' => ReleaseStage::PRIVATE->value,
        ])
            ->assertOk();

        $this->assertDatabaseMissing('reviews', [
            'assistant_id' => $assistant->id,
        ]);
    }

    public function test_admin_can_list_reviews_with_assistant(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/assistant-review?include=assistant')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJson([
            'data' => [
                [
                    'type' => 'reviews',
                    'attributes' => [
                        'status' => ReviewStatus::PENDING->value,
                    ],
                    'relationships' => [
                        'assistant' => [
                            'data' => [
                                'id' => (string) $assistant->id,
                                'type' => 'assistants',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $assistantResource = $included->first(fn ($item) => $item['type'] === 'assistants');
        $this->assertEquals($assistant->name, $assistantResource['attributes']['name']);
    }

    public function test_admin_can_list_reviews_without_assistant(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => ReleaseStage::ORGANIZATIONAL->value,
        ]);
        Review::create([
            'assistant_id' => $assistant->id,
            'status' => ReviewStatus::PENDING->value,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/assistant-review')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonMissingPath('data.0.relationships');
        $response->assertJsonMissingPath('included');
    }

    public function test_non_admin_cannot_list_reviews(): void
    {
        $member = $this->createMember();

        Sanctum::actingAs($member);

        $this->getJson('/api/assistant-review')
            ->assertForbidden();
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

        $response = $this->putJson("/api/assistant-review/{$review->id}", [
            'status' => ReviewStatus::APPROVED->value,
        ])
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $review->id,
                'type' => 'reviews',
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

        $response = $this->putJson("/api/assistant-review/{$review->id}", [
            'status' => ReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ])
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $review->id,
                'type' => 'reviews',
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

        $this->putJson("/api/assistant-review/{$review->id}", [
            'status' => ReviewStatus::DENIED->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
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

        $this->putJson("/api/assistant-review/{$review->id}", [
            'status' => ReviewStatus::APPROVED->value,
        ])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_reviews(): void
    {
        $this->getJson('/api/assistant-review')
            ->assertUnauthorized();

        $this->putJson('/api/assistant-review/1', [
            'status' => ReviewStatus::APPROVED->value,
        ])
            ->assertUnauthorized();
    }
}
