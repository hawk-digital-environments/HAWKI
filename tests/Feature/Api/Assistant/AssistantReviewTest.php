<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantReviewTest extends TestCase
{
    use RefreshDatabase;

    public function testReleaseCreatesPendingReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertOk();

        // Pending approval: the assistant stays private but records the desired
        // stage and opens a pending review.
        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        self::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
    }

    public function testEscalationReopensExistingReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::FEDERATED->value,
                ],
            ],
        ])
            ->assertOk();

        // Escalation between public stages requires a fresh approval: the
        // existing review is reopened and the assistant stays put.
        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
        self::assertSame(AssistantReleaseStage::FEDERATED, $assistant->requested_release_stage);

        self::assertEquals(1, AssistantReview::where('assistant_id', $assistant->id)->count());
        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
    }

    public function testReleaseToPrivateDoesNotCreateReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        Event::fake(AssistantReleaseStageChangedEvent::class);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::PRIVATE->value,
                ],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseMissing('assistant_reviews', [
            'assistant_id' => $assistant->id,
        ]);
    }

    public function testReleaseToDraftDoesNotCreateReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->actingAsUser($user);

        // No Event::fake: the AssistantReleaseStatus listener runs for real
        // so this genuinely verifies it skips review creation when the target
        // stage is draft (matching the existing behaviour for private).
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::DRAFT->value,
                ],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseMissing('assistant_reviews', [
            'assistant_id' => $assistant->id,
        ]);
    }

    public function testTransitioningToPrivateDeletesNonDeniedReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
            'reason' => 'Looks good',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::PRIVATE->value,
                ],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
    }

    public function testTransitioningToDraftDeletesNonDeniedReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::DRAFT->value,
                ],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseMissing('assistant_reviews', ['assistant_id' => $assistant->id]);
    }

    public function testTransitioningToPrivateKeepsDeniedReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::PRIVATE->value,
                ],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ]);
    }

    public function testDenyFlowKeepsDeniedReview(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->actingAsUser($admin);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::DENIED->value,
                    'reason' => 'Not ready for release',
                ],
            ],
        ])
            ->assertOk();

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);

        // The denied review must survive so an admin can manually clear it later.
        $this->assertDatabaseHas('assistant_reviews', [
            'id' => $review->id,
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
        ]);
    }

    public function testAdminCanApproveReview(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->actingAsUser($admin);

        $response = $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::APPROVED->value,
                ],
            ],
        ])
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => (string) $review->id,
                'type' => 'assistant-reviews',
                'attributes' => [
                    'status' => AssistantReviewStatus::APPROVED->value,
                ],
            ],
        ]);

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
    }

    public function testApprovePromotesAssistantToRequestedStage(): void
    {
        // Fake up-front: AssistantService holds the Dispatcher via DI, so the
        // fake must be bound before any request constructs the singleton.
        Event::fake(AssistantReleaseStageChangedEvent::class);

        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        // Creator submits a publication request: review opens as pending and
        // the desired stage is recorded while the assistant stays private.
        $this->actingAsUser($user);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::FEDERATED->value,
                ],
            ],
        ])->assertOk();

        $review = AssistantReview::where('assistant_id', $assistant->id)->sole();

        $this->actingAsUser($admin);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::APPROVED->value,
                ],
            ],
        ])
            ->assertOk();

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::FEDERATED, $assistant->release_stage);
        self::assertNull($assistant->requested_release_stage);

        Event::assertDispatched(AssistantReleaseStageChangedEvent::class, static function (AssistantReleaseStageChangedEvent $event): bool {
            return AssistantReleaseStage::PRIVATE === $event->oldStage
                && AssistantReleaseStage::FEDERATED === $event->newStage;
        });
    }

    public function testDenyClearsRequestedStage(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $this->actingAsUser($user);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])->assertOk();

        $review = AssistantReview::where('assistant_id', $assistant->id)->sole();

        $this->actingAsUser($admin);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::DENIED->value,
                    'reason' => 'Not ready for release',
                ],
            ],
        ])
            ->assertOk();

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        self::assertNull($assistant->requested_release_stage);

        $this->assertDatabaseHas('assistant_reviews', [
            'id' => $review->id,
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
        ]);
    }

    public function testAdminCanDenyReview(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->actingAsUser($admin);

        $response = $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::DENIED->value,
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
                    'status' => AssistantReviewStatus::DENIED->value,
                    'reason' => 'Not ready for release',
                ],
            ],
        ]);

        $assistant->refresh();
        self::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
    }

    public function testDenyWithoutReasonReturnsValidationError(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->actingAsUser($admin);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::DENIED->value,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/reason');
    }

    public function testNonAdminCannotUpdateReview(): void
    {
        $member = $this->createMember();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->actingAsUser($member);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::APPROVED->value,
                ],
            ],
        ])
            ->assertForbidden();
    }

    public function testGuestCannotAccessReviews(): void
    {
        $this->jsonApiRaw('patch', '/api/hawki/v1/assistant-reviews/1', [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => '1',
                'attributes' => [
                    'status' => AssistantReviewStatus::APPROVED->value,
                ],
            ],
        ])
            ->assertStatus(404);
    }

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
}
