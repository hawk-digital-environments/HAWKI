<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * The versioned review workflow: every assistant carries a mutable working
 * draft at version 0.0; submitting freezes the state onto it (every submit
 * overwrites, including changes to a publicly released assistant); approving
 * promotes the draft to the next published version and respawns a fresh
 * draft; rollback restores an earlier published version and deletes every
 * version above it.
 */
#[CoversNothing()]
class AssistantVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function testItSeedsAWorkingDraftFromTheFactory(): void
    {
        $assistant = Assistant::factory()->create();

        $versions = $assistant->assistantVersions()->get();
        static::assertCount(1, $versions);
        static::assertSame('0.0', $versions->first()->version);
        static::assertSame('', $versions->first()->text);
    }

    public function testItFreezesTheSubmittedStateOnTheDraft(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'name' => 'Frozen Name',
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'First submit');

        $draft = $this->draftOf($assistant);
        static::assertSame('First submit', $draft->text);
        static::assertSame('Frozen Name', $draft->payload['attributes']['name']);

        // The creator edits after submitting: the review keeps judging the
        // frozen state, only the changed keys move.
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Edited After Submit'],
            ],
        ])->assertOk();

        $draft = $this->draftOf($assistant);
        static::assertSame('Frozen Name', $draft->payload['attributes']['name']);
        static::assertEquals(['name'], $draft->changed_keys);
    }

    public function testItOverwritesTheFrozenStateOnResubmit(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'First submit');
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Second Attempt'],
            ],
        ])->assertOk();
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'Take two');

        $draft = $this->draftOf($assistant);
        static::assertSame('Take two', $draft->text);
        static::assertSame('Second Attempt', $draft->payload['attributes']['name']);
    }

    public function testItPromotesTheDraftToAPublishedVersionOnApproval(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'name' => 'Publishable',
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'The publish note');

        $this->actingAsUser($admin);
        $this->approve($assistant);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->get();
        static::assertCount(2, $versions);

        $published = $versions->firstWhere('version', '1.0');
        static::assertNotNull($published);
        static::assertSame('The publish note', $published->text);
        static::assertSame('Publishable', $published->payload['attributes']['name']);

        // The respawned draft starts identical to what was just published;
        // the published delta is consumed.
        $draft = $versions->firstWhere('version', '0.0');
        static::assertNotNull($draft);
        static::assertSame('', $draft->text);
        static::assertEquals([], $draft->changed_keys);
        static::assertSame('Publishable', $draft->payload['attributes']['name']);

        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
    }

    public function testItPromotesToTheNextVersionNumberOnTheSecondCycle(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
        $this->actingAsUser($admin);
        $this->approve($assistant);

        // A published assistant is edited and resubmitted: the draft freeze
        // overwrites with the new state, approval promotes to 2.0.
        $this->actingAsUser($user);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Reworked'],
            ],
        ])->assertOk();
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'Reworked the flow');

        $this->actingAsUser($admin);
        $this->approve($assistant);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->pluck('version');
        static::assertEquals(['0.0', '1.0', '2.0'], $versions->all());

        $second = $assistant->assistantVersions()->where('version', '2.0')->first();
        static::assertSame('Reworked', $second->payload['attributes']['name']);
        static::assertSame('Reworked the flow', $second->text);
    }

    public function testItUnpublishesAReleasedAssistantOnEditAndJournalsTheChange(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
        $this->actingAsUser($admin);
        $this->approve($assistant);

        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->fresh()->release_stage);

        // The first content edit unpublishes the assistant — the change never
        // reaches users — but still journals on the working draft, so the
        // resubmission freezes the full delta.
        $this->actingAsUser($user);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Reworked'],
            ],
        ])->assertOk();

        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->fresh()->release_stage);
        static::assertSame('Reworked', $assistant->fresh()->name);

        $draft = $this->draftOf($assistant);
        static::assertEquals(['name'], $draft->changed_keys);
    }

    public function testItLeavesTheDraftUnpromotedOnDenial(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'Please?');

        $this->actingAsUser($admin);
        $this->jsonApiRaw('patch', '/api/hawki/v1/assistant-reviews/' . $this->reviewOf($assistant)->id, [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $this->reviewOf($assistant)->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::DENIED->value,
                    'reason' => 'Not ready',
                ],
            ],
        ])->assertOk();

        // Denial demotes the assistant but leaves the draft (and its frozen
        // state) untouched for a revised resubmission.
        $versions = $assistant->fresh()->assistantVersions()->pluck('version');
        static::assertEquals(['0.0'], $versions->all());
        static::assertSame('Please?', $this->draftOf($assistant)->text);
    }

    public function testItOpensAReviewForARollbackOnAPublishedAssistantAndAppliesItOnApproval(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'name' => 'Version One Name',
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        // Publish three versions through the real workflow.
        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
        $this->actingAsUser($admin);
        $this->approve($assistant);

        foreach (['Version Two Name', 'Version Three Name'] as $name) {
            $this->actingAsUser($user);
            $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
                'data' => [
                    'type' => 'assistants',
                    'id' => (string) $assistant->id,
                    'attributes' => ['name' => $name],
                ],
            ])->assertOk();
            $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
            $this->actingAsUser($admin);
            $this->approve($assistant);
        }

        static::assertSame('Version Three Name', $assistant->fresh()->name);

        // Roll back to version 1: the rollback is itself a submission —
        // nothing changes live, the review reopens for it.
        $this->actingAsUser($admin);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 1.0],
            ],
        ])->assertOk();

        static::assertSame('Version Three Name', $assistant->fresh()->name);
        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
        $draft = $this->draftOf($assistant);
        static::assertSame('Rollback to version 1', $draft->text);
        static::assertSame('Version One Name', $draft->payload['attributes']['name']);
        static::assertContains('name', $draft->changed_keys ?? []);
        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);

        // The history is untouched until the rollback review is approved.
        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->pluck('version');
        static::assertEquals(['0.0', '1.0', '2.0', '3.0'], $versions->all());

        // Approval publishes the restored state as a new version and restores
        // it onto the live assistant.
        $this->approve($assistant);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->pluck('version');
        static::assertEquals(['0.0', '1.0', '2.0', '3.0', '4.0'], $versions->all());
        static::assertSame('Version One Name', $assistant->fresh()->name);
        $restored = $assistant->assistantVersions()->where('version', '4.0')->first();
        static::assertSame('Version One Name', $restored->payload['attributes']['name']);
        static::assertSame('Rollback to version 1', $restored->text);
        static::assertEquals([], $this->draftOf($assistant)->changed_keys);
    }

    public function testItReopensThePendingReviewForARollbackOnAPendingSubmission(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
        $this->actingAsUser($admin);
        $this->approve($assistant);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->fresh()->release_stage);

        // A pending second round is open when the admin rolls back.
        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::FEDERATED);

        $this->actingAsUser($admin);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 1.0],
            ],
        ])->assertOk();

        // The review row survives and judges the rollback now: the pending
        // submission is superseded, the request re-targets the current stage.
        $review = AssistantReview::where('assistant_id', $assistant->id)->sole();
        static::assertSame(AssistantReviewStatus::PENDING, $review->status);
        $assistant->refresh();
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->release_stage);
        static::assertSame(AssistantReleaseStage::ORGANIZATIONAL, $assistant->requested_release_stage);
        static::assertSame('Rollback to version 1', $this->draftOf($assistant)->text);
    }

    public function testItAppendsANewVersionOnRollbackApprovalInsteadOfRewriting(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'name' => 'Round One',
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
        $this->actingAsUser($admin);
        $this->approve($assistant);

        $this->actingAsUser($user);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Round Two'],
            ],
        ])->assertOk();
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
        $this->actingAsUser($admin);
        $this->approve($assistant);
        static::assertSame('Round Two', $assistant->fresh()->name);

        // Back to 1: the rollback opens a review, its approval appends the
        // restored state as version 3 — the history is never rewritten.
        $this->actingAsUser($admin);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 1.0],
            ],
        ])->assertOk();

        $this->approve($assistant);

        $versions = $assistant->fresh()->assistantVersions()->orderBy('version')->pluck('version');
        static::assertEquals(['0.0', '1.0', '2.0', '3.0'], $versions->all());
        static::assertSame('Round One', $assistant->fresh()->name);
        $restored = $assistant->assistantVersions()->where('version', '3.0')->first();
        static::assertSame('Round One', $restored->payload['attributes']['name']);
    }

    public function testItAppliesARollbackImmediatelyOnAnUnpublishedAssistantAndKeepsDecisionRecords(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'name' => 'Published Once',
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        // Publish v1, then a second round is denied: the assistant is back to
        // private with a terminal review on record.
        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
        $this->actingAsUser($admin);
        $this->approve($assistant);

        $this->actingAsUser($user);
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Denied Rework'],
            ],
        ])->assertOk();
        $this->submitForReview($assistant, AssistantReleaseStage::FEDERATED);
        $this->actingAsUser($admin);
        $this->jsonApiRaw('patch', '/api/hawki/v1/assistant-reviews/' . $this->reviewOf($assistant)->id, [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $this->reviewOf($assistant)->id,
                'attributes' => [
                    'status' => AssistantReviewStatus::DENIED->value,
                    'reason' => 'Not this time',
                ],
            ],
        ])->assertOk();
        static::assertSame(AssistantReleaseStage::PRIVATE, $assistant->fresh()->release_stage);

        // Unpublished and nothing pending: the rollback applies immediately.
        $this->actingAsUser($admin);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 1.0],
            ],
        ])->assertOk();

        static::assertSame('Published Once', $assistant->fresh()->name);

        // The denial record survives — earlier decisions are never wiped.
        $this->assertDatabaseHas('assistant_reviews', [
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::DENIED->value,
        ]);
        $draft = $this->draftOf($assistant);
        static::assertSame('Published Once', $draft->payload['attributes']['name']);
        static::assertEquals([], $draft->changed_keys);
    }

    public function testItClearsSettingsTheRollbackTargetDidNotHave(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        // v1.0 is published before any setting value exists; the assistant
        // returns to private so the rollback applies immediately.
        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);
        $this->actingAsUser($admin);
        $this->approve($assistant);
        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::PRIVATE);

        // Settings added afterwards must not survive a rollback to v1.0 —
        // the payload's empty setting list is part of the restored state.
        $setting = AssistantSetting::factory()->create(['key' => 'formality']);
        $assistant->settingValues()->create(['setting_id' => $setting->id, 'value' => 'casual']);
        static::assertCount(1, $assistant->fresh()->settingValues);

        $this->actingAsUser($admin);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 1.0],
            ],
        ])->assertOk();

        static::assertCount(0, $assistant->fresh()->settingValues);
    }

    public function testItRestoresTheSubmittedStateOntoTheLiveAssistantOnApproval(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'name' => 'Submitted Name',
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL, 'The note');

        // The creator keeps editing after submitting; the review still judges
        // the frozen submission.
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistants/{$assistant->id}", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['name' => 'Edited After Submit'],
            ],
        ])->assertOk();

        $this->actingAsUser($admin);
        $this->approve($assistant);

        // What the review judged is what goes live: the post-submit edits are
        // resolved to the approved snapshot.
        static::assertSame('Submitted Name', $assistant->fresh()->name);
        $published = $assistant->assistantVersions()->where('version', '1.0')->first();
        static::assertSame('Submitted Name', $published->payload['attributes']['name']);
    }

    public function testItRequiresAnOrgAdminForRollback(): void
    {
        $member = $this->createMember();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($user);
        $this->submitForReview($assistant, AssistantReleaseStage::ORGANIZATIONAL);

        // The creator is not an org admin…
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 1.0],
            ],
        ])->assertForbidden();

        // …and neither is a plain org member.
        $this->actingAsUser($member);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 1.0],
            ],
        ])->assertForbidden();
    }

    public function testItRejectsARollbackToAnUnknownVersion(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($admin);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 7.0],
            ],
        ])->assertUnprocessable();
    }

    public function testItRejectsARollbackToTheDraftVersion(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $user->id,
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);

        $this->actingAsUser($admin);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/rollback", [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => ['version' => 0.0],
            ],
        ])->assertUnprocessable();
    }

    public function testItFiltersTheReviewQueueByStatus(): void
    {
        $admin = $this->createAdmin();
        $pending = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $pending->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
        $approved = Assistant::factory()->create([
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        AssistantReview::forceCreate([
            'assistant_id' => $approved->id,
            'status' => AssistantReviewStatus::APPROVED->value,
        ]);

        $this->actingAsUser($admin);

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistant-reviews?filter[status]=pending')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        static::assertSame([(string) AssistantReview::where('assistant_id', $pending->id)->sole()->id], $ids);
    }

    private function submitForReview(Assistant $assistant, AssistantReleaseStage $stage, ?string $note = null): void
    {
        $payload = [
            'data' => [
                'type' => 'assistants',
                'id' => (string) $assistant->id,
                'attributes' => array_filter([
                    'release_stage' => $stage->value,
                    'note' => $note,
                ], static fn ($value) => null !== $value),
            ],
        ];

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/release", $payload)
            ->assertOk();
    }

    private function approve(Assistant $assistant): void
    {
        $review = $this->reviewOf($assistant);

        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => ['status' => AssistantReviewStatus::APPROVED->value],
            ],
        ])->assertOk();
    }

    private function reviewOf(Assistant $assistant): AssistantReview
    {
        return AssistantReview::where('assistant_id', $assistant->id)->sole();
    }

    private function draftOf(Assistant $assistant): AssistantVersion
    {
        return $assistant->assistantVersions()
            ->where('version', AssistantVersion::DRAFT_VERSION)
            ->sole();
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
