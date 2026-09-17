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

    public function resetReviewForAssistant(int $assistantId): void
    {
        $review = $this->getQuery()->where('assistant_id', $assistantId)->first();

        if (null === $review) {
            return;
        }

        $review->status = AssistantReviewStatus::PENDING;
        $review->reason = 'Assistant updated since last review';
        $review->save();
    }

    /**
     * A denied or blocked review is a terminal state an admin must clear
     * explicitly — dropping back to a non-public stage otherwise tears the
     * review down (see AssistantReleaseStatus).
     */
    public function deleteReviewForAssistantUnlessTerminal(int $assistantId): void
    {
        $this->getQuery()
            ->where('assistant_id', $assistantId)
            ->whereNotIn('status', [AssistantReviewStatus::DENIED->value, AssistantReviewStatus::BLOCKED->value])
            ->delete();
    }
}
