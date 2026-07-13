<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Assistants\AssistantReview;
use App\Models\User;
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
        private DatabaseManager $db,
        private ClockInterface $clock,
    ) {
    }

    /**
     * React to a review status transition: promote on approval, demote on
     * denial. Only genuine transitions trigger the side effects; the previous
     * status is supplied by the caller (captured reliably before the save).
     *
     * @param AssistantReviewStatus|null $previous the status before the update, or null if unknown
     */
    public function applyStatusTransition(AssistantReview $review, ?AssistantReviewStatus $previous, ?User $reviewer): void
    {
        $newStatus = $review->status;

        if (AssistantReviewStatus::APPROVED === $newStatus && AssistantReviewStatus::APPROVED !== $previous) {
            if (null !== $reviewer) {
                $this->approve($review, $reviewer);
            }

            return;
        }

        if (AssistantReviewStatus::DENIED === $newStatus && AssistantReviewStatus::DENIED !== $previous) {
            if (null !== $reviewer) {
                $this->deny($review, $reviewer, $review->reason);
            }
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
     * @param AssistantReview $review
     * @param User $reviewer
     * @return void
     */
    public function approve(AssistantReview $review, User $reviewer): void
    {
        $this->db->transaction(function () use ($review, $reviewer): void {
            $locked = AssistantReview::whereKey($review->id)->lockForUpdate()->first();

            if ($locked === null) {
                abort(422 ,"The requested review does not exist.");
            }

            // Re-apply the audit fields onto the locked row directly so the
            // update is consistent with the latest persisted state.
            $locked->status = AssistantReviewStatus::APPROVED;
            $locked->reviewer_id = $reviewer->id;
            $locked->reviewed_at = Carbon::instance($this->clock->now());
            $locked->save();

            $review->setRawAttributes($locked->getAttributes()); # TODO: use orm

            $this->assistantService->promoteRequested($locked->assistant);
        });
    }

    /**
     * Mark the review denied with an optional reason, record who denied it and
     * when, then demote the assistant back to private.
     *
     * @see approve() for the transition-detection contract with applyStatusTransition().
     */
    public function deny(AssistantReview $review, User $reviewer, ?string $reason = null): void
    {
        $this->db->transaction(function () use ($review, $reviewer, $reason): void {
            $locked = AssistantReview::whereKey($review->id)->lockForUpdate()->first();

            if ($locked === null) {
                abort(422 ,"The requested review does not exist.");
            }

            $locked->status = AssistantReviewStatus::DENIED;
            $locked->reason = $reason;
            $locked->reviewer_id = $reviewer->id;
            $locked->reviewed_at = Carbon::instance($this->clock->now());
            $locked->save();

            $review->setRawAttributes($locked->getAttributes());

            $this->assistantService->revokeRelease($locked->assistant);
        });
    }
}

