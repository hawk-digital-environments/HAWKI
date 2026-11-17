<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\User;
use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function testReleaseRequestRecordsDesiredStageWhenNotApproved(): void
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
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertOk();

        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        Event::assertNotDispatched(AssistantReleaseStageChangedEvent::class);
    }

    public function testReleaseRecordsVersionNoteOnLatestVersion(): void
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
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                    'note' => '  Reworked the system prompt  ',
                ],
            ],
        ])
            ->assertOk();

        $version = $assistant->assistantVersions()->latest('version')->first();
        static::assertSame('Reworked the system prompt', $version->text);
    }

    public function testReleaseRejectsTooLongNote(): void
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
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                    'note' => str_repeat('a', 2001),
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/note');
    }

    public function testItAlwaysOpensAFreshReviewForAnUpwardReleaseEvenWhenApproved(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
        ]);

        $this->actingAsUser($user);
        Event::fake(AssistantReleaseStageChangedEvent::class);

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

        // An approved review only counts as current while the assistant is
        // public. Once it has fallen back to private, the record never takes
        // effect by itself: the upward release opens a fresh review and the
        // assistant stays private until an admin approves it.
        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);
        static::assertSame(AssistantReviewStatus::PENDING, AssistantReview::where('assistant_id', $assistant->id)->sole()->status);

        Event::assertNotDispatched(AssistantReleaseStageChangedEvent::class);
    }

    public function testReleaseResponseReflectsFavoritedState(): void
    {
        // The release action response must carry the per-user is_favorite
        // flag. The schema's loaderFor hook is what populates it on this
        // path; this test pins that the controller routes the response
        // through the store's queryOne rather than DataResponse::make on a
        // bare model.
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::FEDERATED->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::APPROVED->value,
        ]);
        $user->favoriteAssistants()->attach($assistant->id);

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
            ->assertOk()
            ->assertJsonPath('data.attributes.is_favorite', true);
    }

    public function testItAppliesDownwardPublicMovesFreely(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::FEDERATED->value,
        ]);

        $this->actingAsUser($user);
        Event::fake(AssistantReleaseStageChangedEvent::class);

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
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);

        Event::assertDispatched(AssistantReleaseStageChangedEvent::class, static function (AssistantReleaseStageChangedEvent $event): bool {
            return AssistantReleaseStage::FEDERATED === $event->oldStage
                && AssistantReleaseStage::ORGANIZATIONAL === $event->newStage;
        });
    }

    public function testItRequiresFreshApprovalForEscalationBetweenPublicStages(): void
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
        Event::fake(AssistantReleaseStageChangedEvent::class);

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

        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::FEDERATED, $assistant->requested_release_stage);

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);

        Event::assertNotDispatched(AssistantReleaseStageChangedEvent::class);
    }

    public function testCannotReleaseOthersAssistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertForbidden();
    }

    public function testGuestCannotReleaseAssistant(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => [
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertForbidden();
    }

    public function testItDoesNotDispatchTheEventForASameStageRelease(): void
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

        Event::assertNotDispatched(AssistantReleaseStageChangedEvent::class);
    }

    public function testReleaseWithInvalidStageReturnsValidationError(): void
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
                    'release_stage' => 'invalid',
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/release_stage');
    }

    public function testItCannotSubmitForPublicationWithADeniedReview(): void
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
                    'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/release_stage');
    }

    public function testItCannotFederateAnAssistantWithADeniedReview(): void
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
                    'release_stage' => AssistantReleaseStage::FEDERATED->value,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/release_stage');
    }

    public function testItCanSubmitForPublicationAfterAdminClearsTheDeniedReview(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        $review = AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
            'reason' => 'Not ready for release',
        ]);

        $this->actingAsUser($user);

        // Admin manually clears the denial back to pending.
        $review->status = AssistantReviewStatus::PENDING->value;
        $review->reason = null;
        $review->save();

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

        // Still pending approval, so the assistant stays private but records
        // the desired stage and keeps the review open.
        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);

        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
    }
}
