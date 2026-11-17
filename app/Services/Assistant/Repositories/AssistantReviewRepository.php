<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\AssistantReview;
use App\Services\Assistant\Values\AssistantReviewStatus;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;

#[UseModel(AssistantReview::class)]
class AssistantReviewRepository extends AbstractRepository
{
    public function find(AssistantReview $review): AssistantReview
    {
        return $review->load('assistant');
    }

    /**
     * Re-fetch the review by key under a row lock, so parallel decisions
     * serialise inside the caller's transaction. Null when the review row
     * vanished between the caller's fetch and this lock (a lost race).
     */
    public function lockForUpdate(AssistantReview $review): ?AssistantReview
    {
        return $this->getQuery()->whereKey($review->getKey())->lockForUpdate()->first();
    }

    public function updateOrCreateForAssistant(int $assistantId, array $data): AssistantReview
    {
        $review = $this->getQuery()->where('assistant_id', $assistantId)->first()
            ?? (new AssistantReview())->forceFill(['assistant_id' => $assistantId]);

        // status/assistant_id are intentionally not mass-assignable.
        $review->forceFill($data)->save();

        return $review;
    }

    public function update(AssistantReview $review, array $data): AssistantReview
    {
        $review->forceFill($data)->save();

        return $review;
    }

    public function deleteReviewForAssistantUnlessDenied(int $assistantId): void
    {
        // Both denial variants stick: a denied review must survive the demotion
        // to private that the denial itself triggers (see AssistantReleaseStatus),
        // and a needs_revision review must survive so the creator can resubmit.
        $this->getQuery()
            ->where('assistant_id', $assistantId)
            ->whereNotIn('status', [
                AssistantReviewStatus::DENIED->value,
                AssistantReviewStatus::NEEDS_REVISION->value,
            ])
            ->delete();
    }
}
