<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantReview;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewHistory;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantReviewHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function testItAppendsADecisionEntryWithReason(): void
    {
        $assistant = $this->pendingAssistant();
        $review = $this->pendingReview($assistant);

        $this->actingAsUser($this->createAdmin());

        $this->decide($review, AssistantReviewStatus::NEEDS_REVISION, 'Please tighten the greeting.');

        $entries = $this->historyEntries($review);

        static::assertCount(1, $entries);
        static::assertSame(AssistantReviewStatus::NEEDS_REVISION, $entries[0]->status);
        static::assertSame('Please tighten the greeting.', $entries[0]->reason);
        static::assertNotNull($entries[0]->reviewedAt);
    }

    public function testItRecordsApprovalWithoutReason(): void
    {
        $assistant = $this->pendingAssistant();
        $review = $this->pendingReview($assistant);

        $this->actingAsUser($this->createAdmin());

        $this->decide($review, AssistantReviewStatus::APPROVED);

        $entries = $this->historyEntries($review);

        static::assertCount(1, $entries);
        static::assertSame(AssistantReviewStatus::APPROVED, $entries[0]->status);
        static::assertNull($entries[0]->reason);
    }

    public function testItDoesNotAppendAnEntryWhenTheStatusDoesNotChange(): void
    {
        $assistant = $this->pendingAssistant();
        $review = $this->pendingReview($assistant);

        $admin = $this->createAdmin();
        $this->actingAsUser($admin);

        $this->decide($review, AssistantReviewStatus::APPROVED);
        $this->decide($review, AssistantReviewStatus::APPROVED);

        static::assertCount(1, $this->historyEntries($review));
    }

    public function testItKeepsTheDecisionHistoryAcrossResubmissions(): void
    {
        $creator = User::factory()->create();
        $assistant = $this->pendingAssistant($creator);
        $review = $this->pendingReview($assistant);

        $this->actingAsUser($this->createAdmin());
        $this->decide($review, AssistantReviewStatus::NEEDS_REVISION, 'Please tighten the greeting.');

        $this->actingAsUser($creator);
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

        // The reopened round starts fresh, the previous decision survives.
        $review->refresh();
        static::assertSame(AssistantReviewStatus::PENDING, $review->status);
        static::assertNull($review->reason);
        static::assertCount(1, $this->historyEntries($review));

        $this->actingAsUser($this->createAdmin());
        $this->decide($review, AssistantReviewStatus::DENIED, 'Still not ready.');

        $entries = $this->historyEntries($review);

        static::assertCount(2, $entries);
        static::assertSame(AssistantReviewStatus::NEEDS_REVISION, $entries[0]->status);
        static::assertSame('Please tighten the greeting.', $entries[0]->reason);
        static::assertSame(AssistantReviewStatus::DENIED, $entries[1]->status);
        static::assertSame('Still not ready.', $entries[1]->reason);
    }

    public function testItRecordsSwitchesBetweenTheDenialVariants(): void
    {
        $assistant = $this->pendingAssistant();
        $review = $this->pendingReview($assistant);

        $admin = $this->createAdmin();
        $this->actingAsUser($admin);

        $this->decide($review, AssistantReviewStatus::NEEDS_REVISION, 'Tighten the greeting.');
        $this->decide($review, AssistantReviewStatus::DENIED, 'Still not ready.');

        $entries = $this->historyEntries($review);

        static::assertCount(2, $entries);
        static::assertSame(AssistantReviewStatus::NEEDS_REVISION, $entries[0]->status);
        static::assertSame('Tighten the greeting.', $entries[0]->reason);
        static::assertSame(AssistantReviewStatus::DENIED, $entries[1]->status);
        static::assertSame('Still not ready.', $entries[1]->reason);

        $review->refresh();
        static::assertSame(AssistantReviewStatus::DENIED, $review->status);
        static::assertSame('Still not ready.', $review->reason);
    }

    public function testItRecordsTheLiftFromDeniedToNeedsRevision(): void
    {
        $assistant = $this->pendingAssistant();
        $review = $this->pendingReview($assistant);

        $admin = $this->createAdmin();
        $this->actingAsUser($admin);

        $this->decide($review, AssistantReviewStatus::DENIED, 'No.');
        $this->decide($review, AssistantReviewStatus::NEEDS_REVISION, 'On second thought, fix it.');

        $entries = $this->historyEntries($review);

        static::assertCount(2, $entries);
        static::assertSame(AssistantReviewStatus::DENIED, $entries[0]->status);
        static::assertSame(AssistantReviewStatus::NEEDS_REVISION, $entries[1]->status);
        static::assertSame('On second thought, fix it.', $entries[1]->reason);

        $review->refresh();
        static::assertSame($admin->id, $review->reviewer_id);
        static::assertNotNull($review->reviewed_at);
    }

    public function testItRecordsAReDecisionWithANewReason(): void
    {
        $assistant = $this->pendingAssistant();
        $review = $this->pendingReview($assistant);

        $this->actingAsUser($this->createAdmin());

        $this->decide($review, AssistantReviewStatus::DENIED, 'First reason.');
        $this->decide($review, AssistantReviewStatus::DENIED, 'Second reason.');

        $entries = $this->historyEntries($review);

        static::assertCount(2, $entries);
        static::assertSame('First reason.', $entries[0]->reason);
        static::assertSame('Second reason.', $entries[1]->reason);
    }

    public function testTheAdminDetailSurfacesTheHistory(): void
    {
        $assistant = $this->pendingAssistant();
        $review = $this->pendingReview($assistant);

        $this->actingAsUser($this->createAdmin());
        $this->decide($review, AssistantReviewStatus::NEEDS_REVISION, 'Please tighten the greeting.');

        $this->actingAsUser($this->createAdmin());
        $show = $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=assistant_review")
            ->assertOk();

        $included = collect($show->json('included') ?? [])
            ->first(static fn ($item) => $item['type'] === 'assistant-reviews');

        static::assertNotNull($included);
        static::assertSame('needs_revision', $included['attributes']['history'][0]['status']);
        static::assertSame('Please tighten the greeting.', $included['attributes']['history'][0]['reason']);
    }

    private function pendingAssistant(?User $creator = null): Assistant
    {
        return Assistant::factory()->create([
            ...(null !== $creator ? ['creator_id' => $creator->id] : []),
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
        ]);
    }

    private function pendingReview(Assistant $assistant): AssistantReview
    {
        return AssistantReview::forceCreate([
            'assistant_id' => $assistant->id,
            'status' => AssistantReviewStatus::PENDING->value,
        ]);
    }

    private function decide(AssistantReview $review, AssistantReviewStatus $status, ?string $reason = null): void
    {
        $this->jsonApiRaw('patch', "/api/hawki/v1/assistant-reviews/{$review->id}", [
            'data' => [
                'type' => 'assistant-reviews',
                'id' => (string) $review->id,
                'attributes' => [
                    'status' => $status->value,
                    ...(null !== $reason ? ['reason' => $reason] : []),
                ],
            ],
        ])
            ->assertOk();
    }

    /**
     * @return list<\App\Services\Assistant\Values\AssistantReviewDecisionEntry>
     */
    private function historyEntries(AssistantReview $review): array
    {
        return AssistantReviewHistory::fromRows($review->refresh()->history)->entries();
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->organizations()->attach(Organization::first(), ['role' => 'admin']);

        return $user;
    }
}
