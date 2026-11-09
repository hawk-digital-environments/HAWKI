<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\Assistants\AssistantVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
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
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);

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
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::FEDERATED, $assistant->requested_release_stage);

        static::assertEquals(1, AssistantReview::where('assistant_id', $assistant->id)->count());
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

    public function testItShowsTheCreatorTheReviewStateOfTheirOwnSubmission(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        $this->actingAsUser($user);

        // The admin menu is gated by the `actions/permissions` probe, but the
        // creator-facing read path stays open: the assistant's
        // assistant_review include is how the dashboard surfaces the
        // submission's state.
        $show = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_review")
            ->assertOk();

        $included = collect($show->json('included') ?? [])
            ->first(static fn ($item) => $item['type'] === 'assistant-reviews');
        static::assertNotNull($included, 'include=assistant_review did not surface the review for its creator');
        static::assertSame((string) $review->id, $included['id']);
        static::assertSame(AssistantReviewStatus::PENDING->value, $included['attributes']['status']);
    }

    public function testItDoesNotShowAStrangerTheReviewStateOfSomeoneElsesAssistant(): void
    {
        $creator = User::factory()->create();
        $stranger = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        // The privileged include 403s the whole fetch for non-privileged
        // viewers — even for an assistant the stranger can otherwise see.
        $this->actingAsUser($stranger);
        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_review")
            ->assertForbidden();

        // Without the include the detail stays readable; no review leaks.
        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertOk()
            ->assertJsonMissing(['type' => 'assistant-reviews']);
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
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);

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
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
    }

    public function testAnOrgAdminCannotDecideAnotherOrganizationsReview(): void
    {
        $foreignOrg = Organization::create(['name' => 'Other University']);
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'organization_id' => $foreignOrg->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        // createAdmin() administers the default org; the review belongs to the other one.
        $this->actingAsUser($this->createAdmin());

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

        // The decision never landed: the review stays pending.
        $this->assertDatabaseHas('assistant_reviews', [
            'id' => $review->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
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
        static::assertSame(AssistantReleaseStage::FEDERATED, $assistant->release_stage);
        static::assertNull($assistant->requested_release_stage);

        Event::assertDispatched(AssistantReleaseStageChangedEvent::class, static function (AssistantReleaseStageChangedEvent $event): bool {
            return AssistantReleaseStage::PRIVATE === $event->oldStage
                && AssistantReleaseStage::FEDERATED === $event->newStage;
        });
    }

    public function testItRevokesAPublishedAssistantWhenItsApprovalIsDenied(): void
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

        // Approve a publication request: the assistant goes public.
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
                'attributes' => ['status' => AssistantReviewStatus::APPROVED->value],
            ],
        ])->assertOk();

        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->refresh()->release_stage);

        // Revocation: denying the approved review demotes the assistant back
        // to private and records who revoked it, when, and why.
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::DENIED->value,
                    'reason' => 'Revoked after publication',
                ],
            ],
        ])->assertOk();

        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        static::assertNull($assistant->requested_release_stage);

        $review->refresh();
        static::assertSame(AssistantReviewStatus::DENIED, $review->status);
        static::assertSame('Revoked after publication', $review->reason);
        static::assertSame($admin->id, $review->reviewer_id);
        static::assertNotNull($review->reviewed_at);

        Event::assertDispatched(AssistantReleaseStageChangedEvent::class, static function (AssistantReleaseStageChangedEvent $event): bool {
            return AssistantReleaseStage::ORGANIZATIONAL === $event->oldStage
                && AssistantReleaseStage::PRIVATE === $event->newStage;
        });
    }

    public function testItUnblocksTheAssistantWhenADeniedReviewIsReApproved(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'name' => 'Denied Once',
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        // Creator submits, admin denies: the denial blocks any resubmission.
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
                    'reason' => 'Not yet',
                ],
            ],
        ])->assertOk();

        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->refresh()->release_stage);

        // A denied review blocks a resubmission entirely — the request
        // validator rejects it until an admin clears the denial.
        $this->actingAsUser($user);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])->assertUnprocessable();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->refresh()->release_stage);

        // Re-approval clears the denial: the frozen submission publishes as
        // v1.0 and the creator is unblocked.
        $this->actingAsUser($admin);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::APPROVED->value,
                ],
            ],
        ])->assertOk();

        $review->refresh();
        static::assertSame(AssistantReviewStatus::APPROVED, $review->status);
        static::assertNull($review->reason);
        static::assertSame($admin->id, $review->reviewer_id);

        $published = AssistantVersion::query()
            ->where('assistant_id', $assistant->id)
            ->where('version', '>', 0)
            ->get();
        static::assertCount(1, $published);
        static::assertSame('1.0', $published->first()->version);
        static::assertSame('Denied Once', $published->first()->payload['attributes']['name']);

        // The denial is gone, but the re-approval never takes effect by
        // itself: the next publication request opens a fresh review.
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

        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);
        static::assertSame(AssistantReviewStatus::PENDING, $review->refresh()->status);
    }

    public function testItCanLiftADenialViaNeedsRevision(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        // Submit and deny: the creator is hard-blocked from resubmitting.
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
                    'reason' => 'Not ready',
                ],
            ],
        ])->assertOk();

        $this->actingAsUser($user);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])->assertUnprocessable();

        // The admin lifts the denial by requesting revision: a status-only
        // change (needs_revision is a denial variant, no transition fires)
        // that re-allows resubmission.
        $this->actingAsUser($admin);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::NEEDS_REVISION->value,
                    'reason' => 'Rework the greeting and resubmit',
                ],
            ],
        ])->assertOk();

        static::assertSame(AssistantReviewStatus::NEEDS_REVISION, $review->refresh()->status);
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->refresh()->release_stage);

        // The creator resubmits: the review reopens fresh on the new state.
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

        $review->refresh();
        static::assertSame(AssistantReviewStatus::PENDING, $review->status);
        static::assertNull($review->reason);
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->refresh()->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->refresh()->requested_release_stage);
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
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        static::assertNull($assistant->requested_release_stage);

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
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
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

    public function testNeedsRevisionKeepsReviewAndAllowsResubmission(): void
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
                    'status' => AssistantReviewStatus::NEEDS_REVISION->value,
                    'reason' => 'Please shorten the system prompt',
                ],
            ],
        ])
            ->assertOk();

        // Like a denial, a revision request demotes the assistant to private…
        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);

        // …but unlike a denial the review survives — including the demotion it
        // just triggered — so the creator can see the reason and resubmit.
        $this->assertDatabaseHas('assistant_reviews', [
            'id' => $review->id,
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::NEEDS_REVISION->value,
            'reason' => 'Please shorten the system prompt',
        ]);

        // The creator resubmits: the review reopens as pending with the old
        // denial reason cleared, and the requested stage is recorded again.
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

        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);

        $this->assertDatabaseHas('assistant_reviews', [
            'id' => $review->id,
            'status' => AssistantReviewStatus::PENDING->value,
            'reason' => null,
        ]);
    }

    public function testNeedsRevisionWithoutReasonReturnsValidationError(): void
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
                    'status' => AssistantReviewStatus::NEEDS_REVISION->value,
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

    public function testItCannotAccessReviewsAsGuest(): void
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
