<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Assistants\AssistantReview;
use App\Models\User;
use App\Services\Assistant\Exceptions\AssistantReviewNotFoundException;
use App\Services\Assistant\Repositories\AssistantReviewRepository;
use App\Services\Assistant\Values\AssistantReviewDecisionEntry;
use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Psr\Clock\ClockInterface;

#[Singleton()]
readonly class AssistantReviewService
{
    public function __construct(
        private AssistantService $assistantService,
        private AssistantVersionService $versionService,
        private AssistantReviewRepository $reviewRepository,
        private DatabaseManager $db,
        private ClockInterface $clock,
    ) {
    }

    /**
     * React to a review status transition: promote on approval, demote on
     * denial. Only genuine transitions trigger those side effects; the
     * previous status and reason are supplied by the caller (captured
     * reliably before the save). Every decision that changes either is
     * recorded in the history — including switches between the denial
     * variants and re-decisions with a new reason.
     *
     * @param null|AssistantReviewStatus $previous         the status before the update, or null if unknown
     * @param null|string                $previousReason   the reason before the update
     */
    public function applyStatusTransition(
        AssistantReview $review,
        null|AssistantReviewStatus $previous,
        null|User $reviewer,
        null|string $previousReason = null,
    ): void {
        $newStatus = $review->status;

        if (AssistantReviewStatus::APPROVED === $newStatus && AssistantReviewStatus::APPROVED !== $previous) {
            if (null !== $reviewer) {
                $this->approve($review, $reviewer);
            }

            return;
        }

        // An org admin can reject an assistant in two ways:
        // 1) DENIED permanently blocks resubmission,
        // 2) NEEDS_REVISION lets the creator revise and submit again.
        // Both statuses are treated the same here: demote the assistant back to private.
        if (self::isDenial($newStatus) && !self::isDenial($previous)) {
            if (null !== $reviewer) {
                $this->deny($review, $reviewer, $review->reason, $newStatus);
            }

            return;
        }

        // No promote/demote fires between the denial variants or on a
        // same-status re-decision — but the decision is still recorded.
        if (null !== $reviewer && ($previous !== $newStatus || $previousReason !== $review->reason)) {
            $this->recordDecision($review, $reviewer);
        }
    }

    /**
     * Mark the review approved, record who approved it and when, then promote
     * the assistant to its requested public stage.
     *
     * Concurrency: the review row is locked for the duration of the
     * transaction so parallel approve()/deny() calls serialise. The caller is
     * responsible for invoking this only on a real transition into APPROVED
     * (applyStatusTransition() performs that gate).
     */
    public function approve(AssistantReview $review, User $reviewer): void
    {
        $this->db->transaction(function () use ($review, $reviewer): void {
            $locked = $this->reviewRepository->lockForUpdate($review);

            if (null === $locked) {
                throw AssistantReviewNotFoundException::duringDecision();
            }

            // The reason is cleared: it belonged to a previous denial that the
            // approval supersedes (reopening clears it the same way).
            $this->applyDecision($locked, $reviewer, AssistantReviewStatus::APPROVED, null);
            $review->setRawAttributes($locked->getAttributes()); // TODO: use orm

            // The approved state becomes the next published version; a fresh
            // working draft is respawned from it.
            $promoted = $this->versionService->promoteDraft($locked->assistant);

            // The reviewed snapshot goes live; drafts without a payload (legacy/fixture) keep the live state.
            if (\is_array($promoted->payload)) {
                $this->versionService->restore($locked->assistant, $promoted->payload);
            }

            $this->assistantService->promoteRequested($locked->assistant);
        });
    }

    /**
     * Mark the review denied with an optional reason, record who denied it and
     * when, then demote the assistant back to private.
     *
     * The denial variant is chosen by $status: DENIED permanently blocks any
     * resubmission, NEEDS_REVISION lets the creator revise and submit again.
     *
     * @see approve() for the transition-detection contract with applyStatusTransition().
     */
    public function deny(
        AssistantReview $review,
        User $reviewer,
        ?string $reason = null,
        AssistantReviewStatus $status = AssistantReviewStatus::DENIED,
    ): void {
        $this->db->transaction(function () use ($review, $reviewer, $reason, $status): void {
            $locked = $this->reviewRepository->lockForUpdate($review);

            if (null === $locked) {
                throw AssistantReviewNotFoundException::duringDecision();
            }

            $this->applyDecision($locked, $reviewer, $status, $reason);
            $review->setRawAttributes($locked->getAttributes());

            $this->assistantService->revokeRelease($locked->assistant);
        });
    }

    /**
     * Record a decision without promote/demote side effects: the switches
     * between the denial variants and same-status re-decisions with a new
     * reason still belong in the history, with a fresh reviewer stamp.
     */
    private function recordDecision(AssistantReview $review, User $reviewer): void
    {
        $this->db->transaction(function () use ($review, $reviewer): void {
            $locked = $this->reviewRepository->lockForUpdate($review);

            if (null === $locked) {
                throw AssistantReviewNotFoundException::duringDecision();
            }

            $this->applyDecision($locked, $reviewer, $locked->status, $locked->reason);
            $review->setRawAttributes($locked->getAttributes());
        });
    }

    /**
     * Persist a decision's audit fields and history entry onto the locked row.
     */
    private function applyDecision(AssistantReview $locked, User $reviewer, AssistantReviewStatus $status, null|string $reason): void
    {
        $reviewedAt = $this->clock->now();

        $locked->status = $status;
        $locked->reason = $reason;
        $locked->reviewer_id = $reviewer->id;
        $locked->reviewed_at = Carbon::instance($reviewedAt);
        $locked->appendDecision(AssistantReviewDecisionEntry::from($status, $reason, $reviewedAt));
        $locked->save();
    }

    /**
     * Whether the status is one of the two denial variants.
     */
    private static function isDenial(?AssistantReviewStatus $status): bool
    {
        return AssistantReviewStatus::DENIED === $status
            || AssistantReviewStatus::NEEDS_REVISION === $status;
    }
}

